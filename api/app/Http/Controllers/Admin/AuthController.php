<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Login do administrador da plataforma (separado do login das barbearias).
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [], ['password' => 'senha']);

        $admin = Administrador::where('email', $dados['email'])->first();

        if (! $admin || ! $admin->ativo || ! Hash::check($dados['password'], $admin->password)) {
            throw ValidationException::withMessages(['email' => 'E-mail ou senha inválidos.']);
        }

        $token = $admin->createToken('admin')->plainTextToken;

        return response()->json(['token' => $token, 'admin' => $admin->only(['id', 'nome', 'email'])]);
    }

    public function eu(Request $request): JsonResponse
    {
        return response()->json(['admin' => $request->user()->only(['id', 'nome', 'email'])]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada.']);
    }
}
