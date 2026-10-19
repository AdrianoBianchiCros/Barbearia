<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'empresa' => \App\Http\Middleware\DefinirEmpresaAtual::class,
            'perfil' => \App\Http\Middleware\ExigirPerfil::class,
            'empresa.publica' => \App\Http\Middleware\DefinirEmpresaPublica::class,
        ]);

        // A barbearia precisa estar definida antes de carregar registros pela URL
        // (ex.: /clientes/5), senão o filtro por empresa não seria aplicado.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\DefinirEmpresaAtual::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
