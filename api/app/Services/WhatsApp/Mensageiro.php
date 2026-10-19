<?php

namespace App\Services\WhatsApp;

use App\Jobs\EnviarMensagemWhatsapp;
use App\Models\Agendamento;
use App\Models\Empresa;
use App\Models\MensagemWhatsapp;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Decide quando um agendamento gera mensagem e monta o conteúdo do template.
 * O envio em si acontece na fila (EnviarMensagemWhatsapp).
 */
class Mensageiro
{
    public const TIPOS = ['confirmacao', 'remarcacao', 'lembrete_24h', 'lembrete_2h'];

    private const DIAS = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];

    /**
     * Cria a mensagem (se for o caso) e coloca na fila. Devolve null quando não há
     * o que enviar: WhatsApp desligado, cliente sem consentimento, agendamento passado
     * ou mensagem já criada para este horário.
     */
    public function agendamento(Agendamento $agendamento, string $tipo): ?MensagemWhatsapp
    {
        $agendamento->loadMissing('cliente.consentimentoWhatsapp', 'funcionario', 'itens', 'empresa');
        $empresa = $agendamento->empresa;

        if (! $this->podeEnviar($agendamento) || ! $this->tipoLigado($empresa, $tipo)) {
            return null;
        }

        $existe = MensagemWhatsapp::where('agendamento_id', $agendamento->id)
            ->where('tipo', $tipo)
            ->where('referencia_inicio', $agendamento->inicio)
            ->exists();
        if ($existe) {
            return null;
        }

        try {
            $mensagem = MensagemWhatsapp::create([
                'cliente_id' => $agendamento->cliente_id,
                'agendamento_id' => $agendamento->id,
                'tipo' => $tipo,
                'referencia_inicio' => $agendamento->inicio,
                'telefone' => $agendamento->cliente->telefone,
                'template' => $this->template($tipo),
                'parametros' => $this->parametros($agendamento, $tipo),
            ]);
        } catch (UniqueConstraintViolationException) {
            return null; // outro processo criou a mesma mensagem ao mesmo tempo
        }

        EnviarMensagemWhatsapp::dispatch($mensagem->id)->afterCommit();

        return $mensagem;
    }

    /** Condições para mandar mensagem sobre um agendamento (conferidas de novo na hora do envio). */
    public function podeEnviar(Agendamento $agendamento): bool
    {
        return $agendamento->empresa->wa_ativo
            && in_array($agendamento->status, ['agendado', 'confirmado'], true)
            && $agendamento->inicio->isFuture()
            && $agendamento->cliente
            && ! $agendamento->cliente->trashed()
            && $agendamento->cliente->consentimentoWhatsapp?->aceito;
    }

    private function tipoLigado(Empresa $empresa, string $tipo): bool
    {
        return match ($tipo) {
            'lembrete_24h' => $empresa->wa_lembrete_24h,
            'lembrete_2h' => $empresa->wa_lembrete_2h,
            default => true,
        };
    }

    private function template(string $tipo): string
    {
        return str_starts_with($tipo, 'lembrete')
            ? config('whatsapp.templates.lembrete')
            : config('whatsapp.templates.confirmacao');
    }

    /**
     * Variáveis do template, na ordem {{1}}, {{2}}… e o final do link do botão.
     *
     * confirmacao_agendamento: Olá, {{1}}! Seu horário na {{2}} está marcado para {{3}} às {{4}},
     *   com {{5}} ({{6}}). [botão: /a/{{1}}]
     * lembrete_agendamento: Oi, {{1}}! Lembrete do seu horário na {{2}} {{3}} às {{4}}, com {{5}}. [botão]
     */
    public function parametros(Agendamento $agendamento, string $tipo): array
    {
        $empresa = $agendamento->empresa;
        $inicio = $agendamento->inicio->copy()->setTimezone($empresa->fuso_horario);
        $nome = strtok(trim($agendamento->cliente->nome), ' ') ?: $agendamento->cliente->nome;
        $hora = $inicio->format('H:i');
        $barbeiro = $agendamento->funcionario->nome;

        if (str_starts_with($tipo, 'lembrete')) {
            $hoje = now($empresa->fuso_horario);
            $quando = match (true) {
                $inicio->isSameDay($hoje) => 'hoje',
                $inicio->isSameDay($hoje->copy()->addDay()) => 'amanhã',
                default => 'em '.$inicio->format('d/m'),
            };
            $corpo = [$nome, $empresa->nome, $quando, $hora, $barbeiro];
        } else {
            $data = self::DIAS[$inicio->dayOfWeek].', '.$inicio->format('d/m');
            $servicos = $agendamento->itens->pluck('nome')->join(' + ');
            $corpo = [$nome, $empresa->nome, $data, $hora, $barbeiro, $servicos];
        }

        return ['corpo' => $corpo, 'botao' => $agendamento->token];
    }
}
