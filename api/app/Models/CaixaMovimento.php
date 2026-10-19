<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['caixa_id', 'tipo', 'valor', 'descricao', 'usuario_id'])]
class CaixaMovimento extends Model
{
    use PertenceAEmpresa;

    protected $table = 'caixa_movimentos';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
