<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['cliente_id', 'funcionario_id', 'inicio', 'fim', 'status', 'origem', 'valor_total', 'observacoes', 'cancelado_em'])]
class Agendamento extends Model
{
    use PertenceAEmpresa;

    protected $table = 'agendamentos';

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fim' => 'datetime',
            'valor_total' => 'decimal:2',
            'cancelado_em' => 'datetime',
        ];
    }

    public const STATUS = ['agendado', 'confirmado', 'em_atendimento', 'concluido', 'cancelado', 'faltou'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(AgendamentoItem::class);
    }
}
