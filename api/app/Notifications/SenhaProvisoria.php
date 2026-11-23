<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail ao dono com a senha provisória gerada pelo administrador e o link para criar a própria senha.
 */
class SenhaProvisoria extends Notification
{
    public function __construct(private string $senha, private string $linkNovaSenha) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutos = config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject('Seu acesso ao painel da barbearia')
            ->greeting("Olá, {$notifiable->nome}!")
            ->line('Geramos uma senha provisória para o seu acesso ao painel da barbearia.')
            ->line("E-mail: {$notifiable->email}")
            ->line("Senha provisória: {$this->senha}")
            ->line('Use o botão abaixo para criar a sua própria senha agora:')
            ->action('Criar minha senha', $this->linkNovaSenha)
            ->line("O botão vale por {$minutos} minutos. Depois disso, entre em ".config('app.painel_url').' com a senha provisória: o painel pede a nova senha logo na entrada.')
            ->line('A senha provisória deixa de valer assim que você criar a sua.');
    }
}
