<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Models\ProdutoEntrada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Produtos vendidos na barbearia (pomada, shampoo, bebidas…) e suas compras. */
class ProdutoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $produtos = Produto::query()
            ->with(['ultimaEntrada', 'entradas'])
            ->when($request->boolean('ativos'), fn ($q) => $q->where('ativo', true))
            ->orderBy('nome')
            ->get();

        return response()->json($produtos->map(fn ($p) => $this->formatar($p)));
    }

    /** Cadastro do produto, com a primeira compra opcional (soma ao estoque). */
    public function store(Request $request): JsonResponse
    {
        $dados = $this->validar($request);
        $entrada = $request->filled('entrada.quantidade') ? $this->validarEntrada($request, 'entrada.') : null;

        $produto = DB::transaction(function () use ($dados, $entrada, $request) {
            $produto = Produto::create($dados);
            if ($entrada) {
                $this->registrarEntrada($produto, $entrada, $request->user()->id);
            }

            return $produto;
        });

        return response()->json($this->formatar($produto->fresh(['ultimaEntrada', 'entradas'])), 201);
    }

    public function update(Request $request, Produto $produto): JsonResponse
    {
        $produto->update($this->validar($request));

        return response()->json($this->formatar($produto->fresh(['ultimaEntrada', 'entradas'])));
    }

    public function destroy(Produto $produto): JsonResponse
    {
        $produto->delete();

        return response()->json(null, 204);
    }

    /** Histórico de compras do produto (mais recentes primeiro). */
    public function entradas(Produto $produto): JsonResponse
    {
        $saldos = $this->saldosPorLote($produto->load('entradas'));

        return response()->json(
            $produto->entradas()->with('usuario:id,nome')->orderByDesc('data_compra')->orderByDesc('id')->get()
                ->map(fn ($e) => $this->formatarEntrada($e) + ['em_estoque' => $saldos[$e->id] ?? 0]),
        );
    }

    /** Registra uma compra: soma a quantidade ao estoque e atualiza o custo, se informado. */
    public function novaEntrada(Request $request, Produto $produto): JsonResponse
    {
        $dados = $this->validarEntrada($request);

        $entrada = DB::transaction(fn () => $this->registrarEntrada($produto, $dados, $request->user()->id));

        return response()->json([
            'entrada' => $this->formatarEntrada($entrada->load('usuario:id,nome')),
            'produto' => $this->formatar($produto->fresh(['ultimaEntrada', 'entradas'])),
        ], 201);
    }

    /** Exclui uma compra lançada por engano, devolvendo a quantidade do estoque. */
    public function excluirEntrada(Produto $produto, ProdutoEntrada $entrada): JsonResponse
    {
        abort_unless($entrada->produto_id === $produto->id, 404);

        DB::transaction(function () use ($produto, $entrada) {
            Produto::whereKey($produto->id)->whereNotNull('estoque')->decrement('estoque', $entrada->quantidade);
            $entrada->delete();
        });

        return response()->json($this->formatar($produto->fresh(['ultimaEntrada', 'entradas'])));
    }

    /**
     * Quanto de cada compra ainda está no estoque, considerando que os produtos
     * mais antigos saem primeiro (PEPS): o estoque atual é formado pelas compras
     * mais recentes. Retorna [id da compra => quantidade restante].
     */
    private function saldosPorLote(Produto $produto): array
    {
        $restante = max((int) $produto->estoque, 0);
        $saldos = [];
        foreach ($produto->entradas->sortByDesc(fn ($e) => [$e->data_compra->timestamp, $e->id]) as $entrada) {
            if ($restante <= 0) {
                break;
            }
            $saldos[$entrada->id] = min($entrada->quantidade, $restante);
            $restante -= $saldos[$entrada->id];
        }

        return $saldos;
    }

    private function registrarEntrada(Produto $produto, array $dados, int $usuarioId): ProdutoEntrada
    {
        $produto = Produto::whereKey($produto->id)->lockForUpdate()->firstOrFail();

        $entrada = $produto->entradas()->create($dados + ['usuario_id' => $usuarioId]);

        // Produto sem controle de estoque passa a controlar a partir da primeira compra
        $produto->estoque = ($produto->estoque ?? 0) + $entrada->quantidade;
        if ($entrada->custo_unitario !== null) {
            $produto->custo = $entrada->custo_unitario;
        }
        $produto->save();

        return $entrada;
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'preco' => ['required', 'numeric', 'min:0', 'max:99999'],
            'custo' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'estoque' => ['nullable', 'integer', 'min:-9999', 'max:99999'],
            'comissao_percentual' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'ativo' => ['sometimes', 'boolean'],
        ], [], [
            'preco' => 'preço de venda',
            'comissao_percentual' => 'comissão',
        ]);
    }

    private function validarEntrada(Request $request, string $prefixo = ''): array
    {
        $regras = [
            'data_compra' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'fornecedor' => ['nullable', 'string', 'max:120'],
            'numero_nota' => ['nullable', 'string', 'max:60'],
            'lote' => ['nullable', 'string', 'max:60'],
            'quantidade' => ['required', 'integer', 'min:1', 'max:99999'],
            'custo_unitario' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'validade' => ['nullable', 'date_format:Y-m-d'],
        ];
        $nomes = [
            'data_compra' => 'data da compra',
            'numero_nota' => 'número da nota',
            'quantidade' => 'quantidade comprada',
            'custo_unitario' => 'custo unitário',
        ];

        $dados = $request->validate(
            collect($regras)->mapWithKeys(fn ($r, $c) => [$prefixo.$c => $r])->all(),
            [],
            collect($nomes)->mapWithKeys(fn ($n, $c) => [$prefixo.$c => $n])->all(),
        );
        $dados = $prefixo ? data_get($dados, rtrim($prefixo, '.')) : $dados;

        if (! empty($dados['validade']) && $dados['validade'] < $dados['data_compra']) {
            throw ValidationException::withMessages([$prefixo.'validade' => 'A validade não pode ser anterior à data da compra.']);
        }

        return $dados;
    }

    private function formatar(Produto $p): array
    {
        $ultima = $p->ultimaEntrada;
        $saldos = $this->saldosPorLote($p);
        $lotes = $p->entradas->filter(fn ($e) => isset($saldos[$e->id]))
            ->sortBy(fn ($e) => $e->validade?->timestamp ?? PHP_INT_MAX)
            ->map(fn ($e) => [
                'lote' => $e->lote,
                'validade' => $e->validade?->toDateString(),
                'quantidade' => $saldos[$e->id],
                'data_compra' => $e->data_compra->toDateString(),
            ])->values();

        return $p->only(['id', 'nome', 'preco', 'custo', 'estoque', 'comissao_percentual', 'ativo']) + [
            'ultima_compra' => $ultima ? [
                'data_compra' => $ultima->data_compra->toDateString(),
                'fornecedor' => $ultima->fornecedor,
                'numero_nota' => $ultima->numero_nota,
                'lote' => $ultima->lote,
                'validade' => $ultima->validade?->toDateString(),
            ] : null,
            // Lotes que ainda têm unidades em estoque, do que vence primeiro ao último
            'lotes_em_estoque' => $lotes,
            'proximo_vencimento' => $lotes->firstWhere('validade', '!=', null),
        ];
    }

    private function formatarEntrada(ProdutoEntrada $e): array
    {
        return [
            'id' => $e->id,
            'data_compra' => $e->data_compra->toDateString(),
            'fornecedor' => $e->fornecedor,
            'numero_nota' => $e->numero_nota,
            'lote' => $e->lote,
            'quantidade' => $e->quantidade,
            'custo_unitario' => $e->custo_unitario,
            'validade' => $e->validade?->toDateString(),
            'usuario' => $e->usuario?->nome,
        ];
    }
}
