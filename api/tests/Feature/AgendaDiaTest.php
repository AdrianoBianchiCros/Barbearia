<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\BloqueioAgenda;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgendaDiaTest extends TestCase
{
    use RefreshDatabase;

    private function montarBarbearia(User $usuario): array
    {
        return $this->naEmpresa($usuario->empresa_id, function () use ($usuario) {
            $carlos = Funcionario::create(['nome' => 'Carlos', 'usuario_id' => $usuario->perfil === 'barbeiro' ? $usuario->id : null]);
            $rafael = Funcionario::create(['nome' => 'Rafael']);
            Funcionario::create(['nome' => 'Inativo', 'ativo' => false]);
            // 2030-01-15 é uma terça-feira (dia_semana = 2)
            $carlos->horariosTrabalho()->create(['dia_semana' => 2, 'inicio' => '09:00', 'fim' => '12:00']);
            $carlos->horariosTrabalho()->create(['dia_semana' => 3, 'inicio' => '10:00', 'fim' => '18:00']);
            $cliente = Cliente::create(['nome' => 'João', 'telefone' => '11988887777']);

            $ag = fn ($f, $ini, $fim, $status = 'confirmado') => Agendamento::create([
                'cliente_id' => $cliente->id,
                'funcionario_id' => $f->id,
                // horário de Brasília (UTC-3)
                'inicio' => Carbon::parse("2030-01-15 {$ini}", 'America/Sao_Paulo')->utc(),
                'fim' => Carbon::parse("2030-01-15 {$fim}", 'America/Sao_Paulo')->utc(),
                'status' => $status,
                'valor_total' => 45,
            ]);

            return [
                'carlos' => $carlos,
                'rafael' => $rafael,
                'cedo' => $ag($carlos, '09:00', '09:30'),
                'tarde' => $ag($carlos, '22:30', '23:00'), // ainda no dia 15 em Brasília, mas 16 em UTC
                'cancelado' => $ag($rafael, '10:00', '10:30', 'cancelado'),
            ];
        });
    }

    public function test_retorna_barbeiros_expediente_e_agendamentos_do_dia_no_fuso_da_barbearia(): void
    {
        $usuario = $this->logarComo('recepcao');
        $d = $this->montarBarbearia($usuario);

        $r = $this->getJson('/api/agenda/dia?data=2030-01-15')->assertOk();

        $r->assertJsonPath('data', '2030-01-15')
            ->assertJsonCount(2, 'barbeiros') // inativo fica de fora
            ->assertJsonPath('barbeiros.0.nome', 'Carlos')
            ->assertJsonPath('barbeiros.0.expediente', [['inicio' => '09:00', 'fim' => '12:00']])
            ->assertJsonCount(2, 'barbeiros.0.agendamentos')
            ->assertJsonPath('barbeiros.0.agendamentos.0.inicio', '2030-01-15T09:00:00-03:00')
            ->assertJsonPath('barbeiros.0.agendamentos.1.id', $d['tarde']->id)
            ->assertJsonPath('barbeiros.0.agendamentos.0.cliente.nome', 'João')
            ->assertJsonCount(0, 'barbeiros.1.agendamentos'); // cancelado não aparece
    }

    public function test_bloqueio_da_barbearia_aparece_para_todos_e_e_recortado_no_dia(): void
    {
        $usuario = $this->logarComo('dono');
        $d = $this->montarBarbearia($usuario);
        $this->naEmpresa($usuario->empresa_id, function () use ($d) {
            BloqueioAgenda::create(['inicio' => '2030-01-14 03:00:00', 'fim' => '2030-01-20 03:00:00', 'motivo' => 'Férias coletivas']);
            BloqueioAgenda::create(['funcionario_id' => $d['rafael']->id, 'inicio' => '2030-01-15 18:00:00', 'fim' => '2030-01-15 19:00:00']);
        });

        $this->getJson('/api/agenda/dia?data=2030-01-15')->assertOk()
            ->assertJsonCount(1, 'barbeiros.0.bloqueios')
            ->assertJsonPath('barbeiros.0.bloqueios.0.inicio', '2030-01-15T00:00:00-03:00')
            ->assertJsonPath('barbeiros.0.bloqueios.0.fim', '2030-01-16T00:00:00-03:00')
            ->assertJsonCount(2, 'barbeiros.1.bloqueios');
    }

    public function test_informa_o_barbeiro_do_usuario_logado(): void
    {
        $usuario = $this->logarComo('barbeiro');
        $d = $this->montarBarbearia($usuario);

        $this->getJson('/api/agenda/dia?data=2030-01-15')->assertOk()
            ->assertJsonPath('meu_funcionario_id', $d['carlos']->id);
    }

    public function test_altera_status_e_barbeiro_so_altera_os_proprios(): void
    {
        $barbeiro = $this->logarComo('barbeiro');
        $d = $this->montarBarbearia($barbeiro);
        $deOutro = $this->naEmpresa($barbeiro->empresa_id, fn () => Agendamento::create([
            'cliente_id' => $d['cedo']->cliente_id,
            'funcionario_id' => $d['rafael']->id,
            'inicio' => '2030-01-15 15:00:00',
            'fim' => '2030-01-15 15:30:00',
        ]));

        $this->patchJson("/api/agendamentos/{$d['cedo']->id}/status", ['status' => 'em_atendimento'])
            ->assertOk()->assertJsonPath('status', 'em_atendimento');
        $this->patchJson("/api/agendamentos/{$deOutro->id}/status", ['status' => 'concluido'])->assertForbidden();
        $this->patchJson("/api/agendamentos/{$d['cedo']->id}/status", ['status' => 'inventado'])
            ->assertUnprocessable();

        // Recepção altera qualquer um, e cancelar registra a data
        Sanctum::actingAs(User::factory()->for($barbeiro->empresa)->perfil('recepcao')->create());
        $this->patchJson("/api/agendamentos/{$deOutro->id}/status", ['status' => 'cancelado'])->assertOk();
        $this->assertNotNull($deOutro->fresh()->cancelado_em);
    }

    public function test_nao_ve_agenda_nem_altera_agendamento_de_outra_barbearia(): void
    {
        $outraEmpresa = Empresa::factory()->create();
        $outroDono = User::factory()->for($outraEmpresa)->create();
        $alheio = $this->montarBarbearia($outroDono)['cedo'];

        $this->logarComo('dono');

        $this->getJson('/api/agenda/dia?data=2030-01-15')->assertOk()->assertJsonCount(0, 'barbeiros');
        $this->patchJson("/api/agendamentos/{$alheio->id}/status", ['status' => 'cancelado'])->assertNotFound();
    }
}
