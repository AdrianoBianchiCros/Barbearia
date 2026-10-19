<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['funcionario_id', 'dia_semana', 'inicio', 'fim'])]
class HorarioTrabalho extends Model
{
    use PertenceAEmpresa;

    protected $table = 'horarios_trabalho';

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
        ];
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }
}
