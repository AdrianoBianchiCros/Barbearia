<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use App\Support\EmpresaAtual;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define a barbearia da requisição a partir do endereço público (/{slug}),
 * para a página da barbearia e o agendamento online, que não têm login.
 */
class DefinirEmpresaPublica
{
    public function __construct(private EmpresaAtual $empresaAtual) {}

    public function handle(Request $request, Closure $next): Response
    {
        $empresa = Empresa::where('slug', $request->route('slug'))->where('ativo', true)->first();
        abort_unless($empresa, 404);

        $this->empresaAtual->definir($empresa->id);
        $request->attributes->set('empresa', $empresa);

        return $next($request);
    }
}
