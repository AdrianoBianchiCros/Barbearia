<?php

namespace App\Models\Concerns;

use App\Models\Empresa;
use App\Support\EmpresaAtual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isola os registros por barbearia.
 *
 * Com uma empresa definida na requisição, toda consulta é filtrada por empresa_id
 * e todo registro novo recebe o empresa_id automaticamente.
 */
trait PertenceAEmpresa
{
    public static function bootPertenceAEmpresa(): void
    {
        static::addGlobalScope('empresa', function (Builder $query) {
            $empresa = app(EmpresaAtual::class);

            if ($empresa->definida()) {
                $query->where($query->getModel()->qualifyColumn('empresa_id'), $empresa->id());
            }
        });

        static::creating(function ($model) {
            $empresa = app(EmpresaAtual::class);

            if ($empresa->definida()) {
                // Na requisição de uma barbearia, nunca é possível gravar dados em outra.
                $model->empresa_id = $empresa->id();
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
