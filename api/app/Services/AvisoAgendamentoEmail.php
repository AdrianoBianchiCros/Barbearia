<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Notifications\ConfirmacaoAgendamento;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Manda ao cliente o e-mail de horário marcado ou remarcado, quando ele informou e-mail.
 * É aviso do próprio agendamento (execução de contrato), por isso não depende do
 * consentimento do WhatsApp.
 *
 * O envio acontece logo depois de a tela receber a resposta (não espera o cron da fila
 * nem segura o cliente). Se o servidor de e-mail falhar nessa hora, vai para a fila,
 * que tenta de novo. Registrado como "scoped" no AppServiceProvider.
 */
class AvisoAgendamentoEmail
{
    /** @var array<array{email: string, dados: array}> */
    private array $pendentes = [];

    private bool $aguardandoResposta = false;

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

        $this->pendentes[] = ['email' => $cliente->email, 'dados' => ['tipo' => $tipo] + $this->resumo->dados($agendamento, $cliente)];
        if (! $this->aguardandoResposta) {
            $this->aguardandoResposta = true;
            app()->terminating(fn () => $this->enviarPendentes());
        }

        return true;
    }

    /** Roda depois de a resposta sair para o navegador. */
    public function enviarPendentes(): void
    {
        $pendentes = $this->pendentes;
        $this->pendentes = [];

        foreach ($pendentes as $p) {
            $destino = Notification::route('mail', $p['email']);
            try {
                $destino->notifyNow(new ConfirmacaoAgendamento($p['dados']));
            } catch (Throwable $e) {
                report($e);
                $destino->notify(new ConfirmacaoAgendamento($p['dados'])); // a fila tenta de novo
            }
        }
    }
}
