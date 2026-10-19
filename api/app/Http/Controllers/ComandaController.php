<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Comanda;
use App\Models\Pagamento;
use App\Services\Comandas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Comandas: o que o cliente consumiu (serviços e produtos) e como pagou.
 */
class ComandaController extends Controller
{
    public function __construct(private readonly Comandas $comandas) {}

    /** Lista comandas; ?status=aberta traz as que aguardam pagamento. */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', 'in:aberta,paga,cancelada']]);

        $comandas = Comanda::query()
            ->with(['cliente:id,nome,telefone', 'funcionario:id,nome', 'agendamento:id,inicio,fim'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json($comandas->map(fn ($c) => $this->resumo($c, $request)));
    }

    public function show(Request $request, Comanda $comanda): JsonResponse
    {
        return response()->json($this->completa($comanda, $request));
    }

    /** Venda avulsa (sem agendamento), por exemplo só produtos. */
    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'cliente_id' => ['nullable', 'integer', $this->existeNaEmpresa('clientes')],
            'funcionario_id' => ['nullable', 'integer', $this->existeNaEmpresa('funcionarios')],
        ]);

        $comanda = Comanda::create($dados + ['status' => 'aberta']);

        return response()->json($this->completa($comanda, $request), 201);
    }

    /** Comanda do atendimento (cria em aberto, com os serviços agendados, se ainda não existir). */
    public function doAgendamento(Request $request, Agendamento $agendamento): JsonResponse
    {
        if ($agendamento->status === 'cancelado') {
            return response()->json(['message' => 'Este agendamento foi cancelado.'], 422);
        }

        return response()->json($this->completa($this->comandas->abrirDoAgendamento($agendamento), $request));
    }

    /** Fecha a comanda: itens finais, desconto e pagamentos (soma = total). */
    public function fechar(Request $request, Comanda $comanda): JsonResponse
    {
        $dados = $request->validate([
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.tipo' => ['required', 'in:servico,produto'],
            'itens.*.servico_id' => ['required_if:itens.*.tipo,servico', 'nullable', 'integer', $this->existeNaEmpresa('servicos', softDelete: false)],
            'itens.*.produto_id' => ['required_if:itens.*.tipo,produto', 'nullable', 'integer', $this->existeNaEmpresa('produtos', softDelete: false)],
            'itens.*.funcionario_id' => ['nullable', 'integer', $this->existeNaEmpresa('funcionarios', softDelete: false)],
            'itens.*.quantidade' => ['required', 'integer', 'min:1', 'max:999'],
            'itens.*.preco_unitario' => ['required', 'numeric', 'min:0', 'max:99999'],
            'desconto' => ['nullable', 'numeric', 'min:0'],
            'pagamentos' => ['required', 'array', 'min:1'],
            'pagamentos.*.forma' => ['required', 'in:'.implode(',', Pagamento::FORMAS)],
            'pagamentos.*.valor' => ['required', 'numeric', 'min:0.01'],
            'observacoes' => ['nullable', 'string', 'max:500'],
        ], [], [
            'itens' => 'itens da comanda',
            'pagamentos' => 'pagamentos',
            'pagamentos.*.forma' => 'forma de pagamento',
        ]);

        $comanda = $this->comandas->fechar(
            $comanda,
            $dados['itens'],
            (float) ($dados['desconto'] ?? 0),
            $dados['pagamentos'],
            $request->user()->id,
            $dados['observacoes'] ?? null,
        );

        return response()->json($this->completa($comanda, $request));
    }

    public function cancelar(Request $request, Comanda $comanda): JsonResponse
    {
        if ($comanda->status !== 'aberta') {
            return response()->json(['message' => 'Só é possível cancelar comandas em aberto. Para comandas pagas, use o estorno.'], 422);
        }
        $comanda->update(['status' => 'cancelada']);

        return response()->json($this->completa($comanda, $request));
    }

    public function estornar(Request $request, Comanda $comanda): JsonResponse
    {
        return response()->json($this->completa($this->comandas->estornar($comanda), $request));
    }

    private function resumo(Comanda $c, Request $request): array
    {
        $fuso = $request->user()->empresa->fuso_horario;

        return [
            'id' => $c->id,
            'status' => $c->status,
            'total' => $c->total,
            'agendamento_id' => $c->agendamento_id,
            'horario' => $c->agendamento?->inicio?->copy()->setTimezone($fuso)->toIso8601String(),
            'pago_em' => $c->pago_em?->copy()->setTimezone($fuso)->toIso8601String(),
            'criada_em' => $c->created_at?->copy()->setTimezone($fuso)->toIso8601String(),
            'cliente' => $c->cliente?->only(['id', 'nome', 'telefone']),
            'barbeiro' => $c->funcionario?->only(['id', 'nome']),
        ];
    }

    private function completa(Comanda $c, Request $request): array
    {
        $c->load(['cliente:id,nome,telefone', 'funcionario:id,nome', 'agendamento:id,inicio,fim', 'itens', 'pagamentos']);

        return $this->resumo($c, $request) + [
            'subtotal' => $c->subtotal,
            'desconto' => $c->desconto,
            'observacoes' => $c->observacoes,
            'itens' => $c->itens->map(fn ($i) => $i->only([
                'id', 'tipo', 'servico_id', 'produto_id', 'funcionario_id', 'descricao', 'quantidade',
                'preco_unitario', 'total', 'comissao_percentual', 'comissao_valor',
            ]))->values(),
            'pagamentos' => $c->pagamentos->map(fn ($p) => $p->only(['id', 'forma', 'valor']))->values(),
        ];
    }
}
