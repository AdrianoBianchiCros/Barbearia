<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientesTest extends TestCase
{
    use RefreshDatabase;

    public function test_recepcao_cadastra_cliente_com_consentimento(): void
    {
        $this->logarComo('recepcao');

        $id = $this->postJson('/api/clientes', [
            'nome' => 'João Pereira',
            'telefone' => '(11) 97777-6666',
            'aceita_whatsapp' => true,
        ])->assertCreated()
            ->assertJsonPath('telefone', '11977776666')
            ->assertJsonPath('aceita_whatsapp', true)
            ->json('id');

        $this->assertDatabaseHas('consentimentos', ['cliente_id' => $id, 'canal' => 'whatsapp', 'aceito' => true]);

        // Revogar gera um novo registro (histórico para a LGPD)
        $this->putJson("/api/clientes/{$id}", ['nome' => 'João Pereira', 'telefone' => '11977776666', 'aceita_whatsapp' => false])
            ->assertOk()
            ->assertJsonPath('aceita_whatsapp', false);

        $this->assertDatabaseCount('consentimentos', 2);
    }

    public function test_telefone_unico_por_barbearia(): void
    {
        $outra = Empresa::factory()->create();
        $this->naEmpresa($outra, fn () => Cliente::create(['nome' => 'Na outra', 'telefone' => '11977776666']));

        $this->logarComo('dono');

        $this->postJson('/api/clientes', ['nome' => 'João', 'telefone' => '11977776666'])->assertCreated();
        $this->postJson('/api/clientes', ['nome' => 'Outro João', 'telefone' => '(11) 97777-6666'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['telefone']);
    }

    public function test_busca_por_nome_ou_telefone_com_paginacao(): void
    {
        $usuario = $this->logarComo('dono');
        $this->naEmpresa($usuario->empresa_id, function () {
            Cliente::create(['nome' => 'Ana Souza', 'telefone' => '11911112222']);
            Cliente::create(['nome' => 'Bruno Lima', 'telefone' => '11933334444']);
        });

        $this->getJson('/api/clientes?q=ana')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.nome', 'Ana Souza');
        $this->getJson('/api/clientes?q=3333')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.nome', 'Bruno Lima');
        $this->getJson('/api/clientes')->assertOk()->assertJsonPath('total', 2);
    }

    public function test_telefone_invalido(): void
    {
        $this->logarComo('dono');

        $this->postJson('/api/clientes', ['nome' => 'João', 'telefone' => '1234'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['telefone']);
    }

    public function test_somente_dono_exclui_e_nao_enxerga_outra_barbearia(): void
    {
        $outra = Empresa::factory()->create();
        $alheio = $this->naEmpresa($outra, fn () => Cliente::create(['nome' => 'Alheio', 'telefone' => '11900000000']));

        $recepcao = $this->logarComo('recepcao');
        $meu = $this->naEmpresa($recepcao->empresa_id, fn () => Cliente::create(['nome' => 'Meu', 'telefone' => '11911111111']));
        $this->deleteJson("/api/clientes/{$meu->id}")->assertForbidden();
        $this->getJson("/api/clientes/{$alheio->id}")->assertNotFound();

        $this->logarComo('dono', $recepcao->empresa);
        $this->deleteJson("/api/clientes/{$meu->id}")->assertNoContent();
        $this->assertSoftDeleted('clientes', ['id' => $meu->id]);
    }
}
