<?php

namespace App\Notifications;

use App\Models\Empresa;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail ao dono avisando que o prazo de acesso da barbearia está acabando (ou acabou).
 */
class AvisoVencimentoAcesso extends Notification
{
    public function __construct(private Empresa $empresa) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dias = $this->empresa->diasRestantes();
        $vence = $this->empresa->acesso_ate->format('d/m/Y');
        $bloqueia = $this->empresa->bloqueiaEm()->format('d/m/Y');
        $contato = config('plataforma.nome');

        $mensagem = (new MailMessage)->greeting("Olá, {$notifiable->nome}!");

        if ($dias > 0) {
            return $mensagem
                ->subject("O acesso da {$this->empresa->nome} vence em {$dias} dia(s)")
                ->line("O plano da {$this->empresa->nome} no sistema da barbearia vai até {$vence}.")
                ->line("Para não ter a agenda interrompida, fale com a {$contato} e renove antes do vencimento.");
        }

        return $mensagem
            ->subject($dias === 0 ? "O acesso da {$this->empresa->nome} vence hoje" : "O acesso da {$this->empresa->nome} venceu")
            ->line("O plano da {$this->empresa->nome} vence em {$vence}.")
            ->line("O sistema continua funcionando até {$bloqueia}. Depois disso, o painel e o agendamento online ficam bloqueados até a renovação.")
            ->line("Para renovar, fale com a {$contato}. Nenhum dado é apagado.");
    }
}
