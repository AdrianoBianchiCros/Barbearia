<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\EmpresaAtual;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define a barbearia da requisição a partir do usuário autenticado
 * e bloqueia usuários ou barbearias desativados ou com o prazo de acesso vencido.
 */
class DefinirEmpresaAtual
{
    public function __construct(private EmpresaAtual $empresaAtual) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        // Token de administrador da plataforma não entra no painel das barbearias.
        if (! $usuario instanceof User) {
            return response()->json(['message' => 'Você não tem permissão para esta ação.'], 403);
        }

        if (! $usuario->ativo || ! $usuario->empresa?->ativo) {
            $usuario->currentAccessToken()?->delete();

            return response()->json(['message' => 'Acesso desativado. Fale com o responsável pela barbearia.'], 403);
        }

        if (! $usuario->empresa->acessoLiberado()) {
            $usuario->currentAccessToken()?->delete();

            return response()->json([
                'message' => $usuario->empresa->mensagemBloqueio(),
                'codigo' => 'acesso_vencido',
            ], 403);
        }

        $this->empresaAtual->definir($usuario->empresa_id);

        return $next($request);
    }
}
