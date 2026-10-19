<?php

namespace App\Jobs;

use App\Models\MensagemWhatsapp;
use App\Services\WhatsApp\Enviador;
use App\Services\WhatsApp\FalhaEnvio;
use App\Services\WhatsApp\Mensageiro;
use App\Support\EmpresaAtual;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Envia uma mensagem do WhatsApp. Falhas temporárias (rede, limite da Meta)
 * são tentadas de novo; as definitivas ficam como "falhou" com o motivo.
 */
class EnviarMensagemWhatsapp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Espera entre as tentativas, em segundos. */
    public array $backoff = [60, 300];

    public function __construct(public int $mensagemId) {}

    public function handle(Enviador $enviador, Mensageiro $mensageiro, EmpresaAtual $empresaAtual): void
    {
        $mensagem = MensagemWhatsapp::withoutGlobalScopes()->find($this->mensagemId);
        if (! $mensagem || $mensagem->status !== 'pendente') {
            return; // já enviada (ou removida): não manda duas vezes
        }

        $empresaAtual->definir($mensagem->empresa_id);
        $mensagem->load('agendamento.cliente.consentimentoWhatsapp', 'agendamento.empresa');

        // Cancelado, já passou ou o cliente pediu para não receber: não envia.
        if ($mensagem->agendamento && ! $mensageiro->podeEnviar($mensagem->agendamento)) {
            $mensagem->update(['status' => 'ignorado', 'erro' => 'Agendamento não está mais ativo ou cliente sem consentimento.']);

            return;
        }

        $mensagem->increment('tentativas');

        try {
            $wamid = $enviador->enviar($mensagem, $mensagem->agendamento->empresa);
        } catch (FalhaEnvio $e) {
            $mensagem->update(['erro' => mb_substr($e->getMessage(), 0, 500)]);
            if ($e->definitiva || $this->attempts() >= $this->tries) {
                $mensagem->update(['status' => 'falhou']);

                return;
            }
            $this->release($this->backoff[$this->attempts() - 1] ?? 300);

            return;
        }

        $mensagem->update(['status' => 'enviado', 'wamid' => $wamid, 'enviado_em' => now(), 'erro' => null]);
    }

    public function failed(?Throwable $e): void
    {
        MensagemWhatsapp::withoutGlobalScopes()->whereKey($this->mensagemId)->where('status', 'pendente')
            ->update(['status' => 'falhou', 'erro' => mb_substr((string) $e?->getMessage(), 0, 500)]);
    }
}
