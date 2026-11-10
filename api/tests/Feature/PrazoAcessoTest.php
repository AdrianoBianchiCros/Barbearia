<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Models\User;
use App\Notifications\AvisoVencimentoAcesso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Prazo de acesso pago: avisos, carência de 5 dias e bloqueio depois dela.
 * Hoje (nos testes) = terça, 10/11/2026.
 */
class PrazoAcessoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 10:00', 'America/Sao_Paulo'));
    }

    private function donoComAcessoAte(?string $data): User
    {
        $empresa = Empresa::factory()->create(['slug' => 'barbearia-do-ze', 'acesso_ate' => $data]);

        return User::factory()->for($empresa)->create(['email' => 'ze@exemplo.test', 'password' => 'segredo123']);
    }

    public function test_situacao_conforme_a_data(): void
    {
        $situacao = fn (?string $data, bool $ativo = true) => Empresa::factory()->make(['acesso_ate' => $data, 'ativo' => $ativo])->situacaoAcesso();

        $this->assertSame('sem_prazo', $situacao(null));
        $this->assertSame('em_dia', $situacao('2026-11-21'));
        $this->assertSame('vencendo', $situacao('2026-11-20')); // 10 dias
        $this->assertSame('vencendo', $situacao('2026-11-10')); // vence hoje
        $this->assertSame('carencia', $situacao('2026-11-09'));
        $this->assertSame('carencia', $situacao('2026-11-05')); // 5º dia de carência
        $this->assertSame('vencida', $situacao('2026-11-04'));
        $this->assertSame('suspensa', $situacao('2027-01-01', false));
    }

    public function test_login_funciona_na_carencia_e_bloqueia_depois(): void
    {
        $this->donoComAcessoAte('2026-11-05');
        $this->postJson('/api/auth/login', ['email' => 'ze@exemplo.test', 'password' => 'segredo123'])
            ->assertOk()
            ->assertJsonPath('acesso.situacao', 'carencia')
            ->assertJsonPath('acesso.bloqueia_em', '2026-11-10');

        Empresa::query()->update(['acesso_ate' => '2026-11-04']);
        $this->postJson('/api/auth/login', ['email' => 'ze@exemplo.test', 'password' => 'segredo123'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'O acesso desta barbearia venceu em 04/11/2026. Para renovar, fale com a ABianchi Tech.');
    }

    public function test_sessao_aberta_e_encerrada_quando_o_prazo_acaba(): void
    {
        $dono = $this->donoComAcessoAte('2026-11-05');
        $token = $dono->createToken('teste')->plainTextToken;

        $this->withToken($token)->getJson('/api/agenda/dia')->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-11-11 08:00', 'America/Sao_Paulo'));
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/agenda/dia')
            ->assertForbidden()
            ->assertJsonPath('codigo', 'acesso_vencido');

        $this->assertSame(0, $dono->tokens()->count());
    }

    public function test_aviso_de_prazo_so_vai_para_o_dono(): void
    {
        $empresa = Empresa::factory()->create(['acesso_ate' => '2026-11-15']);

        $this->logarComo('dono', $empresa);
        $this->getJson('/api/auth/eu')
            ->assertJsonPath('acesso.situacao', 'vencendo')
            ->assertJsonPath('acesso.dias_restantes', 5);

        $this->logarComo('barbeiro', $empresa);
        $this->getJson('/api/auth/eu')->assertJsonPath('acesso', null);
    }

    public function test_barbearia_sem_prazo_continua_funcionando(): void
    {
        $this->donoComAcessoAte(null);

        $this->postJson('/api/auth/login', ['email' => 'ze@exemplo.test', 'password' => 'segredo123'])
            ->assertOk()
            ->assertJsonPath('acesso.situacao', 'sem_prazo');
    }

    public function test_pagina_publica_fica_no_ar_sem_agendamento_quando_vencida(): void
    {
        $empresa = Empresa::factory()->create(['slug' => 'barbearia-do-ze', 'acesso_ate' => '2026-11-01']);
        $this->naEmpresa($empresa, function () {
            $corte = Servico::create(['nome' => 'Corte', 'preco' => 50, 'duracao_minutos' => 30, 'ativo' => true]);
            $barbeiro = Funcionario::create(['nome' => 'Carlos', 'ativo' => true]);
            $barbeiro->servicos()->attach([$corte->id => []]);
        });

        $this->get('/barbearia-do-ze')
            ->assertOk()
            ->assertSee('O agendamento online não está disponível no momento.')
            ->assertDontSee('id="lista-servicos"', false);

        $this->getJson('/api/publico/barbearia-do-ze/horarios?data=2026-11-17')->assertStatus(423);
        $this->postJson('/api/publico/barbearia-do-ze/agendamentos', [])->assertStatus(423);

        $empresa->update(['acesso_ate' => '2026-12-31']);
        $this->get('/barbearia-do-ze')->assertOk()->assertSee('id="lista-servicos"', false);
    }

    public function test_avisos_por_email_nos_dias_combinados(): void
    {
        Notification::fake();

        $avisa = User::factory()->for(Empresa::factory()->state(['acesso_ate' => '2026-11-20']))->create(); // 10 dias
        $hoje = User::factory()->for(Empresa::factory()->state(['acesso_ate' => '2026-11-10']))->create(); // vence hoje
        $ontem = User::factory()->for(Empresa::factory()->state(['acesso_ate' => '2026-11-09']))->create(); // 1º dia de carência
        $naoAvisa = User::factory()->for(Empresa::factory()->state(['acesso_ate' => '2026-11-19']))->create(); // 9 dias
        $barbeiro = User::factory()->for($avisa->empresa)->perfil('barbeiro')->create();
        $semPrazo = User::factory()->create();

        $this->artisan('assinaturas:avisos')->expectsOutput('3 aviso(s) enviado(s).')->assertSuccessful();

        Notification::assertSentTo([$avisa, $hoje, $ontem], AvisoVencimentoAcesso::class);
        Notification::assertNotSentTo([$naoAvisa, $barbeiro, $semPrazo], AvisoVencimentoAcesso::class);
    }
}
