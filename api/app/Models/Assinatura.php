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
#[Fillable(['empresa_id', 'administrador_id', 'periodo', 'inicio', 'fim', 'forma_pagamento', 'valor', 'contrato_path', 'contrato_nome', 'observacao'])]
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

    /** Último dia de acesso de um período que começa em $inicio (ex.: 1 mês a partir de 10/11 vai até 09/12). */
    public static function calcularFim(string $periodo, Carbon $inicio): Carbon
    {
        $regra = config("assinatura.periodos.{$periodo}");

        return isset($regra['dias'])
            ? $inicio->copy()->addDays($regra['dias'] - 1)
            : $inicio->copy()->addMonthsNoOverflow($regra['meses'])->subDay();
    }
}
