<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dados da barbearia do usuário logado.
 */
class EmpresaController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user()->empresa);
    }

    public function update(Request $request): JsonResponse
    {
        $empresa = $request->user()->empresa;

        $dados = $request->validate([
            'nome' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => ['sometimes', 'required', 'alpha_dash:ascii', 'max:80', Rule::unique('empresas', 'slug')->ignore($empresa->id)],
            'documento' => ['nullable', 'string', 'max:20'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:180'],
            'fuso_horario' => ['sometimes', 'required', 'timezone:all'],
        ], [], ['slug' => 'endereço do link de agendamento']);

        $empresa->update($dados);

        return response()->json($empresa);
    }
}
