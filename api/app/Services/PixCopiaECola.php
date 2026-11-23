<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Código Pix "copia e cola" (BR Code estático do Banco Central) com chave, valor e identificador.
 * Gerado aqui mesmo, sem API do banco: serve para qualquer chave Pix (ex.: conta Nubank).
 * O mesmo texto vira o QR Code no painel.
 */
class PixCopiaECola
{
    public static function gerar(string $chave, string $recebedor, string $cidade, float $valor, string $txid): string
    {
        $conta = self::campo('00', 'br.gov.bcb.pix').self::campo('01', trim($chave));

        $codigo = self::campo('00', '01')
            .self::campo('26', $conta)
            .self::campo('52', '0000')
            .self::campo('53', '986') // real
            .self::campo('54', number_format($valor, 2, '.', ''))
            .self::campo('58', 'BR')
            .self::campo('59', self::texto($recebedor, 25))
            .self::campo('60', self::texto($cidade, 15))
            .self::campo('62', self::campo('05', self::txid($txid)))
            .'6304';

        return $codigo.self::crc16($codigo);
    }

    /** Identificador aceito pelo Pix: só letras e números, até 25 caracteres. */
    public static function txid(string $txid): string
    {
        return substr(preg_replace('/[^A-Za-z0-9]/', '', $txid), 0, 25) ?: '***';
    }

    private static function campo(string $id, string $valor): string
    {
        return $id.str_pad((string) strlen($valor), 2, '0', STR_PAD_LEFT).$valor;
    }

    /** Nome e cidade sem acento e em maiúsculas, como pedem os bancos. */
    private static function texto(string $valor, int $limite): string
    {
        return substr(trim(preg_replace('/[^A-Z0-9 ]/', '', Str::upper(Str::ascii($valor)))), 0, $limite);
    }

    /** CRC16-CCITT (polinômio 0x1021, início 0xFFFF), exigido no fim do código. */
    private static function crc16(string $dados): string
    {
        $crc = 0xFFFF;
        foreach (str_split($dados) as $caractere) {
            $crc ^= ord($caractere) << 8;
            for ($i = 0; $i < 8; $i++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
