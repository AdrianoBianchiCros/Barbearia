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

    /** "11999990000" → "(11) 99999-0000"; o que não tiver 10 ou 11 dígitos volta como veio. */
    public static function formatar(?string $telefone): string
    {
        $d = (string) self::normalizar($telefone);

        return match (strlen($d)) {
            11 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7)),
            10 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6)),
            default => (string) $telefone,
        };
    }

    /** Regra de validação para telefone brasileiro já normalizado (DDD + número). */
    public const REGRA = 'regex:/^\d{10,11}$/';
}
