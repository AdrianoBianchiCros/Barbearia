<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['comanda_id', 'tipo', 'servico_id', 'produto_id', 'funcionario_id', 'descricao', 'quantidade', 'preco_unitario', 'total', 'comissao_percentual', 'comissao_valor'])]
class ComandaItem extends Model
{
    use PertenceAEmpresa;

    protected $table = 'comanda_itens';

    protected function casts(): array
    {
        return [
            'quantidade' => 'integer',
            'preco_unitario' => 'decimal:2',
            'total' => 'decimal:2',
            'comissao_percentual' => 'decimal:2',
            'comissao_valor' => 'decimal:2',
        ];
    }

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class);
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
