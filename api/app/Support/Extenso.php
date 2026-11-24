<?php

namespace App\Support;

/** Valores por extenso em português, para contratos: 1234.5 → "mil, duzentos e trinta e quatro reais e cinquenta centavos". */
class Extenso
{
    private const UNIDADES = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove',
        'dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];

    private const DEZENAS = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];

    private const CENTENAS = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos',
        'setecentos', 'oitocentos', 'novecentos'];

    public static function reais(float $valor): string
    {
        $centavos = (int) round($valor * 100);
        $inteiro = intdiv($centavos, 100);
        $resto = $centavos % 100;

        $partes = [];
        if ($inteiro > 0) {
            $partes[] = self::numero($inteiro).($inteiro % 1000000 === 0 ? ' de' : '').($inteiro === 1 ? ' real' : ' reais');
        }
        if ($resto > 0) {
            $partes[] = self::numero($resto).($resto === 1 ? ' centavo' : ' centavos');
        }

        return $partes ? implode(' e ', $partes) : 'zero real';
    }

    /** Número inteiro por extenso (até 999.999.999). */
    public static function numero(int $n): string
    {
        if ($n === 0) {
            return 'zero';
        }

        $grupos = [[intdiv($n, 1000000), 'milhão', 'milhões'], [intdiv($n, 1000) % 1000, 'mil', 'mil'], [$n % 1000, '', '']];
        $partes = [];
        foreach ($grupos as [$valor, $singular, $plural]) {
            if ($valor === 0) {
                continue;
            }
            $texto = ($singular === 'mil' && $valor === 1) ? 'mil' : trim(self::ate999($valor).' '.($valor === 1 ? $singular : $plural));
            $partes[] = [$valor, $texto];
        }

        // "e" antes do último grupo quando ele é menor que 100 ou centena redonda (ex.: "mil e cem", "dois mil e vinte"; "mil duzentos e trinta").
        $saida = '';
        foreach ($partes as $i => [$valor, $texto]) {
            if ($i > 0) {
                $ultimo = $i === array_key_last($partes);
                $saida .= ($ultimo && ($valor < 100 || $valor % 100 === 0)) ? ' e ' : ' ';
            }
            $saida .= $texto;
        }

        return $saida;
    }

    private static function ate999(int $n): string
    {
        if ($n === 100) {
            return 'cem';
        }
        $partes = [];
        if ($n >= 100) {
            $partes[] = self::CENTENAS[intdiv($n, 100)];
            $n %= 100;
        }
        if ($n >= 20) {
            $partes[] = self::DEZENAS[intdiv($n, 10)];
            $n %= 10;
        }
        if ($n > 0) {
            $partes[] = self::UNIDADES[$n];
        }

        return implode(' e ', $partes);
    }
}
