<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BuscaAgendamentosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2030-01-15 10:00', 'America/Sao_Paulo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Cria um agendamento no horário local de Brasília. */
    private function agendamento(Cliente $cliente, Funcionario $barbeiro, string $quando, string $status = 'confirmado', string $servico = 'Corte'): Agendamento
    {
        $inicio = Carbon::parse($quando, 'America/Sao_Paulo')->utc();
        $ag = Agendamento::create([
            'cliente_id' => $cliente->id,
            'funcionario_id' => $barbeiro->id,
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(30),
            'status' => $status,
        ]);
        $ag->itens()->forceCreate([
            'empresa_id' => $ag->empresa_id, 'servico_id' => 1, 'nome' => $servico, 'preco' => 45, 'duracao_minutos' => 30,
        ]);

        return $ag;
    }

    private function cenario(User $usuario): array
    {
        return $this->naEmpresa($usuario->empresa_id, function () {
            \App\Models\Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 45]);
            $carlos = Funcionario::create(['nome' => 'Carlos']);
            $joao = Cliente::create(['nome' => 'João Pereira', 'telefone' => '11988887777']);
            $ana = Cliente::create(['nome' => 'Ana Souza', 'telefone' => '11911112222']);

            return [
                'amanha' => $this->agendamento($joao, $carlos, '2030-01-16 14:00'),
                'hojeCedo' => $this->agendamento($joao, $carlos, '2030-01-15 08:00', 'concluido'), // hoje conta como próximo
                'passado' => $this->agendamento($joao, $carlos, '2030-01-10 09:00', 'concluido'),
                'antigo' => $this->agendamento($joao, $carlos, '2029-09-01 09:00', 'concluido'), // mais de 60 dias
                'cancelado' => $this->agendamento($joao, $carlos, '2030-01-17 09:00', 'cancelado'),
                'ana' => $this->agendamento($ana, $carlos, '2030-01-16 09:00', 'confirmado', 'Pigmentação'),
            ];
        });
    }

    public function test_busca_por_nome_separa_proximos_e_anteriores(): void
    {
        $d = $this->cenario($this->logarComo('recepcao'));

        $this->getJson('/api/agenda/busca?q=joão')->assertOk()
            ->assertJsonCount(2, 'proximos')
            ->assertJsonPath('proximos.0.id', $d['hojeCedo']->id)
            ->assertJsonPath('proximos.1.id', $d['amanha']->id)
            ->assertJsonPath('proximos.1.data', '2030-01-16')
            ->assertJsonPath('proximos.1.inicio', '2030-01-16T14:00:00-03:00')
            ->assertJsonPath('proximos.1.barbeiro.nome', 'Carlos')
            ->assertJsonCount(1, 'anteriores')
            ->assertJsonPath('anteriores.0.id', $d['passado']->id);
    }

    public function test_busca_por_telefone_e_por_servico(): void
    {
        $d = $this->cenario($this->logarComo('dono'));

        $this->getJson('/api/agenda/busca?q=(11) 91111')->assertOk()
            ->assertJsonCount(1, 'proximos')
            ->assertJsonPath('proximos.0.id', $d['ana']->id);

        $this->getJson('/api/agenda/busca?q=pigment')->assertOk()
            ->assertJsonPath('proximos.0.cliente.nome', 'Ana Souza');
    }

    public function test_exige_termo_e_nao_mostra_outra_barbearia(): void
    {
        $outro = User::factory()->for(Empresa::factory())->create();
        $this->cenario($outro);

        $this->logarComo('barbeiro');

        $this->getJson('/api/agenda/busca?q=j')->assertUnprocessable();
        $this->getJson('/api/agenda/busca?q=joão')->assertOk()
            ->assertJsonCount(0, 'proximos')
            ->assertJsonCount(0, 'anteriores');
    }
}
