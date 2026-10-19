<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['aberto_por', 'aberto_em', 'valor_inicial', 'fechado_por', 'fechado_em', 'valor_contado', 'observacoes'])]
class Caixa extends Model
{
    use PertenceAEmpresa;

    protected $table = 'caixas';

    protected function casts(): array
    {
        return [
            'aberto_em' => 'datetime',
            'fechado_em' => 'datetime',
            'valor_inicial' => 'decimal:2',
            'valor_contado' => 'decimal:2',
        ];
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(CaixaMovimento::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class);
    }

    public function lancamentos(): HasMany
    {
        return $this->hasMany(Lancamento::class);
    }

    public function abertoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aberto_por');
    }

    public function fechadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fechado_por');
    }

    public function estaAberto(): bool
    {
        return $this->fechado_em === null;
    }
}
