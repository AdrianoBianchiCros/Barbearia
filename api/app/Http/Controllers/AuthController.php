<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as SenhaBroker;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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

        if (! $usuario->empresa->acessoLiberado()) {
            throw ValidationException::withMessages(['email' => $usuario->empresa->mensagemBloqueio()]);
        }

        return $this->respostaComToken($usuario, $request);
    }

    /**
     * Envia o link de redefinição de senha para um e-mail cadastrado e ativo.
     * A resposta é a mesma exista ou não o e-mail, para não revelar quem tem cadastro.
     */
    public function esqueciSenha(Request $request): JsonResponse
    {
        $dados = $request->validate(['email' => ['required', 'email']], [], ['email' => 'e-mail']);
        $resposta = response()->json([
            'message' => 'Se este e-mail estiver cadastrado, enviamos um link para criar uma nova senha. Confira sua caixa de entrada e o spam.',
        ]);

        $usuario = User::where('email', $dados['email'])->first();
        if (! $usuario || ! $usuario->ativo || ! $usuario->empresa?->ativo) {
            return $resposta;
        }

        try {
            SenhaBroker::sendResetLink(['email' => $usuario->email]);
        } catch (\Throwable $e) {
            report($e); // ex.: o servidor de e-mail recusou o login (SMTP 535)

            return response()->json([
                'message' => 'Não foi possível enviar o e-mail agora. Tente novamente em alguns minutos.',
            ], 503);
        }

        // Pedido repetido em menos de 1 minuto (RESET_THROTTLED): o link anterior continua valendo.
        return $resposta;
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
            $usuario->forceFill(['password' => $senha, 'trocar_senha' => false])->save();
            $usuario->tokens()->delete();
        });

        if ($status !== SenhaBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => 'Este link é inválido ou já expirou. Peça um novo em "Esqueci a senha".',
            ]);
        }

        return response()->json(['message' => 'Senha alterada. Entre com a nova senha.']);
    }

    /**
     * Troca da senha provisória (gerada pelo administrador) pela senha do próprio usuário.
     * Mantém a sessão atual e encerra as demais.
     */
    public function trocarSenha(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $dados = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['password' => 'senha']);

        if (Hash::check($dados['password'], $usuario->password)) {
            throw ValidationException::withMessages(['password' => 'A nova senha precisa ser diferente da senha provisória.']);
        }

        $usuario->forceFill(['password' => $dados['password'], 'trocar_senha' => false])->save();
        $usuario->tokens()->whereKeyNot($usuario->currentAccessToken()->getKey())->delete();

        return response()->json(['message' => 'Senha alterada.'] + $this->dadosSessao($usuario));
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
            'usuario' => $usuario->only(['id', 'nome', 'email', 'perfil', 'trocar_senha']),
            // logo_url: aparece no topo do menu; favicon_url: o painel usa o mesmo ícone da página da barbearia.
            'empresa' => $empresa->only(['id', 'nome', 'slug', 'telefone', 'fuso_horario']) + ['favicon_url' => $empresa->favicon_url, 'logo_url' => $empresa->logo_url],
            // Prazo do acesso pago: o painel mostra o aviso de vencimento só para o dono.
            'acesso' => $usuario->temPerfil('dono') ? $empresa->resumoAcesso() : null,
        ];
    }
}
