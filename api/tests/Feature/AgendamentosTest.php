<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\BloqueioAgenda;
use App\Models\Cliente;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgendamentosTest extends TestCase
{
    use RefreshDatabase;

    /** 2030-01-15 é uma terça-feira (dia_semana = 2). */
    private const DIA = '2030-01-15';

    private User $usuario;

    private Funcionario $carlos;

    private Funcionario $rafael;

    private Servico $corte;

    private Servico $barba;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2030-01-14 12:00', 'America/Sao_Paulo'));

        $this->usuario = $this->logarComo('recepcao');
        $this->naEmpresa($this->usuario->empresa_id, function () {
            $this->corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 45]);
            $this->barba = Servico::create(['nome' => 'Barba', 'duracao_minutos' => 30, 'preco' => 35]);
            $this->carlos = Funcionario::create(['nome' => 'Carlos']);
            $this->rafael = Funcionario::create(['nome' => 'Rafael']);
            $this->carlos->servicos()->attach([
                $this->corte->id => ['preco' => 50, 'duracao_minutos' => 45],
                $this->barba->id => [],
            ]);
            $this->rafael->servicos()->attach([$this->corte->id => []]);
            foreach ([$this->carlos, $this->rafael] as $f) {
                $f->horariosTrabalho()->create(['dia_semana' => 2, 'inicio' => '09:00', 'fim' => '12:00']);
                $f->horariosTrabalho()->create(['dia_semana' => 2, 'inicio' => '13:00', 'fim' => '14:00']);
            }
            $this->cliente = Cliente::create(['nome' => 'João', 'telefone' => '11988887777']);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function agendar(array $dados = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/agendamentos', $dados + [
            'cliente_id' => $this->cliente->id,
            'funcionario_id' => $this->carlos->id,
            'servico_ids' => [$this->corte->id],
            'data' => self::DIA,
            'hora' => '09:00',
        ]);
    }

    private function horarios(array $servicos, ?int $funcionario = null): array
    {
        $query = http_build_query(array_filter([
            'data' => self::DIA,
            'servico_ids' => $servicos,
            'funcionario_id' => $funcionario,
        ]));

        return $this->getJson("/api/agenda/horarios?{$query}")->assertOk()->json();
    }

    public function test_lista_horarios_livres_com_duracao_e_preco_do_barbeiro(): void
    {
        $r = $this->horarios([$this->corte->id, $this->barba->id]);

        // Rafael não faz barba: só Carlos aparece
        $this->assertCount(1, $r);
        $this->assertSame($this->carlos->id, $r[0]['funcionario_id']);
        $this->assertSame(75, $r[0]['duracao_minutos']); // 45 (próprio) + 30
        $this->assertSame('85.00', $r[0]['valor_total']); // 50 (próprio) + 35
        // 75 min cabem até 10:45 na manhã; à tarde (1h) não cabem
        $this->assertSame('09:00', $r[0]['horarios'][0]);
        $this->assertSame('10:45', end($r[0]['horarios']));
        $this->assertNotContains('13:00', $r[0]['horarios']);
    }

    public function test_horarios_descontam_agendamentos_e_bloqueios(): void
    {
        $this->agendar(['hora' => '09:30'])->assertCreated(); // Carlos 09:30–10:15
        $this->naEmpresa($this->usuario->empresa_id, fn () => BloqueioAgenda::create([
            'funcionario_id' => $this->carlos->id,
            'inicio' => Carbon::parse(self::DIA.' 11:00', 'America/Sao_Paulo')->utc(),
            'fim' => Carbon::parse(self::DIA.' 12:00', 'America/Sao_Paulo')->utc(),
        ]));

        $livres = $this->horarios([$this->barba->id], $this->carlos->id)[0]['horarios'];

        $this->assertSame(['09:00', '10:15', '10:30', '13:00', '13:15', '13:30'], $livres);
    }

    public function test_cria_agendamento_com_itens_e_valor(): void
    {
        $r = $this->agendar(['servico_ids' => [$this->corte->id, $this->barba->id], 'observacoes' => 'Degradê'])
            ->assertCreated()
            ->assertJsonPath('inicio', self::DIA.'T09:00:00-03:00')
            ->assertJsonPath('fim', self::DIA.'T10:15:00-03:00')
            ->assertJsonPath('valor_total', '85.00')
            ->assertJsonPath('status', 'confirmado')
            ->assertJsonPath('servicos', ['Corte', 'Barba']);

        $this->assertDatabaseCount('agendamento_itens', 2);
        $this->assertDatabaseHas('agendamentos', ['id' => $r->json('id'), 'inicio' => self::DIA.' 12:00:00']); // UTC
    }

    public function test_recusa_conflito_fora_do_expediente_bloqueio_e_passado(): void
    {
        $this->agendar()->assertCreated(); // 09:00–09:45

        $this->agendar(['hora' => '09:30'])->assertUnprocessable()->assertJsonValidationErrors(['hora']);
        $this->agendar(['hora' => '11:45'])->assertUnprocessable(); // passaria das 12:00
        $this->agendar(['hora' => '07:00'])->assertUnprocessable();
        $this->agendar(['data' => '2030-01-13'])->assertUnprocessable(); // domingo e passado

        $this->assertDatabaseCount('agendamentos', 1);
    }

    public function test_encaixe_ignora_expediente_mas_nunca_sobrepoe_cliente(): void
    {
        $this->agendar()->assertCreated(); // 09:00–09:45

        $this->agendar(['hora' => '12:15', 'encaixe' => true])->assertCreated();
        $this->agendar(['hora' => '09:15', 'encaixe' => true])->assertUnprocessable();
    }

    public function test_encaixe_sugere_primeiro_horario_sem_cliente(): void
    {
        // Carlos ocupado das 09:00 às 09:45; Rafael das 09:00 às 09:30 (dia seguinte ao "agora")
        $this->agendar()->assertCreated();
        $this->agendar(['funcionario_id' => $this->rafael->id])->assertCreated();

        $query = fn (array $extra = []) => '/api/agenda/encaixe?'.http_build_query($extra + [
            'data' => self::DIA, 'servico_ids' => [$this->corte->id],
        ]);

        // Qualquer barbeiro: Rafael fica livre antes (09:30)
        $this->getJson($query())->assertOk()
            ->assertJsonPath('funcionario_id', $this->rafael->id)
            ->assertJsonPath('hora', '09:30')
            ->assertJsonPath('duracao_minutos', 30);

        // Barbeiro escolhido
        $this->getJson($query(['funcionario_id' => $this->carlos->id]))->assertOk()
            ->assertJsonPath('hora', '09:45')
            ->assertJsonPath('valor_total', '50.00');

        // Hoje (14/01, 12:00): a partir de agora, mesmo fora do expediente
        $this->getJson($query(['data' => '2030-01-14', 'funcionario_id' => $this->carlos->id]))->assertOk()
            ->assertJsonPath('hora', '12:00');

        // De madrugada, hoje começa no início do expediente, não na hora atual
        Carbon::setTestNow(Carbon::parse('2030-01-15 00:40', 'America/Sao_Paulo'));
        $this->getJson($query(['funcionario_id' => $this->rafael->id]))->assertOk()
            ->assertJsonPath('hora', '09:30');
        Carbon::setTestNow(Carbon::parse('2030-01-14 12:00', 'America/Sao_Paulo'));

        // Dia que já passou
        $this->getJson($query(['data' => '2030-01-13']))->assertUnprocessable();
    }

    public function test_barbeiro_que_nao_faz_o_servico_e_recusado(): void
    {
        $this->agendar(['funcionario_id' => $this->rafael->id, 'servico_ids' => [$this->barba->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['servico_ids']);
    }

    public function test_cria_cliente_novo_ou_reaproveita_pelo_telefone(): void
    {
        $this->agendar(['cliente_id' => null, 'novo_cliente' => [
            'nome' => 'Pedro', 'telefone' => '(11) 97777-6666', 'aceita_whatsapp' => true,
        ]])->assertCreated()->assertJsonPath('cliente.nome', 'Pedro');

        $this->agendar(['cliente_id' => null, 'hora' => '10:00', 'novo_cliente' => [
            'nome' => 'Pedro Outro Nome', 'telefone' => '11977776666',
        ]])->assertCreated()->assertJsonPath('cliente.nome', 'Pedro');

        $this->assertDatabaseCount('clientes', 2);
        $this->assertDatabaseHas('consentimentos', ['aceito' => true, 'origem' => 'recepcao']);
    }

    public function test_remarca_para_outro_horario_e_barbeiro(): void
    {
        $id = $this->agendar()->json('id');
        $this->agendar(['hora' => '10:00', 'funcionario_id' => $this->rafael->id]); // Rafael 10:00–10:30

        // Mantém o próprio horário livre para si mesmo (pode "remarcar" sem mudar)
        $this->putJson("/api/agendamentos/{$id}", [
            'funcionario_id' => $this->carlos->id, 'servico_ids' => [$this->corte->id], 'data' => self::DIA, 'hora' => '09:15',
        ])->assertOk()->assertJsonPath('inicio', self::DIA.'T09:15:00-03:00');

        // Conflito com o horário do Rafael
        $this->putJson("/api/agendamentos/{$id}", [
            'funcionario_id' => $this->rafael->id, 'servico_ids' => [$this->corte->id], 'data' => self::DIA, 'hora' => '10:15',
        ])->assertUnprocessable();

        $this->putJson("/api/agendamentos/{$id}", [
            'funcionario_id' => $this->rafael->id, 'servico_ids' => [$this->corte->id], 'data' => self::DIA, 'hora' => '13:00',
        ])->assertOk()
            ->assertJsonPath('funcionario_id', $this->rafael->id)
            ->assertJsonPath('valor_total', '45.00'); // preço padrão do Rafael

        $this->assertDatabaseCount('agendamento_itens', 2);
    }

    public function test_nao_remarca_atendimento_concluido(): void
    {
        $id = $this->agendar()->json('id');
        Agendamento::whereKey($id)->update(['status' => 'concluido']);

        $this->putJson("/api/agendamentos/{$id}", [
            'funcionario_id' => $this->carlos->id, 'servico_ids' => [$this->corte->id], 'data' => self::DIA, 'hora' => '10:00',
        ])->assertUnprocessable();
    }

    public function test_cancelado_libera_o_horario(): void
    {
        $id = $this->agendar()->json('id');
        $this->patchJson("/api/agendamentos/{$id}/status", ['status' => 'cancelado'])->assertOk();

        $this->agendar()->assertCreated();
    }

    public function test_barbeiro_so_agenda_na_propria_coluna(): void
    {
        $barbeiro = User::factory()->for($this->usuario->empresa)->perfil('barbeiro')->create();
        $this->carlos->update(['usuario_id' => $barbeiro->id]);
        Sanctum::actingAs($barbeiro);

        $this->agendar()->assertCreated();
        $this->agendar(['funcionario_id' => $this->rafael->id])->assertForbidden();
        // Barbeiro não faz encaixe fora do expediente
        $this->agendar(['hora' => '12:15', 'encaixe' => true])->assertUnprocessable();
    }
}
