<?php

namespace App\Services\WhatsApp;

use App\Models\Empresa;
use App\Models\MensagemWhatsapp;

interface Enviador
{
    /**
     * Envia a mensagem (template) e devolve o id dela na Meta (wamid).
     *
     * @throws FalhaEnvio
     */
    public function enviar(MensagemWhatsapp $mensagem, Empresa $empresa): string;
}
