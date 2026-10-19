<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe a rota a determinados perfis. Uso: ->middleware('perfil:dono,recepcao')
 */
class ExigirPerfil
{
    public function handle(Request $request, Closure $next, string ...$perfis): Response
    {
        if (! $request->user()?->temPerfil(...$perfis)) {
            return response()->json(['message' => 'Você não tem permissão para esta ação.'], 403);
        }

        return $next($request);
    }
}
