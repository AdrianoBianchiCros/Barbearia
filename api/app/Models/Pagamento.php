<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['comanda_id', 'caixa_id', 'forma', 'valor'])]
class Pagamento extends Model
{
    use PertenceAEmpresa;

    protected $table = 'pagamentos';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
        ];
    }

    public const FORMAS = ['dinheiro', 'pix', 'debito', 'credito'];

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class);
    }
}
