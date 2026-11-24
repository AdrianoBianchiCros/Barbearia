<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Contratação ou renovação do acesso de uma barbearia: período, pagamento e contrato.
 * Sem o filtro automático por barbearia: é usada só pela área do administrador.
 */
#[Fillable(['empresa_id', 'administrador_id', 'periodo', 'inicio', 'fim', 'forma_pagamento', 'valor', 'taxa_instalacao', 'gratis', 'contrato_path', 'contrato_nome', 'observacao'])]
class Assinatura extends Model
{
    protected $hidden = ['contrato_path'];

    protected $appends = ['tem_contrato'];

    protected function casts(): array
    {
        return [
            'inicio' => 'date:Y-m-d',
            'fim' => 'date:Y-m-d',
            'valor' => 'decimal:2',
            'taxa_instalacao' => 'decimal:2',
            'gratis' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function administrador(): BelongsTo
    {
        return $this->belongsTo(Administrador::class);
    }

    protected function temContrato(): Attribute
    {
        return Attribute::get(fn () => filled($this->contrato_path));
    }

    /**
     * Último dia de acesso pago a partir de $inicio. A cobrança é sempre mensal: cada pagamento libera
     * 1 mês (de 10/11 até 09/12), qualquer que seja o período; o período (3, 6, 12 meses) é a vigência
     * do contrato. O teste usa os dias dele (7 dias).
     */
    public static function calcularFim(string $periodo, Carbon $inicio): Carbon
    {
        $regra = config("assinatura.periodos.{$periodo}");

        return isset($regra['dias'])
            ? $inicio->copy()->addDays($regra['dias'] - 1)
            : $inicio->copy()->addMonthNoOverflow()->subDay();
    }

    /** Como a cobrança aparece para o dono: "Mensalidade", "Mensalidade · contrato de 12 meses" ou "Teste de 7 dias". */
    public static function rotuloCobranca(string $periodo): string
    {
        $regra = config("assinatura.periodos.{$periodo}");
        if (! $regra || isset($regra['dias'])) {
            return $regra['rotulo'] ?? $periodo;
        }

        return $regra['meses'] === 1 ? 'Mensalidade' : "Mensalidade · contrato de {$regra['rotulo']}";
    }
}
