<?php

namespace App\Http\Controllers;

use App\Models\BloqueioAgenda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Folgas, férias, intervalos e feriados.
 * Sem funcionario_id, o bloqueio vale para a barbearia inteira.
 * Datas trafegam em ISO 8601 e são gravadas em UTC.
 */
class BloqueioAgendaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'funcionario_id' => ['nullable', 'integer'],
            'inicio' => ['nullable', 'date'],
            'fim' => ['nullable', 'date'],
        ]);

        $bloqueios = BloqueioAgenda::query()
            ->with('funcionario:id,nome')
            ->when($request->filled('funcionario_id'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('funcionario_id', $request->integer('funcionario_id'))->orWhereNull('funcionario_id');
            }))
            ->when($request->filled('inicio'), fn ($q) => $q->where('fim', '>', Carbon::parse($request->input('inicio'))->utc()))
            ->when($request->filled('fim'), fn ($q) => $q->where('inicio', '<', Carbon::parse($request->input('fim'))->utc()))
            ->when(! $request->filled('inicio'), fn ($q) => $q->where('fim', '>', now()))
            ->orderBy('inicio')
            ->get();

        return response()->json($bloqueios);
    }

    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'funcionario_id' => ['nullable', 'integer', $this->existeNaEmpresa('funcionarios')],
            'inicio' => ['required', 'date'],
            'fim' => ['required', 'date', 'after:inicio'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ], [], ['funcionario_id' => 'barbeiro', 'inicio' => 'início']);

        $bloqueio = BloqueioAgenda::create([
            'funcionario_id' => $dados['funcionario_id'] ?? null,
            'inicio' => Carbon::parse($dados['inicio'])->utc(),
            'fim' => Carbon::parse($dados['fim'])->utc(),
            'motivo' => $dados['motivo'] ?? null,
        ]);

        return response()->json($bloqueio->load('funcionario:id,nome'), 201);
    }

    public function destroy(BloqueioAgenda $bloqueio): JsonResponse
    {
        $bloqueio->delete();

        return response()->json(null, 204);
    }
}
