<?php

namespace App\Http\Middleware;

use App\Support\EmpresaAtual;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define a barbearia da requisição a partir do usuário autenticado
 * e bloqueia usuários ou barbearias desativados.
 */
class DefinirEmpresaAtual
{
    public function __construct(private EmpresaAtual $empresaAtual) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario || ! $usuario->ativo || ! $usuario->empresa?->ativo) {
            $usuario?->currentAccessToken()?->delete();

            return response()->json(['message' => 'Acesso desativado. Fale com o responsável pela barbearia.'], 403);
        }

        $this->empresaAtual->definir($usuario->empresa_id);

        return $next($request);
    }
}
