<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cliente_id', 'agendamento_id', 'tipo', 'referencia_inicio', 'telefone', 'template', 'parametros',
    'status', 'wamid', 'tentativas', 'erro', 'resposta', 'enviado_em', 'entregue_em', 'lido_em', 'respondido_em',
])]
class MensagemWhatsapp extends Model
{
    use PertenceAEmpresa;

    protected $table = 'mensagens_whatsapp';

    /** Ordem dos status de entrega: um status só avança (a Meta pode mandar fora de ordem). */
    public const ORDEM_STATUS = ['pendente' => 0, 'enviado' => 1, 'entregue' => 2, 'lido' => 3];

    protected function casts(): array
    {
        return [
            'parametros' => 'array',
            'referencia_inicio' => 'datetime',
            'enviado_em' => 'datetime',
            'entregue_em' => 'datetime',
            'lido_em' => 'datetime',
            'respondido_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class);
    }
}
