<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Prova do aceite de um documento (termos, privacidade, declaração de maioridade).
 * Sem o filtro automático por barbearia: é gravado também no cadastro, antes de existir sessão.
 */
#[Fillable(['empresa_id', 'usuario_id', 'cliente_id', 'documento', 'versao', 'origem', 'ip', 'navegador', 'aceito_em'])]
class Aceite extends Model
{
    public const VERSAO_MAIORIDADE = '2026-11-09';

    protected function casts(): array
    {
        return ['aceito_em' => 'datetime'];
    }

    /**
     * Grava o aceite de cada documento com a versão vigente.
     *
     * @param  array<string>  $documentos  termos, privacidade, maioridade
     * @param  array{empresa_id: int, usuario_id?: int, cliente_id?: int}  $quem
     */
    public static function registrar(array $documentos, array $quem, string $origem, Request $request): void
    {
        foreach ($documentos as $documento) {
            self::create($quem + [
                'documento' => $documento,
                'versao' => self::versaoVigente($documento),
                'origem' => $origem,
                'ip' => $request->ip(),
                'navegador' => Str::limit((string) $request->userAgent(), 250, ''),
                'aceito_em' => now(),
            ]);
        }
    }

    public static function versaoVigente(string $documento): string
    {
        return match ($documento) {
            'termos' => config('plataforma.versao_termos'),
            'privacidade' => config('plataforma.versao_privacidade'),
            default => self::VERSAO_MAIORIDADE,
        };
    }
}
