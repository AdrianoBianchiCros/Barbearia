<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Support\EmpresaAtual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IsolamentoEmpresaTest extends TestCase
{
    use RefreshDatabase;

    private function criarCliente(Empresa $empresa, string $nome): Cliente
    {
        return Cliente::forceCreate(['empresa_id' => $empresa->id, 'nome' => $nome, 'telefone' => fake()->numerify('119########')]);
    }

    public function test_consultas_enxergam_somente_a_barbearia_atual(): void
    {
        $a = Empresa::factory()->create();
        $b = Empresa::factory()->create();
        $this->criarCliente($a, 'Cliente A');
        $this->criarCliente($b, 'Cliente B');

        app(EmpresaAtual::class)->definir($a->id);

        $this->assertSame(['Cliente A'], Cliente::pluck('nome')->all());
        $this->assertNull(Cliente::where('nome', 'Cliente B')->first());
    }

    public function test_registro_novo_recebe_a_barbearia_atual_mesmo_se_outra_for_informada(): void
    {
        $a = Empresa::factory()->create();
        $b = Empresa::factory()->create();

        app(EmpresaAtual::class)->definir($a->id);

        $cliente = Cliente::forceCreate(['empresa_id' => $b->id, 'nome' => 'Tentativa', 'telefone' => '11988887777']);

        $this->assertSame($a->id, $cliente->empresa_id);
    }

    public function test_mesmo_telefone_pode_existir_em_barbearias_diferentes(): void
    {
        $a = Empresa::factory()->create();
        $b = Empresa::factory()->create();

        Cliente::forceCreate(['empresa_id' => $a->id, 'nome' => 'João', 'telefone' => '11988887777']);
        Cliente::forceCreate(['empresa_id' => $b->id, 'nome' => 'João', 'telefone' => '11988887777']);

        $this->assertSame(2, Cliente::count());
    }

    public function test_seeder_de_demonstracao_roda(): void
    {
        $this->seed();

        $this->assertDatabaseHas('usuarios', ['email' => 'dono@demo.test']);
        $this->assertDatabaseCount('servicos', 4);
        $this->assertDatabaseCount('funcionario_servicos', 8);
    }
}
