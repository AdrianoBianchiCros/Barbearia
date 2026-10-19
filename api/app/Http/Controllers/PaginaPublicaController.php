<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Services\Disponibilidade;
use App\Support\Telefone;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Página pública da barbearia (/{slug}) e agendamento online pelo cliente, sem login.
 * A barbearia é definida pelo middleware DefinirEmpresaPublica.
 */
class PaginaPublicaController extends Controller
{
    /** Até quantos dias à frente o cliente pode agendar pela página. */
    public const DIAS_ANTECEDENCIA = 60;

    /** Horários futuros que um mesmo WhatsApp pode ter marcados pela página. */
    public const MAX_AGENDAMENTOS_FUTUROS = 3;

    private const DIAS_SEMANA = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

    public function show(Request $request): View
    {
        $empresa = $this->empresa($request);
        $barbeiros = $this->barbeiros()->load('horariosTrabalho');
        $servicos = Servico::query()
            ->where('ativo', true)
            ->whereIn('id', $barbeiros->pluck('servicos')->flatten()->pluck('id')->unique())
            ->orderBy('nome')
            ->get();

        return view('pagina.show', [
            'empresa' => $empresa,
            'servicos' => $servicos,
            'barbeiros' => $barbeiros,
            'funcionamento' => $this->funcionamento($barbeiros),
            'endereco' => $this->endereco($empresa),
            'corTexto' => $this->corDoTexto($empresa->cor_primaria),
            'config' => [
                'urlHorarios' => url("/api/publico/{$empresa->slug}/horarios"),
                'urlAgendar' => url("/api/publico/{$empresa->slug}/agendamentos"),
                'hoje' => now($empresa->fuso_horario)->toDateString(),
                'diasAntecedencia' => self::DIAS_ANTECEDENCIA,
                'servicos' => $servicos->map(fn ($s) => [
                    'id' => $s->id,
                    'nome' => $s->nome,
                    'preco' => (float) $s->preco,
                    'duracao' => $s->duracao_minutos,
                ])->values(),
                'barbeiros' => $barbeiros->map(fn ($f) => [
                    'id' => $f->id,
                    'nome' => $f->nome,
                    'foto' => $f->foto_url,
                    'servicos' => $f->servicos->pluck('id')->values(),
                ])->values(),
            ],
        ]);
    }

    /**
     * Horários livres por barbeiro para os serviços escolhidos.
     * GET /api/publico/{slug}/horarios?data=2026-10-20&servico_ids[]=1[&funcionario_id=3]
     */
    public function horarios(Request $request): JsonResponse
    {
        $empresa = $this->empresa($request);
        $dados = $request->validate([
            'data' => ['required', 'date_format:Y-m-d'],
            'servico_ids' => ['required', 'array', 'min:1', 'max:10'],
            'servico_ids.*' => ['integer', 'distinct'],
            'funcionario_id' => ['nullable', 'integer'],
        ]);

        if (! $this->dataPermitida($empresa, $dados['data'])) {
            return response()->json([]);
        }

        $servicoIds = array_map('intval', $dados['servico_ids']);
        $disponibilidade = new Disponibilidade($empresa->fuso_horario);

        return response()->json($this->barbeirosQueFazem($servicoIds, $dados['funcionario_id'] ?? null)
            ->map(function (Funcionario $f) use ($servicoIds, $dados, $disponibilidade) {
                $itens = $f->itensDosServicos($servicoIds);

                return [
                    'funcionario_id' => $f->id,
                    'nome' => $f->nome,
                    'duracao_minutos' => $itens->sum('duracao_minutos'),
                    'valor_total' => number_format($itens->sum('preco'), 2, '.', ''),
                    'horarios' => $disponibilidade->horariosLivres($f, $dados['data'], $itens->sum('duracao_minutos')),
                ];
            })
            ->values());
    }

    /**
     * Agendamento feito pelo próprio cliente. Sem barbeiro escolhido ("sem preferência"),
     * fica com o primeiro que estiver livre no horário.
     * POST /api/publico/{slug}/agendamentos
     */
    public function agendar(Request $request): JsonResponse
    {
        $empresa = $this->empresa($request);
        $request->merge(['telefone' => Telefone::normalizar($request->input('telefone'))]);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'telefone' => ['required', Telefone::REGRA],
            'aceita_whatsapp' => ['sometimes', 'boolean'],
            'funcionario_id' => ['nullable', 'integer', $this->existeNaEmpresa('funcionarios')],
            'servico_ids' => ['required', 'array', 'min:1', 'max:10'],
            'servico_ids.*' => ['integer', 'distinct', $this->existeNaEmpresa('servicos')->where('ativo', true)],
            'data' => ['required', 'date_format:Y-m-d'],
            'hora' => ['required', 'date_format:H:i'],
            'observacoes' => ['nullable', 'string', 'max:300'],
        ], [
            'telefone.regex' => 'Informe o WhatsApp com DDD (10 ou 11 dígitos).',
        ], [
            'telefone' => 'WhatsApp',
            'funcionario_id' => 'barbeiro',
            'servico_ids' => 'serviços',
        ]);

        if (! $this->dataPermitida($empresa, $dados['data'])) {
            throw ValidationException::withMessages([
                'data' => 'Escolha uma data entre hoje e os próximos '.self::DIAS_ANTECEDENCIA.' dias.',
            ]);
        }

        $servicoIds = array_map('intval', $dados['servico_ids']);
        $candidatos = $this->barbeirosQueFazem($servicoIds, $dados['funcionario_id'] ?? null);
        if ($candidatos->isEmpty()) {
            throw ValidationException::withMessages(['servico_ids' => 'Nenhum barbeiro disponível realiza os serviços escolhidos.']);
        }

        $disponibilidade = new Disponibilidade($empresa->fuso_horario);

        $agendamento = DB::transaction(function () use ($dados, $servicoIds, $candidatos, $disponibilidade) {
            $cliente = $this->cliente($dados);

            $futuros = $cliente->agendamentos()
                ->whereIn('status', ['agendado', 'confirmado'])
                ->where('inicio', '>', now())
                ->count();
            if ($futuros >= self::MAX_AGENDAMENTOS_FUTUROS) {
                throw ValidationException::withMessages([
                    'telefone' => 'Você já tem '.self::MAX_AGENDAMENTOS_FUTUROS.' horários marcados. Para marcar outro, fale com a barbearia.',
                ]);
            }

            foreach ($candidatos as $barbeiro) {
                // Trava a agenda do barbeiro para que dois clientes não peguem o mesmo horário.
                Funcionario::whereKey($barbeiro->id)->lockForUpdate()->first();
                $itens = $barbeiro->itensDosServicos($servicoIds);
                $duracao = $itens->sum('duracao_minutos');

                if ($disponibilidade->motivoIndisponivel($barbeiro, $dados['data'], $dados['hora'], $duracao)) {
                    continue;
                }

                $inicio = $disponibilidade->instante($dados['data'], $dados['hora']);
                $agendamento = Agendamento::create([
                    'cliente_id' => $cliente->id,
                    'funcionario_id' => $barbeiro->id,
                    'inicio' => $inicio,
                    'fim' => $inicio->copy()->addMinutes($duracao),
                    'status' => 'agendado',
                    'origem' => 'link_publico',
                    'valor_total' => $itens->sum('preco'),
                    'observacoes' => $dados['observacoes'] ?? null,
                ]);
                foreach ($itens as $item) {
                    $agendamento->itens()->create($item);
                }

                return $agendamento->setRelation('funcionario', $barbeiro);
            }

            throw ValidationException::withMessages([
                'hora' => 'Esse horário acabou de ser ocupado. Escolha outro, por favor.',
            ]);
        });

        $agendamento->load('itens');
        $inicio = $agendamento->inicio->copy()->setTimezone($empresa->fuso_horario);

        return response()->json([
            'id' => $agendamento->id,
            'data' => $inicio->toDateString(),
            'hora' => $inicio->format('H:i'),
            'barbeiro' => $agendamento->funcionario->nome,
            'servicos' => $agendamento->itens->pluck('nome')->values(),
            'duracao_minutos' => $agendamento->itens->sum('duracao_minutos'),
            'valor_total' => $agendamento->valor_total,
        ], 201);
    }

    private function empresa(Request $request): Empresa
    {
        return $request->attributes->get('empresa');
    }

    /** Barbeiros ativos que atendem, com os serviços que realizam. */
    private function barbeiros(): Collection
    {
        return Funcionario::query()
            ->where('ativo', true)
            ->where('atende_clientes', true)
            ->with(['servicos' => fn ($q) => $q->where('servicos.ativo', true)])
            ->orderBy('nome')
            ->get();
    }

    /** Barbeiros (ou o barbeiro escolhido) que fazem todos os serviços. */
    private function barbeirosQueFazem(array $servicoIds, ?int $funcionarioId): Collection
    {
        return $this->barbeiros()
            ->when($funcionarioId, fn ($c) => $c->where('id', $funcionarioId))
            ->filter(fn ($f) => collect($servicoIds)->diff($f->servicos->pluck('id'))->isEmpty())
            ->values();
    }

    private function dataPermitida(Empresa $empresa, string $data): bool
    {
        $hoje = now($empresa->fuso_horario)->toDateString();
        $limite = now($empresa->fuso_horario)->addDays(self::DIAS_ANTECEDENCIA)->toDateString();

        return $data >= $hoje && $data <= $limite;
    }

    /** Cliente pelo WhatsApp: reaproveita o cadastro existente (inclusive excluído). */
    private function cliente(array $dados): Cliente
    {
        $cliente = Cliente::withTrashed()->firstOrNew(['telefone' => $dados['telefone']]);
        if (! $cliente->exists || $cliente->trashed()) {
            $cliente->nome = $dados['nome'];
            $cliente->deleted_at = null;
            $cliente->save();
        }

        if (! empty($dados['aceita_whatsapp']) && ! $cliente->consentimentoWhatsapp?->aceito) {
            $cliente->consentimentos()->create([
                'canal' => 'whatsapp',
                'aceito' => true,
                'origem' => 'link_publico',
                'registrado_em' => now(),
            ]);
        }

        return $cliente;
    }

    /**
     * Horário de funcionamento: para cada dia, do primeiro início ao último fim
     * entre os barbeiros. Dias seguidos com o mesmo horário são agrupados ("Terça a Sexta").
     *
     * @return array<array{dias: string, horario: string}>
     */
    private function funcionamento(Collection $barbeiros): array
    {
        $porDia = [];
        foreach ($barbeiros->pluck('horariosTrabalho')->flatten() as $h) {
            $ini = substr($h->inicio, 0, 5);
            $fim = substr($h->fim, 0, 5);
            $atual = $porDia[$h->dia_semana] ?? [$ini, $fim];
            $porDia[$h->dia_semana] = [min($atual[0], $ini), max($atual[1], $fim)];
        }

        $linhas = [];
        // Semana começando na segunda, com o domingo no fim.
        foreach ([1, 2, 3, 4, 5, 6, 0] as $dia) {
            $horario = isset($porDia[$dia]) ? str_replace(':', 'h', "{$porDia[$dia][0]} às {$porDia[$dia][1]}") : 'Fechado';
            $ultima = array_key_last($linhas);
            if ($ultima !== null && $linhas[$ultima]['horario'] === $horario) {
                $linhas[$ultima]['ate'] = $dia;
            } else {
                $linhas[] = ['de' => $dia, 'ate' => $dia, 'horario' => $horario];
            }
        }

        return array_map(fn ($l) => [
            'dias' => $l['de'] === $l['ate']
                ? self::DIAS_SEMANA[$l['de']]
                : self::DIAS_SEMANA[$l['de']].' a '.self::DIAS_SEMANA[$l['ate']],
            'horario' => $l['horario'],
        ], $linhas);
    }

    private function endereco(Empresa $empresa): ?string
    {
        $rua = trim(implode(', ', array_filter([$empresa->logradouro, $empresa->numero, $empresa->complemento])));
        $cidade = trim(implode(' - ', array_filter([$empresa->cidade, $empresa->uf])));
        $partes = array_filter([$rua, $empresa->bairro, $cidade]);

        return $partes ? implode(' · ', $partes) : null;
    }

    /** Branco ou preto, o que tiver mais contraste sobre a cor principal. */
    private function corDoTexto(string $hex): string
    {
        [$r, $g, $b] = array_map(
            fn ($c) => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
            sscanf($hex, '#%02x%02x%02x'),
        );
        $luminancia = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        return $luminancia > 0.179 ? '#111111' : '#ffffff';
    }
}
