<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrador;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as SenhaBroker;
use Illuminate\Validation\Rules\Password;
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

    /**
     * Envia o link de redefinição para o e-mail do administrador.
     * Responde igual exista ou não o e-mail: não revela quem administra a plataforma.
     */
    public function esqueciSenha(Request $request): JsonResponse
    {
        $dados = $request->validate(['email' => ['required', 'email']], [], ['email' => 'e-mail']);

        try {
            $status = $this->senhas()->sendResetLink(['email' => $dados['email'], 'ativo' => true]);
        } catch (\Throwable $e) {
            report($e); // ex.: o servidor de e-mail recusou o login (SMTP 535)

            return response()->json([
                'message' => 'Não foi possível enviar o e-mail agora. Tente novamente em alguns minutos.',
            ], 503);
        }

        if ($status === PasswordBroker::RESET_THROTTLED) {
            throw ValidationException::withMessages(['email' => 'Já enviamos um link há pouco. Aguarde um minuto para pedir outro.']);
        }

        return response()->json([
            'message' => 'Se o e-mail for de um administrador, enviamos um link para criar uma nova senha. Confira sua caixa de entrada.',
        ]);
    }

    /** Grava a nova senha a partir do link do e-mail e encerra as sessões abertas. */
    public function redefinirSenha(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['email' => 'e-mail', 'password' => 'senha']);

        $status = $this->senhas()->reset($dados + ['ativo' => true], function (Administrador $admin, string $senha) {
            $admin->forceFill(['password' => $senha])->save();
            $admin->tokens()->delete();
        });

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => 'Este link é inválido ou já expirou. Peça um novo em "Esqueci a senha".',
            ]);
        }

        return response()->json(['message' => 'Senha alterada. Entre com a nova senha.']);
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

    private function senhas(): PasswordBroker
    {
        return SenhaBroker::broker('administradores');
    }
}
