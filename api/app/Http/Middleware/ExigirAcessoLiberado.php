<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agendamento online (página pública e link do cliente): só funciona com o prazo de acesso
 * da barbearia em dia. A página continua no ar, mas sem marcar horários.
 * Usar depois de empresa.publica ou agendamento.token.
 */
class ExigirAcessoLiberado
{
    public function handle(Request $request, Closure $next): Response
    {
        $empresa = $request->attributes->get('empresa') ?? $request->attributes->get('agendamento')?->empresa;

        if ($empresa instanceof Empresa && ! $empresa->acessoLiberado()) {
            return response()->json([
                'message' => 'O agendamento online não está disponível no momento. Fale com a barbearia.',
            ], 423);
        }

        return $next($request);
    }
}
