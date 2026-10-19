<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\Servico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FuncionariosTest extends TestCase
{
    use RefreshDatabase;

    public function test_dono_cadastra_barbeiro_com_telefone_normalizado(): void
    {
        $this->logarComo('dono');

        $this->postJson('/api/funcionarios', [
            'nome' => 'Carlos',
            'telefone' => '+55 (11) 98888-7777',
            'tipo_vinculo' => 'parceiro',
            'comissao_percentual' => 50,
        ])->assertCreated()
            ->assertJsonPath('telefone', '11988887777')
            ->assertJsonPath('servicos', [])
            ->assertJsonPath('horarios_trabalho', []);
    }

    public function test_define_servicos_do_barbeiro_com_preco_proprio(): void
    {
        $usuario = $this->logarComo('dono');
        [$barbeiro, $corte, $barba] = $this->naEmpresa($usuario->empresa_id, fn () => [
            Funcionario::create(['nome' => 'Carlos']),
            Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 45]),
            Servico::create(['nome' => 'Barba', 'duracao_minutos' => 30, 'preco' => 35]),
        ]);

        $this->putJson("/api/funcionarios/{$barbeiro->id}/servicos", ['servicos' => [
            ['id' => $corte->id, 'preco' => 55],
            ['id' => $barba->id],
        ]])->assertOk()
            ->assertJsonCount(2, 'servicos')
            ->assertJsonPath('servicos.0.nome', 'Barba')
            ->assertJsonPath('servicos.1.nome', 'Corte')
            ->assertJsonPath('servicos.1.preco', '55.00');

        $this->assertDatabaseHas('funcionario_servicos', [
            'funcionario_id' => $barbeiro->id, 'servico_id' => $corte->id, 'empresa_id' => $usuario->empresa_id,
        ]);

        // Remover todos
        $this->putJson("/api/funcionarios/{$barbeiro->id}/servicos", ['servicos' => []])
            ->assertOk()->assertJsonCount(0, 'servicos');
    }

    public function test_nao_vincula_servico_de_outra_barbearia(): void
    {
        $outra = Empresa::factory()->create();
        $alheio = $this->naEmpresa($outra, fn () => Servico::create(['nome' => 'Alheio', 'duracao_minutos' => 30, 'preco' => 10]));
        $usuario = $this->logarComo('dono');
        $barbeiro = $this->naEmpresa($usuario->empresa_id, fn () => Funcionario::create(['nome' => 'Carlos']));

        $this->putJson("/api/funcionarios/{$barbeiro->id}/servicos", ['servicos' => [['id' => $alheio->id]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['servicos.0.id']);
    }

    public function test_salva_expediente_e_rejeita_sobreposicao(): void
    {
        $usuario = $this->logarComo('dono');
        $barbeiro = $this->naEmpresa($usuario->empresa_id, fn () => Funcionario::create(['nome' => 'Carlos']));

        $this->putJson("/api/funcionarios/{$barbeiro->id}/horarios", ['horarios' => [
            ['dia_semana' => 2, 'inicio' => '13:00', 'fim' => '19:00'],
            ['dia_semana' => 2, 'inicio' => '09:00', 'fim' => '12:00'],
            ['dia_semana' => 6, 'inicio' => '08:00', 'fim' => '14:00'],
        ]])->assertOk()
            ->assertJsonCount(3, 'horarios_trabalho')
            ->assertJsonPath('horarios_trabalho.0', ['dia_semana' => 2, 'inicio' => '09:00', 'fim' => '12:00']);

        $this->putJson("/api/funcionarios/{$barbeiro->id}/horarios", ['horarios' => [
            ['dia_semana' => 2, 'inicio' => '09:00', 'fim' => '13:00'],
            ['dia_semana' => 2, 'inicio' => '12:00', 'fim' => '18:00'],
        ]])->assertUnprocessable()->assertJsonValidationErrors(['horarios']);

        $this->putJson("/api/funcionarios/{$barbeiro->id}/horarios", ['horarios' => [
            ['dia_semana' => 3, 'inicio' => '18:00', 'fim' => '09:00'],
        ]])->assertUnprocessable()->assertJsonValidationErrors(['horarios']);

        // O expediente anterior continua intacto após as tentativas inválidas
        $this->assertDatabaseCount('horarios_trabalho', 3);
    }

    public function test_envia_foto_do_barbeiro(): void
    {
        Storage::fake('public');
        $usuario = $this->logarComo('dono');
        $barbeiro = $this->naEmpresa($usuario->empresa_id, fn () => Funcionario::create(['nome' => 'Carlos']));

        $resposta = $this->postJson("/api/funcionarios/{$barbeiro->id}/foto", [
            'foto' => UploadedFile::fake()->image('carlos.jpg', 400, 400),
        ])->assertOk();

        $this->assertNotNull($resposta->json('foto_url'));
        Storage::disk('public')->assertExists($barbeiro->fresh()->foto_path);
    }

    public function test_recepcao_nao_altera_equipe(): void
    {
        $this->logarComo('recepcao');

        $this->getJson('/api/funcionarios')->assertOk();
        $this->postJson('/api/funcionarios', ['nome' => 'X'])->assertForbidden();
    }
}
