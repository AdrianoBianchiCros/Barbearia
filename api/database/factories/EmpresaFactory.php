<?php

namespace Database\Factories;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Empresa>
 */
class EmpresaFactory extends Factory
{
    public function definition(): array
    {
        $nome = 'Barbearia '.fake()->unique()->lastName();

        return [
            'nome' => $nome,
            'slug' => Str::slug($nome),
            'telefone' => fake()->numerify('119########'),
            'email' => fake()->unique()->companyEmail(),
            'fuso_horario' => 'America/Sao_Paulo',
            'ativo' => true,
        ];
    }

    public function inativa(): static
    {
        return $this->state(fn (array $attributes) => ['ativo' => false]);
    }
}
