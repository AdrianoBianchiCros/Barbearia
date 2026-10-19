<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Produtos vendidos na barbearia (pomada, shampoo, bebidas…). */
class ProdutoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Produto::query()
                ->when($request->boolean('ativos'), fn ($q) => $q->where('ativo', true))
                ->orderBy('nome')
                ->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Produto::create($this->validar($request)), 201);
    }

    public function update(Request $request, Produto $produto): JsonResponse
    {
        $produto->update($this->validar($request));

        return response()->json($produto);
    }

    public function destroy(Produto $produto): JsonResponse
    {
        $produto->delete();

        return response()->json(null, 204);
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
}
