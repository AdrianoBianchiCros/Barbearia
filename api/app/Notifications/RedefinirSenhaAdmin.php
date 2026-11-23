<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail de "Esqueci a senha" do administrador da plataforma, com o link para a área /admin.
 */
class RedefinirSenhaAdmin extends Notification
{
    public function __construct(private string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $link = rtrim(config('app.painel_url'), '/').'/admin/redefinir-senha?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);
        $minutos = config('auth.passwords.administradores.expire');

        return (new MailMessage)
            ->subject('Redefinição de senha da administração')
            ->greeting("Olá, {$notifiable->nome}!")
            ->line('Recebemos um pedido para redefinir a senha da área de administração das barbearias.')
            ->action('Criar nova senha', $link)
            ->line("O link vale por {$minutos} minutos.")
            ->line('Se não foi você que pediu, ignore este e-mail: sua senha atual continua valendo.');
    }
}
