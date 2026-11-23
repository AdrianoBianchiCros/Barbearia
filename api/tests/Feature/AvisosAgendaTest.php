<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Sino da agenda: cancelamentos e remarcações, venham do painel ou do cliente. */
class AvisosAgendaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $dono;

    private User $recepcao;

    private User $usuarioCarlos;

    private User $usuarioRafael;

    private Funcionario $carlos;

    private Funcionario $rafael;

    private Agendamento $agendamento;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2030-01-14 12:00', 'America/Sao_Paulo'));

        $this->empresa = Empresa::factory()->create();
        $this->usuarioCarlos = $this->logarComo('barbeiro', $this->empresa);
        $this->usuarioRafael = $this->logarComo('barbeiro', $this->empresa);
        $this->recepcao = $this->logarComo('recepcao', $this->empresa);
        $this->dono = $this->logarComo('dono', $this->empresa);

        $this->naEmpresa($this->empresa, function () {
            $corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 45]);
            $this->carlos = Funcionario::create(['nome' => 'Carlos', 'usuario_id' => $this->usuarioCarlos->id]);
            $this->rafael = Funcionario::create(['nome' => 'Rafael', 'usuario_id' => $this->usuarioRafael->id]);
            foreach ([$this->carlos, $this->rafael] as $f) {
                $f->servicos()->attach($corte->id);
                $f->horariosTrabalho()->create(['dia_semana' => 2, 'inicio' => '09:00', 'fim' => '18:00']);
            }
            $cliente = Cliente::create(['nome' => 'João Silva', 'telefone' => '11988887777']);
            $inicio = Carbon::parse('2030-01-15 09:00', 'America/Sao_Paulo')->utc();
            $this->agendamento = Agendamento::create([
                'cliente_id' => $cliente->id,
                'funcionario_id' => $this->carlos->id,
                'inicio' => $inicio,
                'fim' => $inicio->copy()->addMinutes(30),
                'status' => 'agendado',
                'origem' => 'link_publico',
                'valor_total' => 45,
            ]);
            $this->agendamento->itens()->create(['servico_id' => $corte->id, 'nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 45]);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function avisosDe(User $usuario): TestResponse
    {
        Sanctum::actingAs($usuario);

        return $this->getJson('/api/avisos')->assertOk();
    }

    public function test_cancelamento_pelo_cliente_avisa_dono_recepcao_e_o_barbeiro_do_horario(): void
    {
        $this->postJson("/api/a/{$this->agendamento->token}/cancelar")->assertOk();

        foreach ([$this->dono, $this->recepcao, $this->usuarioCarlos] as $usuario) {
            $this->avisosDe($usuario)
                ->assertJsonPath('nao_vistos', 1)
                ->assertJsonPath('avisos.0.tipo', 'cancelamento')
                ->assertJsonPath('avisos.0.origem', 'cliente')
                ->assertJsonPath('avisos.0.cliente', 'João Silva')
                ->assertJsonPath('avisos.0.barbeiro', 'Carlos')
                ->assertJsonPath('avisos.0.inicio', '2030-01-15T09:00:00-03:00')
                ->assertJsonPath('avisos.0.visto', false);
        }

        // Outro barbeiro não vê a agenda do colega.
        $this->avisosDe($this->usuarioRafael)->assertJsonPath('nao_vistos', 0)->assertJsonCount(0, 'avisos');
    }

    public function test_fica_marcado_ate_o_usuario_abrir_o_sino(): void
    {
        $this->postJson("/api/a/{$this->agendamento->token}/remarcar", ['data' => '2030-01-15', 'hora' => '14:00'])->assertOk();

        $this->avisosDe($this->dono)
            ->assertJsonPath('nao_vistos', 1)
            ->assertJsonPath('avisos.0.tipo', 'remarcacao')
            ->assertJsonPath('avisos.0.inicio_anterior', '2030-01-15T09:00:00-03:00')
            ->assertJsonPath('avisos.0.inicio', '2030-01-15T14:00:00-03:00');

        $this->postJson('/api/avisos/vistos')->assertOk();
        $this->avisosDe($this->dono)->assertJsonPath('nao_vistos', 0)->assertJsonPath('avisos.0.visto', true);

        // Cada usuário tem o seu "visto".
        $this->avisosDe($this->recepcao)->assertJsonPath('nao_vistos', 1);
    }

    public function test_alteracoes_pelo_painel_avisam_os_outros_mas_nao_quem_fez(): void
    {
        Sanctum::actingAs($this->recepcao);
        $this->putJson("/api/agendamentos/{$this->agendamento->id}", [
            'funcionario_id' => $this->rafael->id,
            'servico_ids' => [$this->agendamento->itens()->value('servico_id')],
            'data' => '2030-01-15',
            'hora' => '10:00',
        ])->assertOk();

        $this->avisosDe($this->recepcao)->assertJsonPath('nao_vistos', 0)->assertJsonCount(0, 'avisos');
        $this->avisosDe($this->dono)
            ->assertJsonPath('nao_vistos', 1)
            ->assertJsonPath('avisos.0.origem', 'painel')
            ->assertJsonPath('avisos.0.barbeiro_anterior', 'Carlos')
            ->assertJsonPath('avisos.0.barbeiro', 'Rafael');
        // Trocou de barbeiro: os dois são avisados.
        $this->avisosDe($this->usuarioCarlos)->assertJsonPath('nao_vistos', 1);
        $this->avisosDe($this->usuarioRafael)->assertJsonPath('nao_vistos', 1);

        // Confirmar presença não gera aviso; cancelar gera.
        Sanctum::actingAs($this->dono);
        $this->patchJson("/api/agendamentos/{$this->agendamento->id}/status", ['status' => 'confirmado'])->assertOk();
        $this->patchJson("/api/agendamentos/{$this->agendamento->id}/status", ['status' => 'cancelado'])->assertOk();
        $this->patchJson("/api/agendamentos/{$this->agendamento->id}/status", ['status' => 'cancelado'])->assertOk();

        $this->avisosDe($this->recepcao)
            ->assertJsonPath('nao_vistos', 1)
            ->assertJsonPath('avisos.0.tipo', 'cancelamento')
            ->assertJsonCount(1, 'avisos');
    }

    public function test_avisos_nao_passam_de_uma_barbearia_para_outra(): void
    {
        $this->postJson("/api/a/{$this->agendamento->token}/cancelar")->assertOk();

        $this->avisosDe($this->logarComo('dono'))->assertJsonPath('nao_vistos', 0)->assertJsonCount(0, 'avisos');
    }
}
