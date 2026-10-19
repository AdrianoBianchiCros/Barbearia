<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Funcionario;
use App\Services\Disponibilidade;
use App\Support\Telefone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Criação e remarcação de agendamentos pela equipe (recepção, dono e barbeiro).
 */
class AgendamentoController extends Controller
{
    /**
     * Horários livres para os serviços escolhidos, por barbeiro.
     * GET /agenda/horarios?data=2026-10-20&servico_ids[]=1&servico_ids[]=2[&funcionario_id=3][&ignorar_agendamento_id=9]
     */
    public function horarios(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'data' => ['required', 'date_format:Y-m-d'],
            'servico_ids' => ['required', 'array', 'min:1'],
            'servico_ids.*' => ['integer', 'distinct'],
            'funcionario_id' => ['nullable', 'integer'],
            'ignorar_agendamento_id' => ['nullable', 'integer'],
        ]);

        $disponibilidade = $this->disponibilidade($request);
        $servicoIds = array_map('intval', $dados['servico_ids']);

        $barbeiros = Funcionario::query()
            ->where('ativo', true)
            ->where('atende_clientes', true)
            ->when($dados['funcionario_id'] ?? null, fn ($q, $id) => $q->where('id', $id))
            ->with('servicos')
            ->orderBy('nome')
            ->get()
            // Só quem faz todos os serviços escolhidos
            ->filter(fn ($f) => collect($servicoIds)->diff($f->servicos->pluck('id'))->isEmpty());

        return response()->json($barbeiros->map(function (Funcionario $f) use ($servicoIds, $dados, $disponibilidade) {
            $itens = $this->itens($f, $servicoIds);

            return [
                'funcionario_id' => $f->id,
                'nome' => $f->nome,
                'foto_url' => $f->foto_url,
                'duracao_minutos' => $itens->sum('duracao_minutos'),
                'valor_total' => number_format($itens->sum('preco'), 2, '.', ''),
                'horarios' => $disponibilidade->horariosLivres(
                    $f,
                    $dados['data'],
                    $itens->sum('duracao_minutos'),
                    $dados['ignorar_agendamento_id'] ?? null,
                ),
            ];
        })->values());
    }

    /**
     * Encaixe: primeiro horário sem cliente para os serviços escolhidos.
     * Sem barbeiro informado, escolhe quem consegue atender mais cedo.
     * GET /agenda/encaixe?data=2026-10-20&servico_ids[]=1[&funcionario_id=3][&ignorar_agendamento_id=9]
     */
    public function encaixe(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'data' => ['required', 'date_format:Y-m-d'],
            'servico_ids' => ['required', 'array', 'min:1'],
            'servico_ids.*' => ['integer', 'distinct'],
            'funcionario_id' => ['nullable', 'integer'],
            'ignorar_agendamento_id' => ['nullable', 'integer'],
        ]);

        $disponibilidade = $this->disponibilidade($request);
        $servicoIds = array_map('intval', $dados['servico_ids']);

        $opcoes = Funcionario::query()
            ->where('ativo', true)
            ->where('atende_clientes', true)
            ->when($dados['funcionario_id'] ?? null, fn ($q, $id) => $q->where('id', $id))
            ->with('servicos')
            ->orderBy('nome')
            ->get()
            ->filter(fn ($f) => collect($servicoIds)->diff($f->servicos->pluck('id'))->isEmpty())
            ->map(function (Funcionario $f) use ($servicoIds, $dados, $disponibilidade) {
                $itens = $this->itens($f, $servicoIds);
                $hora = $disponibilidade->primeiroEncaixe($f, $dados['data'], $itens->sum('duracao_minutos'), $dados['ignorar_agendamento_id'] ?? null);

                return $hora === null ? null : [
                    'funcionario_id' => $f->id,
                    'nome' => $f->nome,
                    'hora' => $hora,
                    'duracao_minutos' => $itens->sum('duracao_minutos'),
                    'valor_total' => number_format($itens->sum('preco'), 2, '.', ''),
                ];
            })
            ->filter()
            ->sortBy('hora')
            ->values();

        if ($opcoes->isEmpty()) {
            return response()->json(['message' => 'Não há horário livre para encaixe neste dia.'], 422);
        }

        return response()->json($opcoes->first());
    }

    public function store(Request $request): JsonResponse
    {
        $dados = $this->validar($request, novo: true);
        $barbeiro = $this->barbeiroPermitido($request, (int) $dados['funcionario_id']);

        $agendamento = DB::transaction(function () use ($request, $dados, $barbeiro) {
            $cliente = $this->clienteDoPedido($dados);
            $itens = $this->itensOuErro($barbeiro, $dados['servico_ids']);

            return $this->gravar(new Agendamento([
                'cliente_id' => $cliente->id,
                'status' => 'confirmado',
                'origem' => 'recepcao',
            ]), $request, $barbeiro, $dados, $itens);
        });

        return response()->json($this->resposta($agendamento), 201);
    }

    /** Remarcar: muda horário, barbeiro e/ou serviços de um agendamento ainda não atendido. */
    public function update(Request $request, Agendamento $agendamento): JsonResponse
    {
        if (! in_array($agendamento->status, ['agendado', 'confirmado'], true)) {
            return response()->json(['message' => 'Só é possível remarcar agendamentos que ainda não foram atendidos.'], 422);
        }

        $this->barbeiroPermitido($request, $agendamento->funcionario_id);
        $dados = $this->validar($request, novo: false);
        $barbeiro = $this->barbeiroPermitido($request, (int) $dados['funcionario_id']);

        $agendamento = DB::transaction(function () use ($request, $agendamento, $dados, $barbeiro) {
            $itens = $this->itensOuErro($barbeiro, $dados['servico_ids']);
            $agendamento->itens()->delete();

            return $this->gravar($agendamento, $request, $barbeiro, $dados, $itens);
        });

        return response()->json($this->resposta($agendamento));
    }

    private function validar(Request $request, bool $novo): array
    {
        if ($request->has('novo_cliente.telefone')) {
            $request->merge(['novo_cliente' => [
                ...$request->input('novo_cliente'),
                'telefone' => Telefone::normalizar($request->input('novo_cliente.telefone')),
            ]]);
        }

        return $request->validate([
            'cliente_id' => $novo
                ? ['required_without:novo_cliente', 'nullable', 'integer', $this->existeNaEmpresa('clientes')]
                : ['prohibited'],
            'novo_cliente' => $novo ? ['required_without:cliente_id', 'nullable', 'array'] : ['prohibited'],
            'novo_cliente.nome' => ['required_with:novo_cliente', 'string', 'max:120'],
            'novo_cliente.telefone' => ['required_with:novo_cliente', Telefone::REGRA],
            'novo_cliente.aceita_whatsapp' => ['sometimes', 'boolean'],
            'funcionario_id' => ['required', 'integer', $this->existeNaEmpresa('funcionarios')],
            'servico_ids' => ['required', 'array', 'min:1'],
            'servico_ids.*' => ['integer', 'distinct', $this->existeNaEmpresa('servicos')],
            'data' => ['required', 'date_format:Y-m-d'],
            'hora' => ['required', 'date_format:H:i'],
            'observacoes' => ['nullable', 'string', 'max:500'],
            'encaixe' => ['sometimes', 'boolean'],
        ], [
            'novo_cliente.telefone.regex' => 'Informe o WhatsApp com DDD (10 ou 11 dígitos).',
            'cliente_id.required_without' => 'Escolha um cliente ou cadastre um novo.',
        ], [
            'funcionario_id' => 'barbeiro',
            'servico_ids' => 'serviços',
            'novo_cliente.nome' => 'nome do cliente',
            'novo_cliente.telefone' => 'WhatsApp do cliente',
        ]);
    }

    /** Barbeiro só agenda na própria coluna; dono e recepção, em qualquer uma. */
    private function barbeiroPermitido(Request $request, int $funcionarioId): Funcionario
    {
        $barbeiro = Funcionario::findOrFail($funcionarioId);
        $usuario = $request->user();

        if ($usuario->perfil === 'barbeiro' && $barbeiro->usuario_id !== $usuario->id) {
            abort(response()->json(['message' => 'Você só pode agendar na sua própria agenda.'], 403));
        }

        return $barbeiro;
    }

    /** Usa o cliente informado ou cria um novo (reaproveitando se o telefone já existir). */
    private function clienteDoPedido(array $dados): Cliente
    {
        if (! empty($dados['cliente_id'])) {
            return Cliente::findOrFail($dados['cliente_id']);
        }

        $novo = $dados['novo_cliente'];
        // Inclui excluídos: o telefone é único por barbearia mesmo para clientes removidos.
        $cliente = Cliente::withTrashed()->firstOrNew(['telefone' => $novo['telefone']]);
        if (! $cliente->exists || $cliente->trashed()) {
            $cliente->nome = $novo['nome'];
            $cliente->deleted_at = null;
            $cliente->save();
        }

        if (! empty($novo['aceita_whatsapp']) && ! $cliente->consentimentoWhatsapp()->exists()) {
            $cliente->consentimentos()->create([
                'canal' => 'whatsapp',
                'aceito' => true,
                'origem' => 'recepcao',
                'registrado_em' => now(),
            ]);
        }

        return $cliente;
    }

    /** Serviços com o preço/duração do barbeiro (ou o padrão do serviço). */
    private function itens(Funcionario $barbeiro, array $servicoIds): Collection
    {
        return $barbeiro->servicos
            ->whereIn('id', $servicoIds)
            ->sortBy(fn ($s) => array_search($s->id, $servicoIds))
            ->map(fn ($s) => [
                'servico_id' => $s->id,
                'nome' => $s->nome,
                'preco' => (float) ($s->pivot->preco ?? $s->preco),
                'duracao_minutos' => (int) ($s->pivot->duracao_minutos ?? $s->duracao_minutos),
            ])
            ->values();
    }

    private function itensOuErro(Funcionario $barbeiro, array $servicoIds): Collection
    {
        $barbeiro->load('servicos');
        $itens = $this->itens($barbeiro, array_map('intval', $servicoIds));

        if ($itens->count() !== count($servicoIds)) {
            throw ValidationException::withMessages([
                'servico_ids' => "{$barbeiro->nome} não realiza todos os serviços escolhidos.",
            ]);
        }

        return $itens;
    }

    /**
     * Confere a disponibilidade e grava. A linha do barbeiro é travada durante a
     * transação para que dois pedidos simultâneos não peguem o mesmo horário.
     */
    private function gravar(Agendamento $agendamento, Request $request, Funcionario $barbeiro, array $dados, Collection $itens): Agendamento
    {
        Funcionario::whereKey($barbeiro->id)->lockForUpdate()->first();

        $duracao = $itens->sum('duracao_minutos');
        $encaixe = ($dados['encaixe'] ?? false) && $request->user()->perfil !== 'barbeiro';
        $motivo = $this->disponibilidade($request)->motivoIndisponivel(
            $barbeiro, $dados['data'], $dados['hora'], $duracao, $encaixe, $agendamento->id,
        );
        if ($motivo) {
            throw ValidationException::withMessages(['hora' => $motivo]);
        }

        $inicio = $this->disponibilidade($request)->instante($dados['data'], $dados['hora']);
        $agendamento->fill([
            'funcionario_id' => $barbeiro->id,
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes($duracao),
            'valor_total' => $itens->sum('preco'),
            'observacoes' => $dados['observacoes'] ?? null,
        ])->save();

        foreach ($itens as $item) {
            $agendamento->itens()->create($item);
        }

        return $agendamento->load('cliente:id,nome,telefone', 'itens');
    }

    private function disponibilidade(Request $request): Disponibilidade
    {
        return new Disponibilidade($request->user()->empresa->fuso_horario);
    }

    private function resposta(Agendamento $a): array
    {
        $fuso = request()->user()->empresa->fuso_horario;

        return [
            'id' => $a->id,
            'funcionario_id' => $a->funcionario_id,
            'inicio' => $a->inicio->copy()->setTimezone($fuso)->toIso8601String(),
            'fim' => $a->fim->copy()->setTimezone($fuso)->toIso8601String(),
            'status' => $a->status,
            'valor_total' => $a->valor_total,
            'cliente' => $a->cliente?->only(['id', 'nome', 'telefone']),
            'servicos' => $a->itens->pluck('nome')->values(),
        ];
    }
}
