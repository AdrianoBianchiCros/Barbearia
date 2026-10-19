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
