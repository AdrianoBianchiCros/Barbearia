<?php

namespace App\Providers;

use App\Models\User;
use App\Services\WhatsApp\Enviador;
use App\Services\WhatsApp\EnviadorCloudApi;
use App\Services\WhatsApp\EnviadorTeste;
use App\Support\EmpresaAtual;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
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

        // Modo de teste (só registra) ou envio real pela Cloud API da Meta.
        $this->app->bind(Enviador::class, fn () => config('whatsapp.driver') === 'cloud'
            ? new EnviadorCloudApi
            : new EnviadorTeste);
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

        // Esqueci a senha / redefinir: evita disparar e-mails em massa e testar links por força bruta.
        RateLimiter::for('senha', fn (Request $request) => Limit::perHour(10)->by($request->ip())
            ->response(fn () => response()->json([
                'message' => 'Muitas tentativas seguidas. Aguarde um pouco e tente novamente.',
            ], 429)));

        // E-mail de redefinição de senha em português, com o link apontando para o painel.
        ResetPassword::toMailUsing(function (User $usuario, string $token) {
            $link = rtrim(config('app.painel_url'), '/').'/redefinir-senha?'.http_build_query([
                'token' => $token,
                'email' => $usuario->email,
            ]);
            $minutos = config('auth.passwords.users.expire');

            return (new MailMessage)
                ->subject('Redefinição de senha')
                ->greeting("Olá, {$usuario->nome}!")
                ->line('Recebemos um pedido para redefinir a senha do seu acesso ao painel da barbearia.')
                ->action('Criar nova senha', $link)
                ->line("O link vale por {$minutos} minutos.")
                ->line('Se não foi você que pediu, ignore este e-mail: sua senha atual continua valendo.');
        });

        // Agendamento pela página pública: evita que alguém lote a agenda com pedidos falsos.
        RateLimiter::for('agendamento-publico', fn (Request $request) => Limit::perHour(10)->by($request->ip())
            ->response(fn () => response()->json([
                'message' => 'Muitos agendamentos seguidos. Tente novamente mais tarde ou fale com a barbearia.',
            ], 429)));
    }
}
