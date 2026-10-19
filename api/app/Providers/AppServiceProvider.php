<?php

namespace App\Providers;

use App\Support\EmpresaAtual;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Uma instância por requisição (ou por job da fila).
        $this->app->scoped(EmpresaAtual::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Limita tentativas de login por e-mail + IP para dificultar ataques de senha.
        RateLimiter::for('login', function (Request $request) {
            $chave = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($chave)->response(fn () => response()->json([
                'message' => 'Muitas tentativas de login. Aguarde um minuto e tente novamente.',
            ], 429));
        });

        RateLimiter::for('registro', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
    }
}
