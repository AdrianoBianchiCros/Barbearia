<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Caixa;
use App\Models\Cliente;
use App\Models\Comanda;
use App\Models\Funcionario;
use App\Models\Lancamento;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RelatoriosTest extends TestCase
{
    use RefreshDatabase;

    private User $dono;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2030-01-20 12:00', 'America/Sao_Paulo'));
        $this->dono = $this->logarComo('dono');

        $this->naEmpresa($this->dono->empresa_id, function () {
            $local = fn (string $quando) => Carbon::parse($quando, 'America/Sao_Paulo')->utc();
            $corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 50]);
            $carlos = Funcionario::create(['nome' => 'Carlos', 'comissao_percentual' => 50]);
            $carlos->horariosTrabalho()->create(['dia_semana' => 2, 'inicio' => '09:00', 'fim' => '12:00']); // terças

            $cliente = fn ($nome, $tel) => Cliente::create(['nome' => $nome, 'telefone' => $tel]);
            [$joao, $ana, $pedro, $maria] = [
                $cliente('João', '11900000001'), $cliente('Ana', '11900000002'),
                $cliente('Pedro', '11900000003'), $cliente('Maria', '11900000004'),
            ];
            $agendar = fn ($c, $quando, $status) => Agendamento::create([
                'cliente_id' => $c->id, 'funcionario_id' => $carlos->id, 'status' => $status,
                'inicio' => $local($quando), 'fim' => $local($quando)->addMinutes(30), 'valor_total' => 50,
            ]);

            $atendido = $agendar($joao, '2030-01-15 10:00', 'concluido'); // terça
            $agendar($ana, '2030-01-16 10:00', 'faltou');
            $agendar($ana, '2030-01-17 10:00', 'cancelado');
            $agendar($pedro, '2029-11-01 10:00', 'concluido');  // sumido
            $agendar($maria, '2029-11-01 11:00', 'concluido');
            $agendar($maria, '2030-01-25 10:00', 'confirmado'); // volta em breve: não é sumida

            $caixa = Caixa::create(['aberto_por' => $this->dono->id, 'aberto_em' => $local('2030-01-15 08:00'), 'valor_inicial' => 0]);
            $comanda = Comanda::create([
                'agendamento_id' => $atendido->id, 'cliente_id' => $joao->id, 'funcionario_id' => $carlos->id,
                'status' => 'paga', 'subtotal' => 70, 'desconto' => 0, 'total' => 70,
                'caixa_id' => $caixa->id, 'pago_em' => $local('2030-01-15 10:40'),
            ]);
            $comanda->itens()->create([
                'tipo' => 'servico', 'servico_id' => $corte->id, 'funcionario_id' => $carlos->id, 'descricao' => 'Corte',
                'quantidade' => 1, 'preco_unitario' => 50, 'total' => 50, 'comissao_percentual' => 50, 'comissao_valor' => 25,
            ]);
            $comanda->itens()->create([
                'tipo' => 'produto', 'descricao' => 'Pomada', 'quantidade' => 2, 'preco_unitario' => 10, 'total' => 20,
            ]);
            $comanda->pagamentos()->create(['caixa_id' => $caixa->id, 'forma' => 'pix', 'valor' => 70]);

            Lancamento::create([
                'tipo' => 'despesa', 'descricao' => 'Luz', 'categoria' => 'contas', 'valor' => 30,
                'vencimento' => '2030-01-10', 'pago_em' => $local('2030-01-10 09:00'), 'usuario_id' => $this->dono->id,
            ]);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_resumo_do_periodo(): void
    {
        $r = $this->getJson('/api/relatorios/resumo?inicio=2030-01-01&fim=2030-01-20')->assertOk();

        $r->assertJsonPath('indicadores.faturamento', 70)
            ->assertJsonPath('indicadores.comandas_pagas', 1)
            ->assertJsonPath('indicadores.ticket_medio', 70)
            ->assertJsonPath('indicadores.faturamento_servicos', 50)
            ->assertJsonPath('indicadores.faturamento_produtos', 20)
            ->assertJsonPath('indicadores.agendamentos', 2)       // cancelado não conta
            ->assertJsonPath('indicadores.cancelados', 1)
            ->assertJsonPath('indicadores.taxa_faltas', 50)       // 1 falta em 2
            ->assertJsonPath('indicadores.comissoes', 25)
            ->assertJsonPath('indicadores.despesas_pagas', 30)
            ->assertJsonPath('indicadores.resultado', 15)         // 70 − 25 − 30
            ->assertJsonPath('anterior.faturamento', 0)
            ->assertJsonPath('periodo_anterior.inicio', '2029-12-12')
            ->assertJsonPath('formas_pagamento.0', ['forma' => 'pix', 'total' => 70])
            ->assertJsonPath('serie.agrupamento', 'dia')
            ->assertJsonCount(20, 'serie.pontos')
            ->assertJsonPath('serie.pontos.14', ['data' => '2030-01-15', 'faturamento' => 70, 'atendimentos' => 1])
            ->assertJsonPath('servicos.0', ['nome' => 'Corte', 'quantidade' => 1, 'receita' => 50])
            ->assertJsonPath('produtos.0.quantidade', 2)
            ->assertJsonCount(2, 'horarios_pico');

        // Ocupação: 30 min atendidos ÷ (3 terças × 180 min) = 5,6%
        $r->assertJsonPath('barbeiros.0.nome', 'Carlos')
            ->assertJsonPath('barbeiros.0.concluidos', 1)
            ->assertJsonPath('barbeiros.0.faltas', 1)
            ->assertJsonPath('barbeiros.0.ocupacao', 5.6)
            ->assertJsonPath('barbeiros.0.faturamento', 50);
    }

    public function test_periodo_longo_agrupa_por_mes_e_limite_de_um_ano(): void
    {
        $this->getJson('/api/relatorios/resumo?inicio=2029-10-01&fim=2030-01-20')->assertOk()
            ->assertJsonPath('serie.agrupamento', 'mes')
            ->assertJsonCount(4, 'serie.pontos');

        $this->getJson('/api/relatorios/resumo?inicio=2028-01-01&fim=2030-01-20')->assertUnprocessable();
    }

    public function test_clientes_novos_e_que_sumiram(): void
    {
        $this->getJson('/api/relatorios/clientes?inicio=2030-01-01&fim=2030-01-20&dias_sem_voltar=45')->assertOk()
            ->assertJsonPath('atendidos', 1)
            ->assertJsonPath('novos', 1)
            ->assertJsonCount(1, 'sumidos')
            ->assertJsonPath('sumidos.0.nome', 'Pedro')
            ->assertJsonPath('sumidos.0.ultima_visita', '2029-11-01')
            ->assertJsonPath('sumidos.0.ultimo_barbeiro', 'Carlos');
    }

    public function test_somente_dono_ve_relatorios(): void
    {
        Sanctum::actingAs(User::factory()->for($this->dono->empresa)->perfil('recepcao')->create());

        $this->getJson('/api/relatorios/resumo')->assertForbidden();
        $this->getJson('/api/relatorios/clientes')->assertForbidden();
    }
}
