<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail ao cliente com o horário marcado ou remarcado e o link para confirmar, remarcar ou cancelar.
 * Recebe só textos prontos (sem modelos): a fila roda fora do contexto da barbearia.
 */
class ConfirmacaoAgendamento extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{tipo: string, barbearia: string, email_barbearia: ?string, nome: string, data: string,
     *               hora: string, barbeiro: string, servicos: string, valor: string, endereco: ?string, link: string}  $dados
     */
    public function __construct(public array $dados)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->dados;
        $remarcado = $d['tipo'] === 'remarcacao';

        $mensagem = (new MailMessage)
            ->from(config('mail.from.address'), $d['barbearia'])
            ->subject(($remarcado ? 'Horário remarcado' : 'Horário confirmado')." — {$d['data']} às {$d['hora']}")
            ->greeting("Olá, {$d['nome']}!")
            ->line("Seu horário na **{$d['barbearia']}** ".($remarcado ? 'foi remarcado para:' : 'está marcado:'))
            ->line("**{$d['data']} às {$d['hora']}**, com {$d['barbeiro']}")
            ->line("Serviço: {$d['servicos']} · R$ {$d['valor']}");

        if ($d['endereco']) {
            $mensagem->line("Endereço: {$d['endereco']}");
        }

        $mensagem
            ->action('Confirmar, remarcar ou cancelar', $d['link'])
            ->line('Se não puder vir, cancele pelo botão acima para liberar o horário para outro cliente.')
            ->salutation("Até lá!\n\n{$d['barbearia']}");

        if ($d['email_barbearia']) {
            $mensagem->replyTo($d['email_barbearia'], $d['barbearia']);
        }

        return $mensagem;
    }
}
