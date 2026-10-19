<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Servico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicosTest extends TestCase
{
    use RefreshDatabase;

    public function test_dono_cria_edita_lista_e_exclui_servico(): void
    {
        $this->logarComo('dono');

        $id = $this->postJson('/api/servicos', ['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 45])
            ->assertCreated()
            ->assertJsonPath('nome', 'Corte')
            ->json('id');

        $this->putJson("/api/servicos/{$id}", ['nome' => 'Corte degradê', 'duracao_minutos' => 40, 'preco' => 50, 'ativo' => false])
            ->assertOk()
            ->assertJsonPath('ativo', false);

        $this->getJson('/api/servicos')->assertOk()->assertJsonCount(1)->assertJsonPath('0.nome', 'Corte degradê');
        $this->getJson('/api/servicos?ativos=1')->assertOk()->assertJsonCount(0);

        $this->deleteJson("/api/servicos/{$id}")->assertNoContent();
        $this->assertSoftDeleted('servicos', ['id' => $id]);
    }

    public function test_valida_campos_obrigatorios(): void
    {
        $this->logarComo('dono');

        $this->postJson('/api/servicos', ['nome' => '', 'duracao_minutos' => 2, 'preco' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nome', 'duracao_minutos', 'preco']);
    }

    public function test_recepcao_e_barbeiro_so_consultam(): void
    {
        foreach (['recepcao', 'barbeiro'] as $perfil) {
            $this->logarComo($perfil);
            $this->getJson('/api/servicos')->assertOk();
            $this->postJson('/api/servicos', ['nome' => 'X', 'duracao_minutos' => 30, 'preco' => 10])->assertForbidden();
        }
    }

    public function test_nao_acessa_servico_de_outra_barbearia(): void
    {
        $outra = Empresa::factory()->create();
        $alheio = $this->naEmpresa($outra, fn () => Servico::create(['nome' => 'Alheio', 'duracao_minutos' => 30, 'preco' => 10]));

        $this->logarComo('dono');

        $this->getJson('/api/servicos')->assertOk()->assertJsonCount(0);
        $this->getJson("/api/servicos/{$alheio->id}")->assertNotFound();
        $this->putJson("/api/servicos/{$alheio->id}", ['nome' => 'Hack', 'duracao_minutos' => 30, 'preco' => 1])->assertNotFound();
        $this->deleteJson("/api/servicos/{$alheio->id}")->assertNotFound();

        $this->assertDatabaseHas('servicos', ['id' => $alheio->id, 'nome' => 'Alheio', 'deleted_at' => null]);
    }
}
