<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Funcionario;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceiroTest extends TestCase
{
    use RefreshDatabase;

    private User $dono;

    private Funcionario $carlos;

    private Servico $corte;

    private Produto $pomada;

    private Agendamento $agendamento;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2030-01-15 15:00', 'America/Sao_Paulo'));

        $this->dono = $this->logarComo('dono');
        $this->naEmpresa($this->dono->empresa_id, function () {
            $this->corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 50]);
            $this->pomada = Produto::create(['nome' => 'Pomada', 'preco' => 40, 'estoque' => 10, 'comissao_percentual' => 10]);
            $this->carlos = Funcionario::create(['nome' => 'Carlos', 'comissao_percentual' => 50]);
            $cliente = Cliente::create(['nome' => 'João', 'telefone' => '11988887777']);
            $this->agendamento = Agendamento::create([
                'cliente_id' => $cliente->id,
                'funcionario_id' => $this->carlos->id,
                'inicio' => Carbon::parse('2030-01-15 14:00', 'America/Sao_Paulo')->utc(),
                'fim' => Carbon::parse('2030-01-15 14:30', 'America/Sao_Paulo')->utc(),
                'status' => 'em_atendimento',
                'valor_total' => 50,
            ]);
            $this->agendamento->itens()->create(['servico_id' => $this->corte->id, 'nome' => 'Corte', 'preco' => 50, 'duracao_minutos' => 30]);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function abrirCaixa(float $inicial = 100): void
    {
        $this->postJson('/api/caixa/abrir', ['valor_inicial' => $inicial])->assertCreated();
    }

    private function fecharComanda(int $comandaId, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/comandas/{$comandaId}/fechar", $extra + [
            'itens' => [
                ['tipo' => 'servico', 'servico_id' => $this->corte->id, 'funcionario_id' => $this->carlos->id, 'quantidade' => 1, 'preco_unitario' => 50],
                ['tipo' => 'produto', 'produto_id' => $this->pomada->id, 'funcionario_id' => $this->carlos->id, 'quantidade' => 2, 'preco_unitario' => 40],
            ],
            'desconto' => 10,
            'pagamentos' => [['forma' => 'pix', 'valor' => 70], ['forma' => 'dinheiro', 'valor' => 50]],
        ]);
    }

    public function test_concluir_atendimento_abre_comanda_e_reabrir_descarta(): void
    {
        $this->patchJson("/api/agendamentos/{$this->agendamento->id}/status", ['status' => 'concluido'])
            ->assertOk()
            ->assertJsonPath('comanda.status', 'aberta')
            ->assertJsonPath('comanda.total', '50.00');

        $this->getJson('/api/comandas?status=aberta')->assertOk()->assertJsonCount(1)->assertJsonPath('0.cliente.nome', 'João');
        $this->getJson('/api/agenda/dia?data=2030-01-15')->assertJsonPath('barbeiros.0.agendamentos.0.comanda.status', 'aberta');

        $this->patchJson("/api/agendamentos/{$this->agendamento->id}/status", ['status' => 'em_atendimento'])->assertOk();
        $this->assertDatabaseCount('comandas', 0);
    }

    public function test_fechar_comanda_registra_pagamentos_comissao_e_estoque(): void
    {
        $comanda = $this->postJson("/api/agendamentos/{$this->agendamento->id}/comanda")->assertOk()->json();

        // Sem caixa aberto não recebe
        $this->fecharComanda($comanda['id'])->assertUnprocessable()->assertJsonValidationErrors(['caixa']);

        $this->abrirCaixa();
        // Soma errada
        $this->fecharComanda($comanda['id'], ['pagamentos' => [['forma' => 'pix', 'valor' => 100]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['pagamentos']);

        $this->fecharComanda($comanda['id'])->assertOk()
            ->assertJsonPath('status', 'paga')
            ->assertJsonPath('subtotal', '130.00')
            ->assertJsonPath('desconto', '10.00')
            ->assertJsonPath('total', '120.00')
            ->assertJsonPath('itens.0.comissao_valor', '25.00')  // 50% de 50
            ->assertJsonPath('itens.1.comissao_valor', '8.00');  // 10% de 80

        $this->assertSame(8, $this->pomada->fresh()->estoque);
        $this->assertSame('concluido', $this->agendamento->fresh()->status);

        // Não fecha duas vezes nem reabre atendimento pago
        $this->fecharComanda($comanda['id'])->assertUnprocessable();
        $this->patchJson("/api/agendamentos/{$this->agendamento->id}/status", ['status' => 'confirmado'])
            ->assertUnprocessable()->assertJsonValidationErrors(['status']);
    }

    public function test_caixa_resumo_sangria_e_fechamento_com_diferenca(): void
    {
        $this->abrirCaixa(100);
        $this->postJson('/api/caixa/abrir', ['valor_inicial' => 50])->assertUnprocessable(); // já aberto

        $comanda = $this->postJson("/api/agendamentos/{$this->agendamento->id}/comanda")->json();
        $this->fecharComanda($comanda['id'])->assertOk();

        $this->postJson('/api/caixa/movimentos', ['tipo' => 'suprimento', 'valor' => 20])->assertCreated();
        $this->postJson('/api/caixa/movimentos', ['tipo' => 'sangria', 'valor' => 500])->assertUnprocessable();
        $this->postJson('/api/caixa/movimentos', ['tipo' => 'sangria', 'valor' => 30, 'descricao' => 'Depósito'])->assertCreated();

        // Despesa paga com dinheiro do caixa
        $this->postJson('/api/lancamentos', [
            'tipo' => 'despesa', 'descricao' => 'Café', 'categoria' => 'outros', 'valor' => 15, 'pago' => true, 'pagar_com_caixa' => true,
        ])->assertCreated();

        // 100 + 50 (dinheiro) + 20 − 30 − 15 = 125
        $this->getJson('/api/caixa/atual')->assertOk()
            ->assertJsonPath('caixa.resumo.recebido_por_forma.pix', 70)
            ->assertJsonPath('caixa.resumo.total_recebido', 120)
            ->assertJsonPath('caixa.resumo.dinheiro_esperado', 125)
            ->assertJsonCount(1, 'caixa.recebimentos');

        $this->postJson('/api/caixa/fechar', ['valor_contado' => 120])->assertOk()
            ->assertJsonPath('aberto', false)
            ->assertJsonPath('resumo.diferenca', -5);

        $this->getJson('/api/caixa/atual')->assertJsonPath('caixa', null);
        $this->getJson('/api/caixas')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_estorno_devolve_estoque_e_reabre_comanda(): void
    {
        $this->abrirCaixa();
        $comanda = $this->postJson("/api/agendamentos/{$this->agendamento->id}/comanda")->json();
        $this->fecharComanda($comanda['id'])->assertOk();

        $this->postJson("/api/comandas/{$comanda['id']}/estornar")->assertOk()->assertJsonPath('status', 'aberta');
        $this->assertSame(10, $this->pomada->fresh()->estoque);
        $this->assertDatabaseCount('pagamentos', 0);
    }

    public function test_venda_avulsa_de_produto(): void
    {
        $this->abrirCaixa();
        $id = $this->postJson('/api/comandas', [])->assertCreated()->json('id');

        $this->postJson("/api/comandas/{$id}/fechar", [
            'itens' => [['tipo' => 'produto', 'produto_id' => $this->pomada->id, 'quantidade' => 1, 'preco_unitario' => 40]],
            'pagamentos' => [['forma' => 'debito', 'valor' => 40]],
        ])->assertOk()
            ->assertJsonPath('total', '40.00')
            ->assertJsonPath('itens.0.comissao_valor', '0.00'); // sem barbeiro, sem comissão
    }

    public function test_comissoes_descontam_vales_e_repasses(): void
    {
        $this->abrirCaixa();
        $comanda = $this->postJson("/api/agendamentos/{$this->agendamento->id}/comanda")->json();
        $this->fecharComanda($comanda['id'])->assertOk(); // comissão 25 + 8 = 33

        $this->postJson('/api/lancamentos', ['tipo' => 'vale', 'descricao' => 'Adiantamento', 'valor' => 10, 'funcionario_id' => $this->carlos->id])
            ->assertCreated();
        $this->postJson('/api/lancamentos', ['tipo' => 'vale', 'descricao' => 'Sem barbeiro', 'valor' => 10])
            ->assertUnprocessable()->assertJsonValidationErrors(['funcionario_id']);
        $this->postJson('/api/lancamentos', ['tipo' => 'repasse_comissao', 'descricao' => 'Parcial', 'valor' => 5, 'funcionario_id' => $this->carlos->id])
            ->assertCreated();

        $this->getJson('/api/comissoes?inicio=2030-01-01&fim=2030-01-31')->assertOk()
            ->assertJsonPath('barbeiros.0.atendimentos', 1)
            ->assertJsonPath('barbeiros.0.faturado_servicos', 50)
            ->assertJsonPath('barbeiros.0.faturado_produtos', 80)
            ->assertJsonPath('barbeiros.0.comissao', 33)
            ->assertJsonPath('barbeiros.0.vales', 10)
            ->assertJsonPath('barbeiros.0.repasses', 5)
            ->assertJsonPath('barbeiros.0.saldo', 18);
    }

    public function test_despesas_do_mes_e_pagamento(): void
    {
        $id = $this->postJson('/api/lancamentos', [
            'tipo' => 'despesa', 'descricao' => 'Aluguel', 'categoria' => 'aluguel', 'valor' => 1500, 'vencimento' => '2030-01-10',
        ])->assertCreated()->assertJsonPath('pago_em', null)->assertJsonPath('atrasada', true)->json('id');

        $this->getJson('/api/lancamentos?tipo=despesa&mes=2030-01')->assertOk()
            ->assertJsonPath('total', 1500)
            ->assertJsonPath('total_pago', 0);

        $this->postJson("/api/lancamentos/{$id}/pagar")->assertOk()->assertJsonPath('atrasada', false);
        $this->postJson("/api/lancamentos/{$id}/pagar")->assertUnprocessable();
        $this->getJson('/api/lancamentos?tipo=despesa&mes=2030-02')->assertJsonCount(0, 'lancamentos');
    }

    public function test_permissoes_do_financeiro(): void
    {
        $empresa = $this->dono->empresa;

        $barbeiro = User::factory()->for($empresa)->perfil('barbeiro')->create();
        $this->carlos->update(['usuario_id' => $barbeiro->id]);
        $this->naEmpresa($empresa, fn () => Funcionario::create(['nome' => 'Outro']));
        Sanctum::actingAs($barbeiro);
        $this->getJson('/api/caixa/atual')->assertForbidden();
        $this->getJson('/api/comissoes')->assertOk()->assertJsonCount(1, 'barbeiros')->assertJsonPath('barbeiros.0.nome', 'Carlos');

        Sanctum::actingAs(User::factory()->for($empresa)->perfil('recepcao')->create());
        $this->getJson('/api/caixa/atual')->assertOk();
        $this->getJson('/api/lancamentos?tipo=despesa')->assertForbidden();
        $this->postJson('/api/produtos', ['nome' => 'X', 'preco' => 1])->assertForbidden();
    }
}
