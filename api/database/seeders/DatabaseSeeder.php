<?php

namespace Database\Seeders;

use App\Models\Agendamento;
use App\Models\Caixa;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\Lancamento;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\User;
use App\Services\Comandas;
use App\Support\EmpresaAtual;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Dados de demonstração para desenvolvimento local.
 *
 * Acessos de teste (somente ambiente local):
 *  - dono@demo.test / demo1234 (dono)
 *  - barbeiro@demo.test / demo1234 (barbeiro Rafael)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::create([
            'nome' => 'Barbearia Demo',
            'slug' => 'barbearia-demo',
            'slogan' => 'Corte clássico, atendimento de verdade',
            'descricao' => 'Barbearia de bairro com ambiente descontraído, café e cerveja gelada.',
            'telefone' => '11999990000',
            'whatsapp' => '11999990000',
            'email' => 'contato@demo.test',
            'instagram' => 'barbeariademo',
            'cep' => '01310-100',
            'logradouro' => 'Avenida Paulista',
            'numero' => '1000',
            'bairro' => 'Bela Vista',
            'cidade' => 'São Paulo',
            'uf' => 'SP',
        ])->refresh(); // carrega os valores padrão do banco (ex.: fuso horário)

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

        $rafaelUsuario = User::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Rafael Demo',
            'email' => 'barbeiro@demo.test',
            'password' => 'demo1234',
            'perfil' => 'barbeiro',
        ]);

        $barbeiros = collect();
        foreach (['Carlos' => $dono->id, 'Rafael' => $rafaelUsuario->id, 'Thiago' => null] as $nome => $usuarioId) {
            $barbeiro = Funcionario::forceCreate([
                'empresa_id' => $empresa->id,
                'usuario_id' => $usuarioId,
                'nome' => $nome,
                'tipo_vinculo' => 'parceiro',
                'comissao_percentual' => 50,
            ]);

            $barbeiro->servicos()->attach($servicos->pluck('id'), ['empresa_id' => $empresa->id]);

            // Segunda a sábado, 9h–19h, com almoço das 12h às 13h.
            foreach (range(1, 6) as $dia) {
                foreach ([['09:00', '12:00'], ['13:00', '19:00']] as [$inicio, $fim]) {
                    $barbeiro->horariosTrabalho()->forceCreate([
                        'empresa_id' => $empresa->id,
                        'dia_semana' => $dia,
                        'inicio' => $inicio,
                        'fim' => $fim,
                    ]);
                }
            }
            $barbeiros->push($barbeiro);
        }

        $this->agendamentosDeDemonstracao($empresa, $barbeiros, $servicos);
        $this->financeiroDeDemonstracao($empresa, $dono, $barbeiros);
    }

    /**
     * Produtos, caixa aberto hoje e comandas dos atendimentos concluídos
     * (alguns ficam aguardando pagamento), além de despesas e um vale.
     */
    private function financeiroDeDemonstracao(Empresa $empresa, User $dono, $barbeiros): void
    {
        // Produtos entram no estoque por compras (com fornecedor, nota e validade)
        $hojeLocal = now($empresa->fuso_horario)->startOfDay();
        foreach ([
            ['Pomada modeladora', 45, 20, 10, 'Distribuidora Barba Fina', [24, 12], 12],
            ['Shampoo para barba', 38, 15, 10, 'Distribuidora Barba Fina', [12, 12], 18],
            ['Cerveja long neck', 12, 5, 0, 'Atacadão Bebidas', [48, 24], 4],
            ['Refrigerante lata', 7, 3, 0, 'Atacadão Bebidas', [24, 24], 6],
        ] as $i => [$nome, $preco, $custo, $comissao, $fornecedor, $compras, $mesesValidade]) {
            $produto = Produto::create(compact('nome', 'preco', 'custo') + ['comissao_percentual' => $comissao, 'estoque' => array_sum($compras)]);
            foreach ([31, 10] as $j => $diasAtras) {
                $data = $hojeLocal->copy()->subDays($diasAtras);
                $produto->entradas()->create([
                    'data_compra' => $data->toDateString(),
                    'fornecedor' => $fornecedor,
                    'numero_nota' => (string) (4100 + $i * 10 + $j),
                    'quantidade' => $compras[$j],
                    'custo_unitario' => $custo,
                    'validade' => $data->copy()->addMonths($mesesValidade)->toDateString(),
                    'usuario_id' => $dono->id,
                ]);
            }
        }

        $comandas = app(Comandas::class);
        $formas = [['pix'], ['dinheiro'], ['credito'], ['debito'], ['pix', 'dinheiro']];
        $fuso = $empresa->fuso_horario;
        $hoje = now($fuso)->toDateString();
        $produtos = Produto::all();

        // Atendimentos concluídos agrupados pelo dia (no fuso da barbearia)
        $porDia = Agendamento::where('status', 'concluido')->with('itens')->orderBy('inicio')->get()
            ->groupBy(fn ($a) => $a->inicio->copy()->setTimezone($fuso)->toDateString());

        // Garante o caixa de hoje mesmo sem atendimentos concluídos ainda
        $dias = $porDia->keys()->push($hoje)->unique()->sort()->values();
        $n = 0;
        foreach ($dias as $dia) {
            $passado = $dia < $hoje;
            $abertura = $passado ? Carbon::parse("{$dia} 08:30", $fuso)->utc() : now();
            $caixa = Caixa::create(['aberto_por' => $dono->id, 'aberto_em' => $abertura, 'valor_inicial' => 100]);

            foreach ($porDia->get($dia, collect()) as $agendamento) {
                $n++;
                $comanda = $comandas->abrirDoAgendamento($agendamento);
                // Hoje, alguns ficam aguardando pagamento na recepção
                if (! $passado && $n % 4 === 3) {
                    continue;
                }
                $itens = $comanda->itens->map(fn ($item) => $item->only(['tipo', 'servico_id', 'produto_id', 'funcionario_id', 'quantidade', 'preco_unitario']))->all();
                // De vez em quando o cliente leva um produto
                if ($n % 5 === 0) {
                    $produto = $produtos[$n % $produtos->count()];
                    $itens[] = ['tipo' => 'produto', 'produto_id' => $produto->id, 'funcionario_id' => $agendamento->funcionario_id, 'quantidade' => 1, 'preco_unitario' => $produto->preco];
                }
                $total = array_sum(array_map(fn ($i) => $i['preco_unitario'] * $i['quantidade'], $itens));
                $forma = $formas[$n % count($formas)];
                $pagamentos = count($forma) === 1
                    ? [['forma' => $forma[0], 'valor' => $total]]
                    : [['forma' => $forma[0], 'valor' => $total - 20], ['forma' => $forma[1], 'valor' => 20]];

                $comanda = $comandas->fechar($comanda, $itens, 0, $pagamentos, $dono->id);
                // Pagamento registrado ao fim do atendimento (e não na hora em que o seeder roda)
                $comanda->update(['pago_em' => $agendamento->fim]);
                $comanda->pagamentos()->update(['created_at' => $agendamento->fim]);
            }

            if ($passado) {
                $dinheiro = (float) $caixa->pagamentos()->where('forma', 'dinheiro')->sum('valor');
                $caixa->update([
                    'fechado_por' => $dono->id,
                    'fechado_em' => Carbon::parse("{$dia} 19:30", $fuso)->utc(),
                    'valor_contado' => 100 + $dinheiro - ($n % 7 === 0 ? 5 : 0), // às vezes falta troco
                ]);
            }
        }

        $anterior = now($empresa->fuso_horario)->subMonthNoOverflow()->startOfMonth();
        foreach ([['Aluguel', 'aluguel', 1800, 5], ['Energia elétrica', 'contas', 298, 15], ['Internet', 'contas', 120, 25]] as [$descricao, $categoria, $valor, $dia]) {
            $vencimento = $anterior->copy()->day($dia);
            Lancamento::create([
                'tipo' => 'despesa', 'descricao' => $descricao, 'categoria' => $categoria, 'valor' => $valor,
                'vencimento' => $vencimento->toDateString(), 'pago_em' => $vencimento->copy()->setTime(10, 0)->utc(),
                'usuario_id' => $dono->id,
            ]);
        }

        $mes = now($empresa->fuso_horario)->startOfMonth();
        foreach ([
            ['Aluguel', 'aluguel', 1800, 5, true],
            ['Energia elétrica', 'contas', 320, 15, false],
            ['Reposição de produtos', 'produtos', 450, 20, false],
            ['Internet', 'contas', 120, 25, false],
        ] as [$descricao, $categoria, $valor, $dia, $pago]) {
            Lancamento::create([
                'tipo' => 'despesa', 'descricao' => $descricao, 'categoria' => $categoria, 'valor' => $valor,
                'vencimento' => $mes->copy()->day($dia)->toDateString(),
                'pago_em' => $pago ? $mes->copy()->day($dia)->setTime(10, 0)->utc() : null,
                'usuario_id' => $dono->id,
            ]);
        }
        Lancamento::create([
            'tipo' => 'vale', 'descricao' => 'Adiantamento', 'valor' => 100, 'funcionario_id' => $barbeiros[1]->id,
            'pago_em' => now(), 'usuario_id' => $dono->id,
        ]);
    }

    /**
     * Cria clientes, 30 dias de histórico e os agendamentos de hoje e amanhã. A situação
     * de hoje depende da hora em que o seeder roda (passado = concluído, agora = em atendimento).
     */
    private function agendamentosDeDemonstracao(Empresa $empresa, $barbeiros, $servicos): void
    {
        $nomes = ['João Pereira', 'Lucas Martins', 'Pedro Alves', 'Gabriel Rocha', 'Mateus Costa', 'Bruno Lima',
            'Felipe Souza', 'André Ribeiro', 'Diego Fernandes', 'Rodrigo Nunes', 'Vinícius Gomes', 'Caio Barros'];
        $clientes = collect($nomes)->map(fn ($nome, $i) => Cliente::forceCreate([
            'empresa_id' => $empresa->id,
            'nome' => $nome,
            'telefone' => '119'.str_pad((string) (80000000 + $i * 1111), 8, '0', STR_PAD_LEFT),
        ]));

        // [barbeiro, hora, serviço (índice), cliente (índice)]
        $grade = [
            [0, '09:00', 2, 0], [0, '10:00', 0, 1], [0, '10:30', 1, 2], [0, '11:15', 3, 3], [0, '13:00', 0, 4],
            [0, '14:00', 2, 5], [0, '15:30', 0, 6], [0, '17:00', 1, 7],
            [1, '09:30', 0, 8], [1, '10:00', 2, 9], [1, '11:00', 0, 10], [1, '13:30', 1, 11], [1, '14:00', 0, 0],
            [1, '16:00', 2, 1], [1, '18:00', 0, 2],
            [2, '09:00', 0, 3], [2, '09:45', 1, 4], [2, '11:00', 2, 5], [2, '14:30', 0, 6], [2, '15:00', 3, 7],
            [2, '16:30', 2, 8],
        ];

        $agora = now($empresa->fuso_horario);
        foreach (range(-30, 1) as $diasAFrente) {
            $dia = $agora->copy()->startOfDay()->addDays($diasAFrente);
            if ($dia->isSunday()) {
                continue; // barbearia fechada
            }
            foreach ($grade as $n => [$b, $hora, $s, $c]) {
                $semente = $n + $diasAFrente * 7;
                // Dias anteriores com movimento variado
                if ($diasAFrente < -1 && $semente % 3 === 0) {
                    continue;
                }
                $servico = $servicos[$s];
                $inicio = $dia->copy()->setTimeFromTimeString($hora);
                $fim = $inicio->copy()->addMinutes($servico->duracao_minutos);

                $status = match (true) {
                    $diasAFrente < 0 && $semente % 13 === 0 => 'cancelado',
                    $fim->lte($agora) => abs($semente) % 9 === 4 ? 'faltou' : 'concluido',
                    $inicio->lte($agora) => 'em_atendimento',
                    default => $n % 3 === 0 ? 'agendado' : 'confirmado',
                };

                $agendamento = Agendamento::forceCreate([
                    'empresa_id' => $empresa->id,
                    'cliente_id' => $clientes[$c]->id,
                    'funcionario_id' => $barbeiros[$b]->id,
                    'inicio' => $inicio->copy()->utc(),
                    'fim' => $fim->copy()->utc(),
                    'status' => $status,
                    'origem' => $n % 2 ? 'link_publico' : 'recepcao',
                    'valor_total' => $servico->preco,
                ]);
                $agendamento->itens()->forceCreate([
                    'empresa_id' => $empresa->id,
                    'servico_id' => $servico->id,
                    'nome' => $servico->nome,
                    'preco' => $servico->preco,
                    'duracao_minutos' => $servico->duracao_minutos,
                ]);
            }
        }
    }
}
