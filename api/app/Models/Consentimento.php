<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cliente_id', 'canal', 'aceito', 'origem', 'registrado_em'])]
class Consentimento extends Model
{
    use PertenceAEmpresa;

    protected $table = 'consentimentos';

    protected function casts(): array
    {
        return [
            'aceito' => 'boolean',
            'registrado_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
