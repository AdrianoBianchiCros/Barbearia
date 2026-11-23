<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Notifications\ConfirmacaoAgendamento;
use Illuminate\Support\Facades\Notification;

/**
 * Manda ao cliente o e-mail de horário marcado ou remarcado, quando ele informou e-mail.
 * É aviso do próprio agendamento (execução de contrato), por isso não depende do
 * consentimento do WhatsApp. O envio sai pela fila.
 */
class AvisoAgendamentoEmail
{
    private const DIAS = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];

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
            ->notify(new ConfirmacaoAgendamento($this->dados($agendamento, $cliente, $tipo)));

        return true;
    }

    private function dados(Agendamento $agendamento, Cliente $cliente, string $tipo): array
    {
        $empresa = $agendamento->empresa;
        $inicio = $agendamento->inicio->copy()->setTimezone($empresa->fuso_horario);
        $nome = trim($cliente->nome);

        return [
            'tipo' => $tipo,
            'barbearia' => $empresa->nome,
            'email_barbearia' => $empresa->email ?: null,
            'nome' => strtok($nome, ' ') ?: $nome,
            'data' => self::DIAS[$inicio->dayOfWeek].', '.$inicio->format('d/m/Y'),
            'hora' => $inicio->format('H:i'),
            'barbeiro' => $agendamento->funcionario->nome,
            'servicos' => $agendamento->itens->pluck('nome')->join(' + '),
            'valor' => number_format((float) $agendamento->valor_total, 2, ',', '.'),
            'endereco' => $this->endereco($empresa),
            'link' => route('agendamento.cliente', $agendamento->token),
        ];
    }

    private function endereco(Empresa $empresa): ?string
    {
        $rua = trim(implode(', ', array_filter([$empresa->logradouro, $empresa->numero, $empresa->complemento])));
        $cidade = trim(implode(' - ', array_filter([$empresa->cidade, $empresa->uf])));
        $partes = array_filter([$rua, $empresa->bairro, $cidade]);

        return $partes ? implode(' · ', $partes) : null;
    }
}
