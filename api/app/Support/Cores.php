<?php

namespace App\Support;

/**
 * Variações da cor principal escolhida em Configurações para a página pública e o link do cliente:
 * a própria cor (botões e faixa), o texto que vai em cima dela, uma versão clara para destacar
 * sobre o fundo escuro e uma escura para destacar sobre as faixas claras. Qualquer cor fica legível.
 */
class Cores
{
    /** Variáveis usadas pelas páginas: acento, corTexto, acentoClaro e acentoEscuro. */
    public static function paraPagina(?string $hex, string $padrao): array
    {
        $cor = preg_match('/^#[0-9a-f]{6}$/i', (string) $hex) ? strtolower($hex) : $padrao;

        return [
            'acento' => $cor,
            'corTexto' => self::textoSobre($cor),
            'acentoClaro' => self::clarear($cor),
            'acentoEscuro' => self::escurecer($cor),
        ];
    }

    /** Branco ou preto, o que tiver mais contraste sobre a cor. */
    public static function textoSobre(string $hex): string
    {
        return self::luminancia(self::rgb($hex)) > 0.179 ? '#111111' : '#ffffff';
    }

    /** A cor clareada até ficar legível sobre o fundo escuro (azul-marinho sumiria no preto). */
    public static function clarear(string $hex): string
    {
        $rgb = self::rgb($hex);
        for ($i = 0; $i < 40 && self::luminancia($rgb) < 0.3; $i++) {
            $rgb = array_map(fn ($c) => (int) round($c + (255 - $c) * 0.05), $rgb);
        }

        return vsprintf('#%02x%02x%02x', $rgb);
    }

    /** A cor escurecida até ficar legível sobre as faixas claras (amarelo sumiria no creme). */
    public static function escurecer(string $hex): string
    {
        $rgb = self::rgb($hex);
        for ($i = 0; $i < 40 && self::luminancia($rgb) > 0.12; $i++) {
            $rgb = array_map(fn ($c) => (int) round($c * 0.95), $rgb);
        }

        return vsprintf('#%02x%02x%02x', $rgb);
    }

    private static function rgb(string $hex): array
    {
        return sscanf($hex, '#%02x%02x%02x');
    }

    private static function luminancia(array $rgb): float
    {
        [$r, $g, $b] = array_map(
            fn ($c) => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
            $rgb,
        );

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
