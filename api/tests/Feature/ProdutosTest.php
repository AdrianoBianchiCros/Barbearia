<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProdutosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2030-01-20 10:00', 'America/Sao_Paulo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cadastra_produto_com_a_primeira_compra(): void
    {
        $this->logarComo('dono');

        $r = $this->postJson('/api/produtos', [
            'nome' => 'Pomada', 'preco' => 45, 'estoque' => null,
            'entrada' => [
                'data_compra' => '2030-01-18', 'fornecedor' => 'Distribuidora X', 'numero_nota' => 'NF 123',
                'quantidade' => 12, 'custo_unitario' => 20, 'validade' => '2031-01-18',
            ],
        ])->assertCreated()
            ->assertJsonPath('estoque', 12)
            ->assertJsonPath('custo', '20.00')
            ->assertJsonPath('ultima_compra.fornecedor', 'Distribuidora X')
            ->assertJsonPath('ultima_compra.validade', '2031-01-18');

        $this->getJson("/api/produtos/{$r->json('id')}/entradas")->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.numero_nota', 'NF 123')
            ->assertJsonPath('0.quantidade', 12);
    }

    public function test_cadastro_sem_compra_continua_funcionando(): void
    {
        $this->logarComo('dono');

        $this->postJson('/api/produtos', ['nome' => 'Cerveja', 'preco' => 12, 'estoque' => 5])
            ->assertCreated()
            ->assertJsonPath('estoque', 5)
            ->assertJsonPath('ultima_compra', null);
    }

    public function test_nova_compra_soma_ao_estoque_e_excluir_devolve(): void
    {
        $usuario = $this->logarComo('dono');
        $produto = $this->naEmpresa($usuario->empresa_id, fn () => Produto::create(['nome' => 'Shampoo', 'preco' => 38, 'estoque' => 3]));

        $id = $this->postJson("/api/produtos/{$produto->id}/entradas", [
            'data_compra' => '2030-01-19', 'fornecedor' => 'Fornecedor Y', 'quantidade' => 10, 'validade' => '2030-12-01',
        ])->assertCreated()
            ->assertJsonPath('produto.estoque', 13)
            ->assertJsonPath('produto.custo', null)   // sem custo informado, não altera
            ->json('entrada.id');

        $this->deleteJson("/api/produtos/{$produto->id}/entradas/{$id}")->assertOk()->assertJsonPath('estoque', 3);
    }

    public function test_lotes_em_estoque_e_proximo_vencimento(): void
    {
        $usuario = $this->logarComo('dono');
        $produto = $this->naEmpresa($usuario->empresa_id, fn () => Produto::create(['nome' => 'Pomada', 'preco' => 45, 'estoque' => 0]));
        $compra = fn (array $d) => $this->postJson("/api/produtos/{$produto->id}/entradas", $d)->assertCreated();

        $compra(['data_compra' => '2030-01-05', 'lote' => 'L1', 'quantidade' => 10, 'validade' => '2030-06-30']);
        $compra(['data_compra' => '2030-01-15', 'lote' => 'L2', 'quantidade' => 5, 'validade' => '2030-03-31'])
            ->assertJsonPath('entrada.lote', 'L2')
            ->assertJsonPath('produto.ultima_compra.lote', 'L2')
            ->assertJsonCount(2, 'produto.lotes_em_estoque')
            // Vence primeiro o L2, mesmo tendo sido comprado depois
            ->assertJsonPath('produto.proximo_vencimento.lote', 'L2')
            ->assertJsonPath('produto.proximo_vencimento.quantidade', 5);

        // Vendeu 11: saem primeiro as unidades mais antigas (todo o L1 e 1 do L2)
        $produto->update(['estoque' => 4]);

        $this->getJson('/api/produtos')->assertOk()
            ->assertJsonCount(1, '0.lotes_em_estoque')
            ->assertJsonPath('0.lotes_em_estoque.0', ['lote' => 'L2', 'validade' => '2030-03-31', 'quantidade' => 4, 'data_compra' => '2030-01-15']);

        $this->getJson("/api/produtos/{$produto->id}/entradas")->assertOk()
            ->assertJsonPath('0.lote', 'L2')
            ->assertJsonPath('0.em_estoque', 4)
            ->assertJsonPath('1.lote', 'L1')
            ->assertJsonPath('1.em_estoque', 0);
    }

    public function test_editar_compra_ajusta_estoque_e_custo(): void
    {
        $usuario = $this->logarComo('dono');
        $produto = $this->naEmpresa($usuario->empresa_id, fn () => Produto::create(['nome' => 'Pomada', 'preco' => 45, 'estoque' => 2]));
        $id = $this->postJson("/api/produtos/{$produto->id}/entradas", [
            'data_compra' => '2030-01-18', 'quantidade' => 10, 'custo_unitario' => 20, 'lote' => 'L1',
        ])->assertCreated()->assertJsonPath('produto.estoque', 12)->json('entrada.id');

        // Quantidade 10 → 6: estoque cai 4; custo e lote corrigidos
        $this->putJson("/api/produtos/{$produto->id}/entradas/{$id}", [
            'data_compra' => '2030-01-18', 'quantidade' => 6, 'custo_unitario' => 22, 'lote' => 'L1-A',
            'fornecedor' => 'Novo fornecedor', 'validade' => '2031-01-01',
        ])->assertOk()
            ->assertJsonPath('entrada.quantidade', 6)
            ->assertJsonPath('entrada.lote', 'L1-A')
            ->assertJsonPath('produto.estoque', 8)
            ->assertJsonPath('produto.custo', '22.00');

        $this->putJson("/api/produtos/{$produto->id}/entradas/{$id}", ['data_compra' => '2030-01-18', 'quantidade' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors(['quantidade']);

        // Compra de outro produto não pode ser editada por esta rota
        $outro = $this->naEmpresa($usuario->empresa_id, fn () => Produto::create(['nome' => 'Outro', 'preco' => 1]));
        $this->putJson("/api/produtos/{$outro->id}/entradas/{$id}", ['data_compra' => '2030-01-18', 'quantidade' => 1])->assertNotFound();
    }

    public function test_validacoes_da_compra(): void
    {
        $usuario = $this->logarComo('dono');
        $produto = $this->naEmpresa($usuario->empresa_id, fn () => Produto::create(['nome' => 'Shampoo', 'preco' => 38]));

        $this->postJson("/api/produtos/{$produto->id}/entradas", [
            'data_compra' => '2030-02-01', 'quantidade' => 0, 'validade' => '2030-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['data_compra', 'quantidade']);

        $this->postJson("/api/produtos/{$produto->id}/entradas", [
            'data_compra' => '2030-01-10', 'quantidade' => 2, 'validade' => '2030-01-05',
        ])->assertUnprocessable()->assertJsonValidationErrors(['validade']);
    }

    public function test_recepcao_nao_lanca_compra_nem_ve_compra_de_outra_barbearia(): void
    {
        $outra = Empresa::factory()->create();
        $alheio = $this->naEmpresa($outra, fn () => Produto::create(['nome' => 'Alheio', 'preco' => 1]));

        $dono = $this->logarComo('dono');
        $this->getJson("/api/produtos/{$alheio->id}/entradas")->assertNotFound();
        $meu = $this->naEmpresa($dono->empresa_id, fn () => Produto::create(['nome' => 'Meu', 'preco' => 1]));

        $this->logarComo('recepcao', $dono->empresa);
        $this->postJson("/api/produtos/{$meu->id}/entradas", ['data_compra' => '2030-01-10', 'quantidade' => 1])->assertForbidden();
    }
}
