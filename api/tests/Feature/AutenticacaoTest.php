<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AutenticacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_barbearia_com_usuario_dono(): void
    {
        $resposta = $this->postJson('/api/auth/registrar', [
            'empresa_nome' => 'Barbearia do Zé',
            'nome' => 'José Silva',
            'email' => 'ze@exemplo.test',
            'password' => 'segredo123',
            'password_confirmation' => 'segredo123',
            'aceite_termos' => true,
        ]);

        $resposta->assertCreated()
            ->assertJsonStructure(['token', 'usuario' => ['id', 'nome', 'email', 'perfil'], 'empresa' => ['id', 'nome', 'slug']])
            ->assertJsonPath('usuario.perfil', 'dono')
            ->assertJsonPath('empresa.slug', 'barbearia-do-ze')
            ->assertJsonPath('empresa.fuso_horario', 'America/Sao_Paulo');

        $this->assertDatabaseHas('usuarios', ['email' => 'ze@exemplo.test', 'perfil' => 'dono']);
    }

    public function test_slug_repetido_recebe_sufixo(): void
    {
        Empresa::factory()->create(['nome' => 'Barbearia do Zé', 'slug' => 'barbearia-do-ze']);

        $this->postJson('/api/auth/registrar', [
            'empresa_nome' => 'Barbearia do Zé',
            'nome' => 'Outro Zé',
            'email' => 'outro@exemplo.test',
            'password' => 'segredo123',
            'password_confirmation' => 'segredo123',
            'aceite_termos' => true,
        ])->assertCreated()->assertJsonPath('empresa.slug', 'barbearia-do-ze-2');
    }

    public function test_registro_rejeita_email_duplicado_e_senha_fraca(): void
    {
        User::factory()->create(['email' => 'ze@exemplo.test']);

        $this->postJson('/api/auth/registrar', [
            'empresa_nome' => 'Barbearia',
            'nome' => 'Zé',
            'email' => 'ze@exemplo.test',
            'password' => '123',
            'password_confirmation' => '123',
            'aceite_termos' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_com_credenciais_validas_retorna_token(): void
    {
        $usuario = User::factory()->create(['email' => 'dono@exemplo.test']);

        $this->postJson('/api/auth/login', ['email' => 'dono@exemplo.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('usuario.id', $usuario->id)
            ->assertJsonPath('empresa.id', $usuario->empresa_id)
            ->assertJsonStructure(['token']);
    }

    public function test_login_com_senha_errada_falha(): void
    {
        User::factory()->create(['email' => 'dono@exemplo.test']);

        $this->postJson('/api/auth/login', ['email' => 'dono@exemplo.test', 'password' => 'errada'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_usuario_ou_barbearia_desativados_nao_entram(): void
    {
        User::factory()->inativo()->create(['email' => 'inativo@exemplo.test']);
        User::factory()->for(Empresa::factory()->inativa())->create(['email' => 'empresa@exemplo.test']);

        $this->postJson('/api/auth/login', ['email' => 'inativo@exemplo.test', 'password' => 'password'])
            ->assertUnprocessable();
        $this->postJson('/api/auth/login', ['email' => 'empresa@exemplo.test', 'password' => 'password'])
            ->assertUnprocessable();
    }

    public function test_login_bloqueia_apos_muitas_tentativas(): void
    {
        User::factory()->create(['email' => 'dono@exemplo.test']);

        foreach (range(1, 5) as $tentativa) {
            $this->postJson('/api/auth/login', ['email' => 'dono@exemplo.test', 'password' => 'errada']);
        }

        $this->postJson('/api/auth/login', ['email' => 'dono@exemplo.test', 'password' => 'password'])
            ->assertStatus(429);
    }

    public function test_eu_e_logout(): void
    {
        $usuario = User::factory()->create();
        $token = $usuario->createToken('teste')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/eu')
            ->assertOk()
            ->assertJsonPath('usuario.email', $usuario->email);

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_rotas_protegidas_exigem_login(): void
    {
        $this->getJson('/api/auth/eu')->assertUnauthorized();
        $this->getJson('/api/empresa')->assertUnauthorized();
    }

    public function test_somente_dono_altera_dados_da_barbearia(): void
    {
        $empresa = Empresa::factory()->create();
        $recepcao = User::factory()->for($empresa)->perfil('recepcao')->create();
        $dono = User::factory()->for($empresa)->perfil('dono')->create();

        $this->actingAs($recepcao)->putJson('/api/empresa', ['nome' => 'Novo nome'])->assertForbidden();
        $this->actingAs($dono)->putJson('/api/empresa', ['nome' => 'Novo nome'])
            ->assertOk()
            ->assertJsonPath('nome', 'Novo nome');
    }

    public function test_esqueci_a_senha_envia_link_para_o_painel(): void
    {
        Notification::fake();
        config(['app.painel_url' => 'https://painel.test/saas/barbearia/painel']);
        $usuario = User::factory()->create(['email' => 'ze@exemplo.test']);

        $this->postJson('/api/auth/esqueci-senha', ['email' => 'ze@exemplo.test'])->assertOk();

        Notification::assertSentTo($usuario, ResetPassword::class, function (ResetPassword $n) use ($usuario) {
            $email = $n->toMail($usuario);

            return str_starts_with($email->actionUrl, 'https://painel.test/saas/barbearia/painel/redefinir-senha?token=')
                && str_contains($email->actionUrl, 'email=ze%40exemplo.test');
        });
    }

    public function test_esqueci_a_senha_nao_revela_se_o_email_existe(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'inativo@exemplo.test', 'ativo' => false]);

        $existe = $this->postJson('/api/auth/esqueci-senha', ['email' => 'inativo@exemplo.test'])->assertOk()->json('message');
        $naoExiste = $this->postJson('/api/auth/esqueci-senha', ['email' => 'ninguem@exemplo.test'])->assertOk()->json('message');

        $this->assertSame($existe, $naoExiste);
        Notification::assertNothingSent();
    }

    public function test_redefine_a_senha_com_token_valido_e_encerra_sessoes(): void
    {
        $usuario = User::factory()->create(['email' => 'ze@exemplo.test']);
        $usuario->createToken('celular');
        $token = Password::createToken($usuario);

        $this->postJson('/api/auth/redefinir-senha', [
            'token' => $token,
            'email' => 'ze@exemplo.test',
            'password' => 'novaSenha123',
            'password_confirmation' => 'novaSenha123',
        ])->assertOk();

        $this->assertTrue(Hash::check('novaSenha123', $usuario->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/auth/login', ['email' => 'ze@exemplo.test', 'password' => 'novaSenha123'])->assertOk();
    }

    public function test_redefinir_senha_rejeita_token_invalido_e_senha_fraca(): void
    {
        $usuario = User::factory()->create(['email' => 'ze@exemplo.test']);

        $this->postJson('/api/auth/redefinir-senha', [
            'token' => 'token-falso',
            'email' => 'ze@exemplo.test',
            'password' => 'novaSenha123',
            'password_confirmation' => 'novaSenha123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['token']);

        $this->postJson('/api/auth/redefinir-senha', [
            'token' => Password::createToken($usuario),
            'email' => 'ze@exemplo.test',
            'password' => '123',
            'password_confirmation' => '123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }
}
