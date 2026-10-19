<?php

namespace App\Services\WhatsApp;

use RuntimeException;

/**
 * Erro ao enviar. Definitiva = não adianta tentar de novo (número inválido,
 * template recusado, WhatsApp não configurado…).
 */
class FalhaEnvio extends RuntimeException
{
    public function __construct(string $mensagem, public readonly bool $definitiva = false)
    {
        parent::__construct($mensagem);
    }
}
