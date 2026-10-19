<?php

namespace App\Http\Controllers;

use App\Models\Lancamento;
use App\Services\Comandas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Despesas (contas a pagar), vales/adiantamentos de barbeiros e repasses de comissão.
 */
class LancamentoController extends Controller
{
    public const CATEGORIAS = ['aluguel', 'produtos', 'contas', 'salarios', 'impostos', 'manutencao', 'marketing', 'outros'];

    public function __construct(private readonly Comandas $comandas) {}

    /**
     * ?tipo=despesa&mes=2026-10. Despesas são filtradas pelo vencimento;
     * vales e repasses, pela data do pagamento.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'tipo' => ['required', 'in:despesa,vale,repasse_comissao'],
            'mes' => ['nullable', 'date_format:Y-m'],
            'funcionario_id' => ['nullable', 'integer'],
        ]);

        $fuso = $request->user()->empresa->fuso_horario;
        $mes = Carbon::createFromFormat('Y-m', $request->input('mes', now($fuso)->format('Y-m')), $fuso)->startOfMonth();
        $tipo = $request->input('tipo');

        $lista = Lancamento::query()
            ->with('funcionario:id,nome')
            ->where('tipo', $tipo)
            ->when($request->filled('funcionario_id'), fn ($q) => $q->where('funcionario_id', $request->integer('funcionario_id')))
            ->when(
                $tipo === 'despesa',
                fn ($q) => $q->whereBetween('vencimento', [$mes->toDateString(), $mes->copy()->endOfMonth()->toDateString()])
                    ->orderBy('vencimento'),
                fn ($q) => $q->whereBetween('pago_em', [$mes->copy()->utc(), $mes->copy()->addMonth()->utc()])
                    ->orderByDesc('pago_em'),
            )
            ->get();

        return response()->json([
            'mes' => $mes->format('Y-m'),
            'total' => round((float) $lista->sum('valor'), 2),
            'total_pago' => round((float) $lista->whereNotNull('pago_em')->sum('valor'), 2),
            'lancamentos' => $lista->map(fn ($l) => $this->formatar($l, $fuso)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'tipo' => ['required', 'in:despesa,vale,repasse_comissao'],
            'descricao' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'in:'.implode(',', self::CATEGORIAS)],
            'valor' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'funcionario_id' => ['required_unless:tipo,despesa', 'nullable', 'integer', $this->existeNaEmpresa('funcionarios')],
            'vencimento' => ['nullable', 'date_format:Y-m-d'],
            'pago' => ['sometimes', 'boolean'],
            'pagar_com_caixa' => ['sometimes', 'boolean'],
        ], [], [
            'funcionario_id' => 'barbeiro',
            'descricao' => 'descrição',
        ]);

        $fuso = $request->user()->empresa->fuso_horario;
        // Vale e repasse são pagos na hora; despesa pode ficar a pagar
        $pago = $dados['tipo'] !== 'despesa' || ($dados['pago'] ?? false);

        $lancamento = Lancamento::create([
            'tipo' => $dados['tipo'],
            'descricao' => $dados['descricao'],
            'categoria' => $dados['tipo'] === 'despesa' ? ($dados['categoria'] ?? 'outros') : null,
            'valor' => $dados['valor'],
            'funcionario_id' => $dados['funcionario_id'] ?? null,
            'vencimento' => $dados['vencimento'] ?? now($fuso)->toDateString(),
            'pago_em' => $pago ? now() : null,
            'caixa_id' => $pago && ($dados['pagar_com_caixa'] ?? false) ? $this->caixaParaPagar((float) $dados['valor']) : null,
            'usuario_id' => $request->user()->id,
        ]);

        return response()->json($this->formatar($lancamento->load('funcionario:id,nome'), $fuso), 201);
    }

    /** Marca uma despesa como paga (opcionalmente com o dinheiro do caixa). */
    public function pagar(Request $request, Lancamento $lancamento): JsonResponse
    {
        $dados = $request->validate(['pagar_com_caixa' => ['sometimes', 'boolean']]);

        if ($lancamento->pago_em) {
            throw ValidationException::withMessages(['lancamento' => 'Este lançamento já está pago.']);
        }

        $lancamento->update([
            'pago_em' => now(),
            'caixa_id' => ($dados['pagar_com_caixa'] ?? false) ? $this->caixaParaPagar((float) $lancamento->valor) : null,
        ]);

        return response()->json($this->formatar($lancamento->load('funcionario:id,nome'), $request->user()->empresa->fuso_horario));
    }

    public function destroy(Lancamento $lancamento): JsonResponse
    {
        if ($lancamento->caixa_id && ! $lancamento->caixa()->whereNull('fechado_em')->exists()) {
            throw ValidationException::withMessages(['lancamento' => 'Saiu de um caixa já fechado e não pode ser excluído.']);
        }
        $lancamento->delete();

        return response()->json(null, 204);
    }

    private function caixaParaPagar(float $valor): int
    {
        $caixa = $this->comandas->caixaAberto()
            ?? throw ValidationException::withMessages(['pagar_com_caixa' => 'Não há caixa aberto para retirar o dinheiro.']);

        return $caixa->id;
    }

    private function formatar(Lancamento $l, string $fuso): array
    {
        return [
            'id' => $l->id,
            'tipo' => $l->tipo,
            'categoria' => $l->categoria,
            'descricao' => $l->descricao,
            'valor' => $l->valor,
            'funcionario' => $l->funcionario?->only(['id', 'nome']),
            'vencimento' => $l->vencimento?->toDateString(),
            'pago_em' => $l->pago_em?->copy()->setTimezone($fuso)->toIso8601String(),
            'do_caixa' => $l->caixa_id !== null,
            'atrasada' => $l->tipo === 'despesa' && ! $l->pago_em && $l->vencimento?->lt(now($fuso)->startOfDay()),
        ];
    }
}
