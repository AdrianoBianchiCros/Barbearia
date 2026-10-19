<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable(['cliente_id', 'funcionario_id', 'inicio', 'fim', 'status', 'origem', 'valor_total', 'observacoes', 'cancelado_em', 'confirmado_em'])]
class Agendamento extends Model
{
    use PertenceAEmpresa;

    protected $table = 'agendamentos';

    protected $hidden = ['token'];

    protected static function booted(): void
    {
        // Código secreto do link do cliente (/a/{token}) para confirmar, remarcar ou cancelar.
        static::creating(fn (Agendamento $a) => $a->token ??= Str::random(32));
    }

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fim' => 'datetime',
            'valor_total' => 'decimal:2',
            'cancelado_em' => 'datetime',
            'confirmado_em' => 'datetime',
        ];
    }

    /** Endereço que o cliente usa para confirmar, remarcar ou cancelar. */
    public function linkCliente(): string
    {
        return url("/a/{$this->token}");
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(MensagemWhatsapp::class);
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

    public function comanda(): HasOne
    {
        return $this->hasOne(Comanda::class);
    }
}
