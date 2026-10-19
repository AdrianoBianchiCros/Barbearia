<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ]);

        $resposta->assertCreated()
            ->assertJsonStructure(['token', 'usuario' => ['id', 'nome', 'email', 'perfil'], 'empresa' => ['id', 'nome', 'slug']])
            ->assertJsonPath('usuario.perfil', 'dono')
            ->assertJsonPath('empresa.slug', 'barbearia-do-ze');

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
}
