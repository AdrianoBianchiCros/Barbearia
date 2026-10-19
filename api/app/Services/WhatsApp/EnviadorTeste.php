<?php

namespace App\Services\WhatsApp;

use App\Models\Empresa;
use App\Models\MensagemWhatsapp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Modo de teste: não envia nada, só registra no log como se tivesse enviado. */
class EnviadorTeste implements Enviador
{
    public function enviar(MensagemWhatsapp $mensagem, Empresa $empresa): string
    {
        Log::info('[WhatsApp teste] '.$mensagem->template.' para '.$mensagem->telefone, $mensagem->parametros);

        return 'teste.'.Str::uuid();
    }
}
