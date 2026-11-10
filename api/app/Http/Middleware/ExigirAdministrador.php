<?php

namespace App\Http\Middleware;

use App\Models\Administrador;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Área do administrador da plataforma: só aceita token de um administrador ativo.
 */
class ExigirAdministrador
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();

        if (! $admin instanceof Administrador || ! $admin->ativo) {
            return response()->json(['message' => 'Você não tem permissão para esta ação.'], 403);
        }

        return $next($request);
    }
}
