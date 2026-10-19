<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Compra de um produto (entrada de estoque). */
#[Fillable(['produto_id', 'data_compra', 'fornecedor', 'numero_nota', 'lote', 'quantidade', 'custo_unitario', 'validade', 'usuario_id'])]
class ProdutoEntrada extends Model
{
    use PertenceAEmpresa;

    protected $table = 'produto_entradas';

    protected function casts(): array
    {
        return [
            'data_compra' => 'date',
            'validade' => 'date',
            'quantidade' => 'integer',
            'custo_unitario' => 'decimal:2',
        ];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
