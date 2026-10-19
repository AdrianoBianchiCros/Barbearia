<?php

namespace App\Http\Controllers;

use App\Models\Caixa;
use App\Models\Comanda;
use App\Models\Pagamento;
use App\Services\Comandas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Caixa do dia: abertura, suprimentos/sangrias, recebimentos e fechamento com conferência.
 */
class CaixaController extends Controller
{
    public function __construct(private readonly Comandas $comandas) {}

    /** Caixa aberto (ou null), com resumo, movimentos e recebimentos. */
    public function atual(Request $request): JsonResponse
    {
        $caixa = $this->comandas->caixaAberto();

        return response()->json([
            'caixa' => $caixa ? $this->detalhado($caixa, $request) : null,
            'comandas_abertas' => Comanda::where('status', 'aberta')->count(),
        ]);
    }

    public function abrir(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'valor_inicial' => ['required', 'numeric', 'min:0', 'max:999999'],
        ], [], ['valor_inicial' => 'valor inicial (troco)']);

        $caixa = DB::transaction(function () use ($request, $dados) {
            if (Caixa::whereNull('fechado_em')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['caixa' => 'Já existe um caixa aberto.']);
            }

            return Caixa::create([
                'aberto_por' => $request->user()->id,
                'aberto_em' => now(),
                'valor_inicial' => $dados['valor_inicial'],
            ]);
        });

        return response()->json($this->detalhado($caixa, $request), 201);
    }

    public function movimento(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'tipo' => ['required', 'in:suprimento,sangria'],
            'valor' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'descricao' => ['nullable', 'string', 'max:255'],
        ]);

        $caixa = $this->exigirAberto();
        if ($dados['tipo'] === 'sangria' && $dados['valor'] > $this->resumo($caixa)['dinheiro_esperado'] + 0.009) {
            throw ValidationException::withMessages(['valor' => 'A sangria é maior que o dinheiro disponível no caixa.']);
        }

        $caixa->movimentos()->create($dados + ['usuario_id' => $request->user()->id]);

        return response()->json($this->detalhado($caixa->fresh(), $request), 201);
    }

    public function fechar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'valor_contado' => ['required', 'numeric', 'min:0', 'max:999999'],
            'observacoes' => ['nullable', 'string', 'max:500'],
        ], [], ['valor_contado' => 'dinheiro contado']);

        $caixa = $this->exigirAberto();
        $caixa->update([
            'fechado_por' => $request->user()->id,
            'fechado_em' => now(),
            'valor_contado' => $dados['valor_contado'],
            'observacoes' => $dados['observacoes'] ?? null,
        ]);

        return response()->json($this->detalhado($caixa, $request));
    }

    /** Histórico de caixas (mais recentes primeiro). */
    public function index(Request $request): JsonResponse
    {
        $caixas = Caixa::with(['abertoPor:id,nome', 'fechadoPor:id,nome'])->latest('aberto_em')->paginate(20);
        $caixas->getCollection()->transform(fn ($c) => $this->cabecalho($c, $request) + ['resumo' => $this->resumo($c)]);

        return response()->json($caixas);
    }

    public function show(Request $request, Caixa $caixa): JsonResponse
    {
        return response()->json($this->detalhado($caixa, $request));
    }

    private function exigirAberto(): Caixa
    {
        return $this->comandas->caixaAberto()
            ?? throw ValidationException::withMessages(['caixa' => 'Nenhum caixa aberto.']);
    }

    /**
     * Totais do caixa. Dinheiro esperado = inicial + recebido em dinheiro + suprimentos
     * − sangrias − despesas pagas com dinheiro do caixa.
     */
    private function resumo(Caixa $caixa): array
    {
        $porForma = Pagamento::where('caixa_id', $caixa->id)
            ->selectRaw('forma, SUM(valor) as total')
            ->groupBy('forma')
            ->pluck('total', 'forma');
        $formas = collect(Pagamento::FORMAS)->mapWithKeys(fn ($f) => [$f => round((float) ($porForma[$f] ?? 0), 2)]);

        $suprimentos = (float) $caixa->movimentos()->where('tipo', 'suprimento')->sum('valor');
        $sangrias = (float) $caixa->movimentos()->where('tipo', 'sangria')->sum('valor');
        $saidas = (float) $caixa->lancamentos()->sum('valor');
        $esperado = round((float) $caixa->valor_inicial + $formas['dinheiro'] + $suprimentos - $sangrias - $saidas, 2);

        return [
            'valor_inicial' => (float) $caixa->valor_inicial,
            'recebido_por_forma' => $formas,
            'total_recebido' => round($formas->sum(), 2),
            'atendimentos' => Comanda::where('caixa_id', $caixa->id)->where('status', 'paga')->count(),
            'suprimentos' => round($suprimentos, 2),
            'sangrias' => round($sangrias, 2),
            'despesas_pagas' => round($saidas, 2),
            'dinheiro_esperado' => $esperado,
            'valor_contado' => $caixa->valor_contado !== null ? (float) $caixa->valor_contado : null,
            'diferenca' => $caixa->valor_contado !== null ? round((float) $caixa->valor_contado - $esperado, 2) : null,
        ];
    }

    private function cabecalho(Caixa $caixa, Request $request): array
    {
        $fuso = $request->user()->empresa->fuso_horario;
        $caixa->loadMissing(['abertoPor:id,nome', 'fechadoPor:id,nome']);

        return [
            'id' => $caixa->id,
            'aberto' => $caixa->estaAberto(),
            'aberto_em' => $caixa->aberto_em->copy()->setTimezone($fuso)->toIso8601String(),
            'fechado_em' => $caixa->fechado_em?->copy()->setTimezone($fuso)->toIso8601String(),
            'aberto_por' => $caixa->abertoPor?->nome,
            'fechado_por' => $caixa->fechadoPor?->nome,
            'observacoes' => $caixa->observacoes,
        ];
    }

    private function detalhado(Caixa $caixa, Request $request): array
    {
        $fuso = $request->user()->empresa->fuso_horario;
        $hora = fn ($dt) => $dt?->copy()->setTimezone($fuso)->toIso8601String();

        return $this->cabecalho($caixa, $request) + [
            'resumo' => $this->resumo($caixa),
            'movimentos' => $caixa->movimentos()->with('usuario:id,nome')->latest('id')->get()->map(fn ($m) => [
                'id' => $m->id,
                'tipo' => $m->tipo,
                'valor' => $m->valor,
                'descricao' => $m->descricao,
                'usuario' => $m->usuario?->nome,
                'em' => $hora($m->created_at),
            ]),
            'recebimentos' => Comanda::with(['cliente:id,nome', 'funcionario:id,nome', 'pagamentos'])
                ->where('caixa_id', $caixa->id)
                ->where('status', 'paga')
                ->latest('pago_em')
                ->get()
                ->map(fn ($c) => [
                    'comanda_id' => $c->id,
                    'cliente' => $c->cliente?->nome,
                    'barbeiro' => $c->funcionario?->nome,
                    'total' => $c->total,
                    'formas' => $c->pagamentos->pluck('forma')->unique()->values(),
                    'pago_em' => $hora($c->pago_em),
                ]),
        ];
    }
}
