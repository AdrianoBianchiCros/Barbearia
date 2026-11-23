<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Notifications\ConfirmacaoAgendamento;
use Illuminate\Support\Facades\Notification;

/**
 * Manda ao cliente o e-mail de horário marcado ou remarcado, quando ele informou e-mail.
 * É aviso do próprio agendamento (execução de contrato), por isso não depende do
 * consentimento do WhatsApp. O envio sai pela fila.
 */
class AvisoAgendamentoEmail
{
    public function __construct(private ResumoAgendamento $resumo) {}

    /** @param  string  $tipo  confirmacao | remarcacao */
    public function enviar(Agendamento $agendamento, string $tipo): bool
    {
        $agendamento->loadMissing('funcionario', 'itens', 'empresa');
        // Busca de novo: o painel carrega o cliente só com id, nome e telefone.
        $cliente = $agendamento->cliente()->withTrashed()->first();

        if (! $cliente?->email
            || $cliente->trashed()
            || ! in_array($agendamento->status, ['agendado', 'confirmado'], true)
            || ! $agendamento->inicio->isFuture()) {
            return false;
        }

        Notification::route('mail', $cliente->email)
            ->notify(new ConfirmacaoAgendamento(['tipo' => $tipo] + $this->resumo->dados($agendamento, $cliente)));

        return true;
    }
}
