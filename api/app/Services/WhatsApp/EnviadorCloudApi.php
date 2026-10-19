<?php

namespace App\Services\WhatsApp;

use App\Models\Empresa;
use App\Models\MensagemWhatsapp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Envio pela WhatsApp Cloud API usando o número da própria barbearia.
 * https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-message-templates
 */
class EnviadorCloudApi implements Enviador
{
    public function enviar(MensagemWhatsapp $mensagem, Empresa $empresa): string
    {
        if (! $empresa->wa_phone_number_id || ! $empresa->wa_token) {
            throw new FalhaEnvio('WhatsApp da barbearia não configurado (ID do número e token).', definitiva: true);
        }

        $p = $mensagem->parametros;
        $componentes = [[
            'type' => 'body',
            'parameters' => array_map(fn ($t) => ['type' => 'text', 'text' => (string) $t], $p['corpo']),
        ]];
        if (! empty($p['botao'])) {
            // Botão de link dinâmico: a Meta completa a URL do template com este final.
            $componentes[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [['type' => 'text', 'text' => $p['botao']]],
            ];
        }

        try {
            $resposta = Http::withToken($empresa->wa_token)
                ->timeout(20)
                ->post(config('whatsapp.graph_url')."/{$empresa->wa_phone_number_id}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => '55'.$mensagem->telefone,
                    'type' => 'template',
                    'template' => [
                        'name' => $mensagem->template,
                        'language' => ['code' => config('whatsapp.idioma')],
                        'components' => $componentes,
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new FalhaEnvio('Sem conexão com a Meta: '.$e->getMessage());
        }

        if ($resposta->successful() && $resposta->json('messages.0.id')) {
            return $resposta->json('messages.0.id');
        }

        $erro = $resposta->json('error.message') ?? "HTTP {$resposta->status()}";
        // 4xx (exceto limite de envio) é erro nosso ou do número: tentar de novo não resolve.
        $definitiva = $resposta->clientError() && $resposta->status() !== 429;

        throw new FalhaEnvio($erro, $definitiva);
    }
}
