<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Notifications\ConfirmacaoAgendamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** E-mail de confirmação do horário para o cliente que informa e-mail. */
class EmailClienteTest extends TestCase
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
        Carbon::setTestNow(Carbon::parse('2030-01-14 12:00', 'America/Sao_Paulo'));
        Notification::fake();

        $this->empresa = Empresa::factory()->create([
            'nome' => 'Barbearia do Zé',
            'slug' => 'barbearia-do-ze',
            'email' => 'contato@barbeariadoze.com.br',
            'logradouro' => 'Rua das Flores',
            'numero' => '10',
            'bairro' => 'Centro',
            'cidade' => 'São Paulo',
            'uf' => 'SP',
        ]);

        $this->naEmpresa($this->empresa, function () {
            $this->corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 45]);
            $this->carlos = Funcionario::create(['nome' => 'Carlos']);
            $this->carlos->servicos()->attach([$this->corte->id => ['preco' => 50]]);
            foreach ([2, 3] as $dia) {
                $this->carlos->horariosTrabalho()->create(['dia_semana' => $dia, 'inicio' => '09:00', 'fim' => '18:00']);
            }
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function agendar(array $dados = []): TestResponse
    {
        return $this->postJson('/api/publico/barbearia-do-ze/agendamentos', $dados + [
            'aceite_privacidade' => true,
            'maioridade' => true,
            'nome' => 'João Silva',
            'telefone' => '(11) 98888-7777',
            'servico_ids' => [$this->corte->id],
            'funcionario_id' => $this->carlos->id,
            'data' => self::DIA,
            'hora' => '09:00',
        ]);
    }

    private function agendamento(): Agendamento
    {
        return $this->naEmpresa($this->empresa, fn () => Agendamento::firstOrFail());
    }

    /** @return array<ConfirmacaoAgendamento> */
    private function enviadosPara(string $email): array
    {
        $enviados = [];
        Notification::assertSentOnDemand(ConfirmacaoAgendamento::class, function ($n, $canais, $notifiable) use ($email, &$enviados) {
            if ($notifiable->routes['mail'] === $email) {
                $enviados[] = $n;
            }

            return true;
        });

        return $enviados;
    }

    public function test_cliente_que_informa_email_recebe_a_confirmacao_mesmo_sem_whatsapp(): void
    {
        $this->agendar(['email' => 'joao@exemplo.com'])->assertCreated();

        $this->assertSame('joao@exemplo.com', $this->naEmpresa($this->empresa, fn () => Cliente::firstOrFail()->email));

        [$email] = $this->enviadosPara('joao@exemplo.com');
        $d = $email->dados;
        $this->assertSame('confirmacao', $d['tipo']);
        $this->assertSame('João', $d['nome']);
        $this->assertSame('terça, 15/01/2030', $d['data']);
        $this->assertSame('09:00', $d['hora']);
        $this->assertSame('Carlos', $d['barbeiro']);
        $this->assertSame('Corte', $d['servicos']);
        $this->assertSame('50,00', $d['valor']);
        $this->assertSame('Rua das Flores, 10 · Centro · São Paulo - SP', $d['endereco']);
        $this->assertStringEndsWith('/a/'.$this->agendamento()->token, $d['link']);

        $mail = $email->toMail(new AnonymousNotifiable);
        $this->assertSame('Horário confirmado — terça, 15/01/2030 às 09:00', $mail->subject);
        $this->assertSame('Barbearia do Zé', $mail->from[1]);
        $this->assertSame('contato@barbeariadoze.com.br', $mail->replyTo[0][0]);
        $this->assertSame('Confirmar, remarcar ou cancelar', $mail->actionText);
    }

    public function test_sem_email_nada_e_enviado_e_email_invalido_e_recusado(): void
    {
        $this->agendar()->assertCreated();
        Notification::assertNothingSent();

        $this->agendar(['email' => 'joao@', 'data' => '2030-01-22'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Informe um e-mail válido']);
    }

    public function test_remarcar_pelo_link_manda_email_de_remarcacao(): void
    {
        $this->agendar(['email' => 'joao@exemplo.com'])->assertCreated();
        $token = $this->agendamento()->token;

        $this->postJson("/api/a/{$token}/remarcar", ['data' => '2030-01-16', 'hora' => '14:00'])->assertOk();

        $tipos = array_map(fn ($n) => $n->dados['tipo'], $this->enviadosPara('joao@exemplo.com'));
        $this->assertSame(['confirmacao', 'remarcacao'], $tipos);
    }

    public function test_recepcao_cadastra_email_e_cliente_recebe_confirmacao_e_remarcacao(): void
    {
        $this->logarComo('recepcao', $this->empresa);
        $id = $this->postJson('/api/agendamentos', [
            'novo_cliente' => ['nome' => 'Pedro', 'telefone' => '11955554444', 'email' => 'pedro@exemplo.com'],
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

        $tipos = array_map(fn ($n) => $n->dados['tipo'], $this->enviadosPara('pedro@exemplo.com'));
        $this->assertSame(['confirmacao', 'remarcacao'], $tipos);
    }

    public function test_botao_da_agenda_envia_a_confirmacao_e_grava_o_email_informado(): void
    {
        $this->agendar()->assertCreated(); // cliente sem e-mail
        $id = $this->agendamento()->id;
        $this->logarComo('recepcao', $this->empresa);

        $this->postJson("/api/agendamentos/{$id}/enviar-confirmacao")
            ->assertUnprocessable()->assertJsonValidationErrors(['email' => 'Informe o e-mail do cliente.']);
        Notification::assertNothingSent();

        $this->postJson("/api/agendamentos/{$id}/enviar-confirmacao", ['email' => 'joao@exemplo.com'])
            ->assertOk()->assertJsonPath('email', 'joao@exemplo.com');
        $this->assertSame('joao@exemplo.com', $this->naEmpresa($this->empresa, fn () => Cliente::firstOrFail()->email));

        // Com o e-mail já no cadastro, basta clicar de novo.
        $this->postJson("/api/agendamentos/{$id}/enviar-confirmacao")->assertOk();
        $this->assertCount(2, $this->enviadosPara('joao@exemplo.com'));

        $this->getJson('/api/agenda/dia?data='.self::DIA)->assertOk()
            ->assertJsonFragment(['email' => 'joao@exemplo.com']);
    }

    public function test_botao_da_agenda_recusa_horario_cancelado_e_barbeiro(): void
    {
        $this->agendar(['email' => 'joao@exemplo.com'])->assertCreated();
        $agendamento = $this->agendamento();

        $this->logarComo('barbeiro', $this->empresa);
        $this->postJson("/api/agendamentos/{$agendamento->id}/enviar-confirmacao")->assertForbidden();

        $this->naEmpresa($this->empresa, fn () => $agendamento->update(['status' => 'cancelado']));
        $this->logarComo('recepcao', $this->empresa);
        $this->postJson("/api/agendamentos/{$agendamento->id}/enviar-confirmacao")->assertUnprocessable();
        $this->assertCount(1, $this->enviadosPara('joao@exemplo.com')); // só a do agendamento
    }
}
