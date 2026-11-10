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
     * Envia o link de redefinição de senha para um e-mail cadastrado.
     * Avisa quando o e-mail não existe (decisão do produto; o limite "senha" segura tentativas em massa).
     */
    public function esqueciSenha(Request $request): JsonResponse
    {
        $dados = $request->validate(['email' => ['required', 'email']], [], ['email' => 'e-mail']);

        $usuario = User::where('email', $dados['email'])->first();

        if (! $usuario) {
            throw ValidationException::withMessages(['email' => 'E-mail não encontrado para recuperar a senha.']);
        }

        if (! $usuario->ativo || ! $usuario->empresa?->ativo) {
            throw ValidationException::withMessages(['email' => 'Acesso desativado. Fale com o responsável pela barbearia.']);
        }

        try {
            $status = SenhaBroker::sendResetLink(['email' => $usuario->email]);
        } catch (\Throwable $e) {
            report($e); // ex.: o servidor de e-mail recusou o login (SMTP 535)

            return response()->json([
                'message' => 'Não foi possível enviar o e-mail agora. Tente novamente em alguns minutos.',
            ], 503);
        }

        if ($status === SenhaBroker::RESET_THROTTLED) {
            throw ValidationException::withMessages(['email' => 'Já enviamos um link há pouco. Aguarde um minuto para pedir outro.']);
        }

        return response()->json([
            'message' => 'Enviamos um link para criar uma nova senha. Confira sua caixa de entrada.',
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
