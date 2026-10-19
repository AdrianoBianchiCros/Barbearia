<?php

namespace App\Http\Controllers;

use App\Models\Servico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServicoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $servicos = Servico::query()
            ->when($request->boolean('ativos'), fn ($q) => $q->where('ativo', true))
            ->orderBy('nome')
            ->get();

        return response()->json($servicos);
    }

    public function store(Request $request): JsonResponse
    {
        $servico = Servico::create($this->validar($request));

        return response()->json($servico, 201);
    }

    public function show(Servico $servico): JsonResponse
    {
        return response()->json($servico);
    }

    public function update(Request $request, Servico $servico): JsonResponse
    {
        $servico->update($this->validar($request));

        return response()->json($servico);
    }

    public function destroy(Servico $servico): JsonResponse
    {
        $servico->delete();

        return response()->json(null, 204);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'descricao' => ['nullable', 'string', 'max:500'],
            'duracao_minutos' => ['required', 'integer', 'min:5', 'max:480'],
            'preco' => ['required', 'numeric', 'min:0', 'max:99999'],
            'ativo' => ['sometimes', 'boolean'],
        ], [], [
            'duracao_minutos' => 'duração',
            'preco' => 'preço',
            'descricao' => 'descrição',
        ]);
    }
}
