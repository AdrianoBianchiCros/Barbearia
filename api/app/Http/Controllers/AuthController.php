<?php

namespace App\Http\Controllers;

use App\Models\Aceite;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as SenhaBroker;
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
            'aceite_termos' => ['accepted'],
        ], [
            'aceite_termos.accepted' => 'Para criar a conta, aceite os Termos de uso e a Política de privacidade.',
        ], [
            'empresa_nome' => 'nome da barbearia',
            'empresa_telefone' => 'telefone da barbearia',
            'email' => 'e-mail',
            'password' => 'senha',
        ]);

        $usuario = DB::transaction(function () use ($dados, $request) {
            $empresa = Empresa::create([
                'nome' => $dados['empresa_nome'],
                'slug' => Empresa::gerarSlug($dados['empresa_nome']),
                'telefone' => $dados['empresa_telefone'] ?? null,
                'email' => $dados['email'],
            ])->refresh(); // carrega os valores padrão do banco (ex.: fuso horário)

            $usuario = User::create([
                'empresa_id' => $empresa->id,
                'nome' => $dados['nome'],
                'email' => $dados['email'],
                'password' => $dados['password'],
                'perfil' => 'dono',
            ]);

            Aceite::registrar(['termos', 'privacidade'], ['empresa_id' => $empresa->id, 'usuario_id' => $usuario->id], 'cadastro_painel', $request);

            return $usuario;
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

    /**
     * Envia o link de redefinição de senha. A resposta é a mesma exista ou não o e-mail,
     * para não revelar quem tem conta no sistema.
     */
    public function esqueciSenha(Request $request): JsonResponse
    {
        $dados = $request->validate(['email' => ['required', 'email']], [], ['email' => 'e-mail']);

        $usuario = User::where('email', $dados['email'])->first();

        if ($usuario?->ativo && $usuario->empresa?->ativo) {
            try {
                SenhaBroker::sendResetLink(['email' => $usuario->email]);
            } catch (\Throwable $e) {
                // Falha no envio (ex.: SMTP recusou a senha) fica no log, mas a resposta não muda:
                // um erro só para e-mails cadastrados revelaria quem tem conta.
                report($e);
            }
        }

        return response()->json([
            'message' => 'Se esse e-mail estiver cadastrado, você vai receber em instantes um link para criar uma nova senha.',
        ]);
    }

    /**
     * Grava a nova senha a partir do link recebido por e-mail e encerra as sessões abertas.
     */
    public function redefinirSenha(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['email' => 'e-mail', 'password' => 'senha']);

        $status = SenhaBroker::reset($dados, function (User $usuario, string $senha) {
            $usuario->forceFill(['password' => $senha])->save();
            $usuario->tokens()->delete();
        });

        if ($status !== SenhaBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => 'Este link é inválido ou já expirou. Peça um novo em "Esqueci a senha".',
            ]);
        }

        return response()->json(['message' => 'Senha alterada. Entre com a nova senha.']);
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
