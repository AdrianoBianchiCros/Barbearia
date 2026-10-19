<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Models\User;
use App\Support\EmpresaAtual;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Dados de demonstração para desenvolvimento local.
 *
 * Acesso de teste: dono@demo.test / demo1234 (somente ambiente local).
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $empresa = Empresa::create([
            'nome' => 'Barbearia Demo',
            'slug' => 'barbearia-demo',
            'telefone' => '11999990000',
            'email' => 'contato@demo.test',
        ]);

        // A partir daqui, tudo o que for criado pertence à Barbearia Demo.
        app(EmpresaAtual::class)->definir($empresa->id);

        $dono = User::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Dono Demo',
            'email' => 'dono@demo.test',
            'password' => 'demo1234',
            'perfil' => 'dono',
        ]);

        $servicos = collect([
            ['nome' => 'Corte masculino', 'duracao_minutos' => 30, 'preco' => 45],
            ['nome' => 'Barba', 'duracao_minutos' => 30, 'preco' => 35],
            ['nome' => 'Corte + barba', 'duracao_minutos' => 60, 'preco' => 70],
            ['nome' => 'Sobrancelha', 'duracao_minutos' => 15, 'preco' => 15],
        ])->map(fn ($s) => Servico::forceCreate($s + ['empresa_id' => $empresa->id]));

        foreach (['Carlos', 'Rafael'] as $i => $nome) {
            $barbeiro = Funcionario::forceCreate([
                'empresa_id' => $empresa->id,
                'usuario_id' => $i === 0 ? $dono->id : null,
                'nome' => $nome,
                'tipo_vinculo' => 'parceiro',
                'comissao_percentual' => 50,
            ]);

            $barbeiro->servicos()->attach($servicos->pluck('id'), ['empresa_id' => $empresa->id]);

            // Terça a sábado, 9h–19h, com almoço das 12h às 13h.
            foreach (range(2, 6) as $dia) {
                foreach ([['09:00', '12:00'], ['13:00', '19:00']] as [$inicio, $fim]) {
                    $barbeiro->horariosTrabalho()->forceCreate([
                        'empresa_id' => $empresa->id,
                        'dia_semana' => $dia,
                        'inicio' => $inicio,
                        'fim' => $fim,
                    ]);
                }
            }
        }
    }
}
