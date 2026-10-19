<?php

namespace App\Http\Middleware;

use App\Models\Agendamento;
use App\Support\EmpresaAtual;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Link do cliente (/a/{token}): encontra o agendamento pelo código secreto
 * e define a barbearia dele para o restante da requisição.
 */
class DefinirAgendamentoPorToken
{
    public function __construct(private EmpresaAtual $empresaAtual) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->route('token');
        $agendamento = strlen($token) === 32
            ? Agendamento::withoutGlobalScopes()->where('token', $token)->first()
            : null;
        abort_unless($agendamento && $agendamento->empresa?->ativo, 404);

        $this->empresaAtual->definir($agendamento->empresa_id);
        $request->attributes->set('agendamento', $agendamento);

        return $next($request);
    }
}
