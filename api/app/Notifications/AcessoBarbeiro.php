<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail ao barbeiro com o link do painel dele (agenda e comissão), a senha provisória
 * e o botão para criar a própria senha.
 */
class AcessoBarbeiro extends Notification
{
    public function __construct(
        private string $barbearia,
        private string $senha,
        private string $linkPainel,
        private string $linkNovaSenha,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutos = config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject("Seu acesso à agenda da {$this->barbearia}")
            ->greeting("Olá, {$notifiable->nome}!")
            ->line("A {$this->barbearia} liberou o seu acesso ao painel do barbeiro, onde você acompanha a sua agenda e a comissão a receber.")
            ->line("Endereço: {$this->linkPainel}")
            ->line("E-mail: {$notifiable->email}")
            ->line("Senha provisória: {$this->senha}")
            ->line('Use o botão abaixo para criar a sua própria senha agora:')
            ->action('Criar minha senha', $this->linkNovaSenha)
            ->line("O botão vale por {$minutos} minutos. Depois disso, entre no endereço acima com a senha provisória: o painel pede a nova senha logo na entrada.")
            ->line('Esqueceu a senha? Use "Esqueci a senha" na tela de entrada.');
    }
}
