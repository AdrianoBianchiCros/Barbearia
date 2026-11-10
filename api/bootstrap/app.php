<?php

use App\Http\Middleware\DefinirAgendamentoPorToken;
use App\Http\Middleware\DefinirEmpresaAtual;
use App\Http\Middleware\DefinirEmpresaPublica;
use App\Http\Middleware\ExigirAcessoLiberado;
use App\Http\Middleware\ExigirAdministrador;
use App\Http\Middleware\ExigirPerfil;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'empresa' => DefinirEmpresaAtual::class,
            'perfil' => ExigirPerfil::class,
            'empresa.publica' => DefinirEmpresaPublica::class,
            'agendamento.token' => DefinirAgendamentoPorToken::class,
            'acesso.liberado' => ExigirAcessoLiberado::class,
            'admin' => ExigirAdministrador::class,
        ]);

        // A barbearia precisa estar definida antes de carregar registros pela URL
        // (ex.: /clientes/5), senão o filtro por empresa não seria aplicado.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: DefinirEmpresaAtual::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
