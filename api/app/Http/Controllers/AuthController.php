<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Cadastro de uma nova barbearia com o usuário dono.
     */
    public function registrar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'empresa_nome' => ['required', 'string', 'max:120'],
            'empresa_telefone' => ['nullable', 'string', 'max:20'],
            'nome' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:usuarios,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], [
            'empresa_nome' => 'nome da barbearia',
            'empresa_telefone' => 'telefone da barbearia',
            'email' => 'e-mail',
            'password' => 'senha',
        ]);

        $usuario = DB::transaction(function () use ($dados) {
            $empresa = Empresa::create([
                'nome' => $dados['empresa_nome'],
                'slug' => Empresa::gerarSlug($dados['empresa_nome']),
                'telefone' => $dados['empresa_telefone'] ?? null,
                'email' => $dados['email'],
            ])->refresh(); // carrega os valores padrão do banco (ex.: fuso horário)

            return User::create([
                'empresa_id' => $empresa->id,
                'nome' => $dados['nome'],
                'email' => $dados['email'],
                'password' => $dados['password'],
                'perfil' => 'dono',
            ]);
        });

        return $this->respostaComToken($usuario, $request, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [], ['password' => 'senha']);

        $usuario = User::where('email', $dados['email'])->first();

        if (! $usuario || ! Hash::check($dados['password'], $usuario->password)) {
            throw ValidationException::withMessages(['email' => 'E-mail ou senha inválidos.']);
        }

        if (! $usuario->ativo || ! $usuario->empresa?->ativo) {
            throw ValidationException::withMessages(['email' => 'Acesso desativado. Fale com o responsável pela barbearia.']);
        }

        return $this->respostaComToken($usuario, $request);
    }

    public function eu(Request $request): JsonResponse
    {
        return response()->json($this->dadosSessao($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada.']);
    }

    private function respostaComToken(User $usuario, Request $request, int $status = 200): JsonResponse
    {
        $dispositivo = substr((string) $request->userAgent(), 0, 120) ?: 'api';
        $token = $usuario->createToken($dispositivo)->plainTextToken;

        return response()->json(['token' => $token] + $this->dadosSessao($usuario), $status);
    }

    private function dadosSessao(User $usuario): array
    {
        $empresa = $usuario->empresa;

        return [
            'usuario' => $usuario->only(['id', 'nome', 'email', 'perfil']),
            'empresa' => $empresa->only(['id', 'nome', 'slug', 'telefone', 'fuso_horario']),
        ];
    }
}
