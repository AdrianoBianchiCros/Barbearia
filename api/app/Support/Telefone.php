<?php

namespace App\Support;

class Telefone
{
    /**
     * Mantém só os dígitos e remove o DDI 55: "(11) 99999-0000" → "11999990000".
     */
    public static function normalizar(?string $telefone): ?string
    {
        if ($telefone === null || trim($telefone) === '') {
            return null;
        }

        $digitos = preg_replace('/\D/', '', $telefone);

        if (strlen($digitos) >= 12 && str_starts_with($digitos, '55')) {
            $digitos = substr($digitos, 2);
        }

        return $digitos;
    }

    /** Regra de validação para telefone brasileiro já normalizado (DDD + número). */
    public const REGRA = 'regex:/^\d{10,11}$/';
}
