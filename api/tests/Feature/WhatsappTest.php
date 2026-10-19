<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\MensagemWhatsapp;
use App\Models\Servico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappTest extends TestCase
{
    use RefreshDatabase;

    /** 2030-01-15 é uma terça-feira (dia_semana = 2). */
    private const DIA = '2030-01-15';

    private Empresa $empresa;

    private Funcionario $carlos;

    private Servico $corte;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2030-01-13 12:00', 'America/Sao_Paulo'));
        config(['whatsapp.driver' => 'log', 'whatsapp.app_secret' => null, 'whatsapp.verify_token' => 'segredo-verificacao']);

        $this->empresa = Empresa::factory()->create([
            'nome' => 'Barbearia do Zé',
            'slug' => 'barbearia-do-ze',
            'wa_ativo' => true,
            'wa_phone_number_id' => '1234567890',
            'wa_token' => 'token-da-meta',
        ]);
        $this->naEmpresa($this->empresa, function () {
            $this->corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 45]);
            $this->carlos = Funcionario::create(['nome' => 'Carlos']);
            $this->carlos->servicos()->attach([$this->corte->id => []]);
            foreach ([0, 1, 2, 3, 4, 5, 6] as $dia) {
                $this->carlos->horariosTrabalho()->create(['dia_semana' => $dia, 'inicio' => '09:00', 'fim' => '18:00']);
            }
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function agendarPelaPagina(array $dados = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/publico/barbearia-do-ze/agendamentos', $dados + [
            'nome' => 'João Silva',
            'telefone' => '11988887777',
            'aceita_whatsapp' => true,
            'servico_ids' => [$this->corte->id],
            'funcionario_id' => $this->carlos->id,
            'data' => self::DIA,
            'hora' => '10:00',
        ]);
    }

    /** Executa algo "dentro" da barbearia (sem o escopo da requisição anterior). */
    private function na(callable $fn): mixed
    {
        return $this->naEmpresa($this->empresa, $fn);
    }

    private function agendamento(): Agendamento
    {
        return $this->na(fn () => Agendamento::latest('id')->firstOrFail());
    }

    // ---------- Confirmação ----------

    public function test_agendamento_com_consentimento_gera_confirmacao_enviada(): void
    {
        $this->agendarPelaPagina()->assertCreated();

        $m = $this->na(fn () => MensagemWhatsapp::sole());
        $this->assertSame('confirmacao', $m->tipo);
        $this->assertSame('enviado', $m->status);
        $this->assertStringStartsWith('teste.', $m->wamid);
        $this->assertSame('11988887777', $m->telefone);
        $this->assertSame('confirmacao_agendamento', $m->template);
        $this->assertSame(['João', 'Barbearia do Zé', 'terça, 15/01', '10:00', 'Carlos', 'Corte'], $m->parametros['corpo']);
        $this->assertSame($this->agendamento()->token, $m->parametros['botao']);
    }

    public function test_sem_consentimento_ou_whatsapp_desligado_nao_envia(): void
    {
        $this->agendarPelaPagina(['aceita_whatsapp' => false])->assertCreated();

        $this->empresa->update(['wa_ativo' => false]);
        $this->agendarPelaPagina(['telefone' => '11977776666', 'hora' => '11:00'])->assertCreated();

        $this->assertSame(0, $this->na(fn () => MensagemWhatsapp::count()));
    }

    public function test_cliente_que_pediu_para_parar_nao_recebe(): void
    {
        $this->na(function () {
            $c = Cliente::create(['nome' => 'João', 'telefone' => '11988887777']);
            $c->consentimentos()->create(['canal' => 'whatsapp', 'aceito' => true, 'origem' => 'recepcao', 'registrado_em' => now()->subDay()]);
            $c->consentimentos()->create(['canal' => 'whatsapp', 'aceito' => false, 'origem' => 'resposta_whatsapp', 'registrado_em' => now()]);
        });

        $this->agendarPelaPagina(['aceita_whatsapp' => false])->assertCreated();

        $this->assertSame(0, $this->na(fn () => MensagemWhatsapp::count()));
    }

    public function test_recepcao_agenda_e_remarca_gerando_mensagens(): void
    {
        $usuario = $this->logarComo('recepcao', $this->empresa);
        $id = $this->postJson('/api/agendamentos', [
            'novo_cliente' => ['nome' => 'Pedro', 'telefone' => '11955554444', 'aceita_whatsapp' => true],
            'funcionario_id' => $this->carlos->id,
            'servico_ids' => [$this->corte->id],
            'data' => self::DIA,
            'hora' => '09:00',
        ])->assertCreated()->json('id');

        $this->putJson("/api/agendamentos/{$id}", [
            'funcionario_id' => $this->carlos->id,
            'servico_ids' => [$this->corte->id],
            'data' => self::DIA,
            'hora' => '15:00',
        ])->assertOk();

        $this->assertSame(['confirmacao', 'remarcacao'], $this->na(fn () => MensagemWhatsapp::orderBy('id')->pluck('tipo')->all()));
        $this->assertNotNull($usuario);
    }

    // ---------- Lembretes ----------

    public function test_lembretes_de_24h_e_2h_saem_uma_vez_so(): void
    {
        $this->empresa->update(['wa_lembrete_2h' => true]);
        $this->agendarPelaPagina()->assertCreated(); // marcado 46 h antes (13/01 12:00 → 15/01 10:00)

        // Ainda faltam mais de 24 h: nada.
        $this->artisan('whatsapp:lembretes')->assertSuccessful();
        $this->assertSame(1, $this->na(fn () => MensagemWhatsapp::count()));

        Carbon::setTestNow(Carbon::parse('2030-01-14 10:30', 'America/Sao_Paulo'));
        $this->artisan('whatsapp:lembretes');
        $this->artisan('whatsapp:lembretes'); // segunda rodada não duplica

        $lembrete = $this->na(fn () => MensagemWhatsapp::where('tipo', 'lembrete_24h')->sole());
        $this->assertSame(['João', 'Barbearia do Zé', 'amanhã', '10:00', 'Carlos'], $lembrete->parametros['corpo']);
        $this->assertSame('lembrete_agendamento', $lembrete->template);

        Carbon::setTestNow(Carbon::parse('2030-01-15 08:30', 'America/Sao_Paulo'));
        $this->artisan('whatsapp:lembretes');
        $this->assertSame('hoje', $this->na(fn () => MensagemWhatsapp::where('tipo', 'lembrete_2h')->sole())->parametros['corpo'][2]);
    }

    public function test_quem_marcou_em_cima_da_hora_nao_recebe_lembrete_de_24h(): void
    {
        Carbon::setTestNow(Carbon::parse('2030-01-14 20:00', 'America/Sao_Paulo'));
        $this->agendarPelaPagina()->assertCreated(); // 14 h antes

        $this->artisan('whatsapp:lembretes');

        $this->assertSame(['confirmacao'], $this->na(fn () => MensagemWhatsapp::pluck('tipo')->all()));
    }

    public function test_lembrete_de_agendamento_cancelado_e_ignorado(): void
    {
        $this->agendarPelaPagina()->assertCreated();
        $a = $this->agendamento();
        $this->na(fn () => $a->update(['status' => 'cancelado']));

        Carbon::setTestNow(Carbon::parse('2030-01-14 10:30', 'America/Sao_Paulo'));
        $this->artisan('whatsapp:lembretes');

        $this->assertSame(0, $this->na(fn () => MensagemWhatsapp::where('tipo', 'lembrete_24h')->count()));
    }

    // ---------- Cloud API ----------

    public function test_envio_real_pela_cloud_api(): void
    {
        config(['whatsapp.driver' => 'cloud']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ABC']]])]);

        $this->agendarPelaPagina()->assertCreated();

        Http::assertSent(function ($req) {
            $corpo = $req->data();

            return str_ends_with($req->url(), '/1234567890/messages')
                && $req->hasHeader('Authorization', 'Bearer token-da-meta')
                && $corpo['to'] === '5511988887777'
                && $corpo['template']['name'] === 'confirmacao_agendamento'
                && $corpo['template']['language']['code'] === 'pt_BR'
                && $corpo['template']['components'][0]['parameters'][0]['text'] === 'João'
                && $corpo['template']['components'][1]['sub_type'] === 'url';
        });
        $m = $this->na(fn () => MensagemWhatsapp::sole());
        $this->assertSame('enviado', $m->status);
        $this->assertSame('wamid.ABC', $m->wamid);
    }

    public function test_erro_definitivo_da_meta_marca_como_falhou(): void
    {
        config(['whatsapp.driver' => 'cloud']);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template não existe']], 400)]);

        $this->agendarPelaPagina()->assertCreated();

        $m = $this->na(fn () => MensagemWhatsapp::sole());
        $this->assertSame('falhou', $m->status);
        $this->assertSame('Template não existe', $m->erro);
    }

    // ---------- Webhook ----------

    private function webhook(array $value, array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/webhooks/whatsapp', ['entry' => [['changes' => [[
            'value' => ['metadata' => ['phone_number_id' => '1234567890']] + $value,
        ]]]]], $headers);
    }

    public function test_verificacao_do_webhook(): void
    {
        $this->get('/api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=segredo-verificacao&hub.challenge=987')
            ->assertOk()->assertSeeText('987');
        $this->get('/api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=errado&hub.challenge=987')
            ->assertForbidden();
    }

    public function test_status_de_entrega_so_avanca_e_eventos_repetidos_sao_ignorados(): void
    {
        $this->agendarPelaPagina()->assertCreated();
        $wamid = $this->na(fn () => MensagemWhatsapp::sole()->wamid);

        $this->webhook(['statuses' => [['id' => $wamid, 'status' => 'read', 'timestamp' => '1894000000']]])->assertOk();
        $this->webhook(['statuses' => [['id' => $wamid, 'status' => 'delivered', 'timestamp' => '1893999000']]])->assertOk();
        $this->webhook(['statuses' => [['id' => $wamid, 'status' => 'read']]])->assertOk();

        $m = $this->na(fn () => MensagemWhatsapp::sole());
        $this->assertSame('lido', $m->status);
        $this->assertNotNull($m->entregue_em);
        $this->assertSame(1894000000, $m->lido_em->timestamp);
    }

    public function test_assinatura_do_webhook_e_conferida(): void
    {
        config(['whatsapp.app_secret' => 'segredo-do-app']);
        $corpo = json_encode(['entry' => []]);

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json'], $corpo)
            ->assertUnauthorized();
        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $corpo, 'segredo-do-app'),
        ], $corpo)->assertOk();
    }

    public function test_cliente_responde_sim_para_confirmar_e_parar_para_sair(): void
    {
        $this->agendarPelaPagina()->assertCreated();

        // Número sem o 9 (celular antigo no WhatsApp) também é reconhecido.
        $this->webhook(['messages' => [['id' => 'm1', 'from' => '551188887777', 'type' => 'text', 'text' => ['body' => 'Sim!']]]])->assertOk();
        $this->assertSame('confirmado', $this->agendamento()->status);
        $this->assertSame('Sim!', $this->na(fn () => MensagemWhatsapp::sole()->resposta));

        $this->webhook(['messages' => [['id' => 'm2', 'from' => '5511988887777', 'type' => 'text', 'text' => ['body' => 'parar']]]])->assertOk();
        $cliente = $this->na(fn () => Cliente::sole());
        $this->assertFalse($this->na(fn () => $cliente->consentimentoWhatsapp()->first()->aceito));
    }

    // ---------- Link do cliente ----------

    public function test_cliente_confirma_pelo_link(): void
    {
        $this->agendarPelaPagina()->assertCreated();
        $token = $this->agendamento()->token;

        $this->get("/a/{$token}")->assertOk()->assertSee('Confirmar presença')->assertSee('Carlos');
        $this->postJson("/api/a/{$token}/confirmar")->assertOk()->assertJsonPath('status', 'confirmado');
        $this->assertNotNull($this->agendamento()->confirmado_em);

        $this->get('/a/'.str_repeat('x', 32))->assertNotFound();
    }

    public function test_cliente_remarca_pelo_link_e_recebe_nova_confirmacao(): void
    {
        $this->agendarPelaPagina()->assertCreated();
        $token = $this->agendamento()->token;

        $horas = $this->getJson("/api/a/{$token}/horarios?data=2030-01-16")->assertOk()->json();
        $this->assertContains('14:00', $horas);

        $this->postJson("/api/a/{$token}/remarcar", ['data' => '2030-01-16', 'hora' => '14:00'])
            ->assertOk()->assertJsonPath('hora', '14:00');

        $a = $this->agendamento();
        $this->assertSame('2030-01-16 17:00', $a->inicio->utc()->format('Y-m-d H:i'));
        $this->assertSame('confirmado', $a->status);
        $this->assertSame(['confirmacao', 'remarcacao'], $this->na(fn () => MensagemWhatsapp::orderBy('id')->pluck('tipo')->all()));

        $this->postJson("/api/a/{$token}/remarcar", ['data' => '2030-01-16', 'hora' => '20:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('hora');
    }

    public function test_cancelamento_respeita_a_antecedencia_minima(): void
    {
        $this->empresa->update(['cancelamento_antecedencia_horas' => 3]);
        $this->agendarPelaPagina()->assertCreated();
        $token = $this->agendamento()->token;

        Carbon::setTestNow(Carbon::parse('2030-01-15 08:00', 'America/Sao_Paulo')); // faltam 2 h
        $this->postJson("/api/a/{$token}/cancelar")->assertUnprocessable();
        $this->get("/a/{$token}")->assertOk()->assertSee('só até 3 h antes');

        Carbon::setTestNow(Carbon::parse('2030-01-15 06:00', 'America/Sao_Paulo')); // faltam 4 h
        $this->postJson("/api/a/{$token}/cancelar")->assertOk();
        $this->assertSame('cancelado', $this->agendamento()->status);
        $this->postJson("/api/a/{$token}/confirmar")->assertUnprocessable();
    }

    // ---------- Configuração no painel ----------

    public function test_dono_configura_whatsapp_sem_expor_o_token(): void
    {
        $this->logarComo('dono', $this->empresa);

        $this->getJson('/api/whatsapp')->assertOk()
            ->assertJsonPath('modo', 'teste')
            ->assertJsonPath('wa_token_configurado', true)
            ->assertJsonMissingPath('wa_token');

        $this->putJson('/api/whatsapp', ['wa_lembrete_2h' => true, 'wa_token' => '', 'cancelamento_antecedencia_horas' => 4])
            ->assertOk()->assertJsonPath('wa_lembrete_2h', true);
        $this->assertSame('token-da-meta', $this->empresa->fresh()->wa_token);
        $this->assertNotSame('token-da-meta', \DB::table('empresas')->where('id', $this->empresa->id)->value('wa_token'));

        $this->getJson('/api/empresa')->assertOk()->assertJsonMissingPath('wa_token');

        $this->logarComo('recepcao', $this->empresa);
        $this->getJson('/api/whatsapp')->assertForbidden();
    }
}
