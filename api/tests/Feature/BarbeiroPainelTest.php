<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Funcionario;
use App\Models\User;
use App\Notifications\AcessoBarbeiro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Painel do barbeiro: o dono libera o acesso pelo e-mail do cadastro e o barbeiro
 * vê só a própria agenda e a própria comissão.
 */
class BarbeiroPainelTest extends TestCase
{
    use RefreshDatabase;

    private User $dono;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->dono = $this->logarComo('dono');
    }

    private function cadastrar(array $dados = []): TestResponse
    {
        Sanctum::actingAs($this->dono);

        return $this->postJson('/api/funcionarios', $dados + ['nome' => 'Carlos', 'email' => 'carlos@exemplo.test']);
    }

    private function entrar(string $email, string $senha): string
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/auth/login', ['email' => $email, 'password' => $senha])->assertOk()->json('token');
    }

    public function test_cadastro_com_email_libera_o_acesso_e_envia_a_senha(): void
    {
        $r = $this->cadastrar()
            ->assertCreated()
            ->assertJsonPath('acesso.email', 'carlos@exemplo.test')
            ->assertJsonPath('acesso.email_enviado', true)
            ->assertJsonPath('acesso_erro', null);

        $this->assertStringEndsWith('/barbeiro', $r->json('acesso.painel_url'));
        $this->assertSame($r->json('acesso.painel_url'), $r->json('painel_barbeiro_url'));

        $usuario = User::where('email', 'carlos@exemplo.test')->first();
        $this->assertSame('barbeiro', $usuario->perfil);
        $this->assertSame($usuario->id, $r->json('usuario_id'));

        $senha = $r->json('acesso.senha_provisoria');
        Notification::assertSentTo($usuario, AcessoBarbeiro::class, function (AcessoBarbeiro $n) use ($usuario, $senha) {
            $email = $n->toMail($usuario);
            $texto = implode("\n", $email->introLines);

            return str_contains($texto, $senha) && str_contains($texto, '/barbeiro')
                && str_contains($email->actionUrl, '/redefinir-senha?token=');
        });

        // Entra com a provisória e precisa criar a própria senha.
        $this->entrar('carlos@exemplo.test', $senha);
        $this->postJson('/api/auth/login', ['email' => 'carlos@exemplo.test', 'password' => $senha])
            ->assertJsonPath('usuario.perfil', 'barbeiro')
            ->assertJsonPath('usuario.trocar_senha', true);
    }

    public function test_email_e_obrigatorio_e_email_de_outro_acesso_nao_cria_login(): void
    {
        $this->cadastrar(['email' => null])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        // O dono que também corta cabelo: o cadastro continua, com o aviso.
        $this->cadastrar(['nome' => 'Dono', 'email' => $this->dono->email])
            ->assertCreated()
            ->assertJsonPath('acesso', null)
            ->assertJsonPath('acesso_erro', 'Este e-mail já é usado por outro acesso ao painel. Use outro e-mail para o barbeiro.');

        $this->assertSame(1, User::count());
        Notification::assertNothingSent();
    }

    public function test_dono_libera_acesso_depois_e_gera_nova_senha(): void
    {
        // Barbeiro cadastrado antes do e-mail ser obrigatório.
        $id = $this->naEmpresa($this->dono->empresa, fn () => Funcionario::create(['nome' => 'Carlos']))->id;

        $this->postJson("/api/funcionarios/{$id}/acesso")->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $this->putJson("/api/funcionarios/{$id}", ['nome' => 'Carlos', 'email' => 'carlos@exemplo.test'])->assertOk();
        $primeira = $this->postJson("/api/funcionarios/{$id}/acesso")->assertOk()->json('acesso.senha_provisoria');
        $token = $this->entrar('carlos@exemplo.test', $primeira);

        // Nova senha: a anterior para de valer e a sessão aberta é encerrada.
        Sanctum::actingAs($this->dono);
        $segunda = $this->postJson("/api/funcionarios/{$id}/acesso")->assertOk()->json('acesso.senha_provisoria');
        $this->assertSame(1, User::where('perfil', 'barbeiro')->count());
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => hash('sha256', explode('|', $token)[1])]);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => 'carlos@exemplo.test', 'password' => $primeira])->assertUnprocessable();
        $this->entrar('carlos@exemplo.test', $segunda);
    }

    public function test_edicao_e_exclusao_do_barbeiro_valem_para_o_login(): void
    {
        $r = $this->cadastrar();
        $id = $r->json('id');
        $senha = $r->json('acesso.senha_provisoria');
        User::factory()->for($this->dono->empresa)->create(['email' => 'ocupado@exemplo.test']);

        $this->putJson("/api/funcionarios/{$id}", ['nome' => 'Carlos', 'email' => 'ocupado@exemplo.test'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email']);
        $this->assertSame('carlos@exemplo.test', Funcionario::find($id)->email); // nada foi salvo

        $this->putJson("/api/funcionarios/{$id}", ['nome' => 'Carlos Silva', 'email' => 'novo@exemplo.test'])->assertOk();
        $this->entrar('novo@exemplo.test', $senha);

        Sanctum::actingAs($this->dono);
        $this->putJson("/api/funcionarios/{$id}", ['nome' => 'Carlos Silva', 'ativo' => false])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => 'novo@exemplo.test', 'password' => $senha])->assertUnprocessable();

        Sanctum::actingAs($this->dono);
        $this->putJson("/api/funcionarios/{$id}", ['nome' => 'Carlos Silva', 'ativo' => true])->assertOk();
        $this->deleteJson("/api/funcionarios/{$id}")->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => 'novo@exemplo.test', 'password' => $senha])->assertUnprocessable();
    }

    public function test_barbeiro_ve_so_a_propria_agenda_sem_contato_do_cliente(): void
    {
        $barbeiro = User::factory()->for($this->dono->empresa)->perfil('barbeiro')->create();
        $this->naEmpresa($this->dono->empresa, function () use ($barbeiro) {
            $carlos = Funcionario::create(['nome' => 'Carlos', 'usuario_id' => $barbeiro->id]);
            $rafael = Funcionario::create(['nome' => 'Rafael']);
            $carlos->horariosTrabalho()->create(['dia_semana' => 2, 'inicio' => '09:00', 'fim' => '12:00']);
            $cliente = Cliente::create(['nome' => 'João', 'telefone' => '11988887777']);
            foreach ([[$carlos, '09:00'], [$rafael, '10:00'], [$carlos, '11:00']] as [$f, $hora]) {
                Agendamento::create([
                    'cliente_id' => $cliente->id,
                    'funcionario_id' => $f->id,
                    'inicio' => Carbon::parse("2030-01-15 {$hora}", 'America/Sao_Paulo')->utc(),
                    'fim' => Carbon::parse("2030-01-15 {$hora}", 'America/Sao_Paulo')->addMinutes(30)->utc(),
                    'status' => $hora === '11:00' ? 'cancelado' : 'confirmado',
                    'valor_total' => 45,
                ]);
            }
        });

        Sanctum::actingAs($barbeiro);
        $r = $this->getJson('/api/barbeiro/agenda?data=2030-01-15')
            ->assertOk()
            ->assertJsonPath('barbeiro.nome', 'Carlos')
            ->assertJsonPath('expediente.0.inicio', '09:00')
            ->assertJsonCount(1, 'agendamentos') // o do Rafael e o cancelado ficam de fora
            ->assertJsonPath('agendamentos.0.inicio', '2030-01-15T09:00:00-03:00')
            ->assertJsonPath('agendamentos.0.cliente', ['nome' => 'João', 'observacoes' => null]);
        $this->assertStringNotContainsString('11988887777', $r->getContent());

        // O resto do painel da barbearia fica fechado para o barbeiro.
        $this->getJson('/api/clientes')->assertForbidden();
        $this->getJson('/api/funcionarios')->assertForbidden();
        $this->getJson('/api/barbeiro/comissoes')->assertOk()->assertJsonCount(1, 'barbeiros');

        // E o painel do barbeiro é só dele.
        Sanctum::actingAs($this->dono);
        $this->getJson('/api/barbeiro/agenda')->assertForbidden();
    }

    public function test_barbeiro_inicia_o_proprio_atendimento_de_hoje_mas_nao_conclui(): void
    {
        Carbon::setTestNow(Carbon::parse('2030-01-15 08:00', 'America/Sao_Paulo'));
        $barbeiro = User::factory()->for($this->dono->empresa)->perfil('barbeiro')->create();
        [$meu, $doRafael, $amanha] = $this->naEmpresa($this->dono->empresa, function () use ($barbeiro) {
            $carlos = Funcionario::create(['nome' => 'Carlos', 'usuario_id' => $barbeiro->id]);
            $rafael = Funcionario::create(['nome' => 'Rafael']);
            $cliente = Cliente::create(['nome' => 'João', 'telefone' => '11988887777']);
            $ag = fn ($f, $quando) => Agendamento::create([
                'cliente_id' => $cliente->id,
                'funcionario_id' => $f->id,
                'inicio' => Carbon::parse($quando, 'America/Sao_Paulo')->utc(),
                'fim' => Carbon::parse($quando, 'America/Sao_Paulo')->addMinutes(30)->utc(),
                'status' => 'confirmado',
                'valor_total' => 45,
            ]);

            return [$ag($carlos, '2030-01-15 09:00'), $ag($rafael, '2030-01-15 10:00'), $ag($carlos, '2030-01-16 09:00')];
        });

        Sanctum::actingAs($barbeiro);
        $this->postJson("/api/barbeiro/agendamentos/{$meu->id}/iniciar")->assertOk()->assertJsonPath('status', 'em_atendimento');
        $this->postJson("/api/barbeiro/agendamentos/{$meu->id}/iniciar")->assertUnprocessable(); // já iniciado
        $this->postJson("/api/barbeiro/agendamentos/{$doRafael->id}/iniciar")->assertForbidden();
        $this->postJson("/api/barbeiro/agendamentos/{$amanha->id}/iniciar")
            ->assertUnprocessable()->assertJsonPath('message', 'Só é possível iniciar atendimentos de hoje.');

        // Concluir continua com a recepção ou o dono.
        $this->patchJson("/api/agendamentos/{$meu->id}/status", ['status' => 'concluido'])->assertForbidden();
        $this->assertSame('em_atendimento', $meu->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_login_de_barbeiro_sem_cadastro_ligado_avisa(): void
    {
        Sanctum::actingAs(User::factory()->for($this->dono->empresa)->perfil('barbeiro')->create());

        $this->getJson('/api/barbeiro/agenda')->assertNotFound()
            ->assertJsonPath('message', 'Seu acesso não está ligado a um barbeiro. Fale com o dono da barbearia.');
    }
}
