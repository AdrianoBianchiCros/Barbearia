<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nome', 'preco', 'custo', 'estoque', 'comissao_percentual', 'ativo'])]
class Produto extends Model
{
    use PertenceAEmpresa, SoftDeletes;

    protected $table = 'produtos';

    protected function casts(): array
    {
        return [
            'preco' => 'decimal:2',
            'custo' => 'decimal:2',
            'estoque' => 'integer',
            'comissao_percentual' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    public function entradas(): HasMany
    {
        return $this->hasMany(ProdutoEntrada::class);
    }

    /** Compra mais recente (por data da compra). */
    public function ultimaEntrada(): HasOne
    {
        return $this->hasOne(ProdutoEntrada::class)->ofMany(['data_compra' => 'max', 'id' => 'max']);
    }
}
