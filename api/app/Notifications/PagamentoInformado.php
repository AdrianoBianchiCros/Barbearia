<?php

namespace App\Notifications;

use App\Models\Empresa;
use App\Models\Fatura;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail ao administrador: o dono avisou pelo painel que pagou a fatura por Pix.
 */
class PagamentoInformado extends Notification
{
    public function __construct(private Empresa $empresa, private Fatura $fatura) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $valor = 'R$ '.number_format((float) $this->fatura->valor, 2, ',', '.');
        $link = rtrim(config('app.painel_url'), '/')."/admin/barbearias/{$this->empresa->id}";

        return (new MailMessage)
            ->subject("Pagamento informado: {$this->empresa->nome}")
            ->greeting("Olá, {$notifiable->nome}!")
            ->line("O dono da {$this->empresa->nome} informou que pagou a mensalidade por Pix.")
            ->line("Valor: {$valor} · {$this->fatura->periodo_rotulo} · identificador {$this->fatura->txid}")
            ->line('Confira o recebimento no banco e confirme na administração para renovar o acesso.')
            ->action('Abrir a barbearia', $link);
    }
}
