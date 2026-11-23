<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Cancelamento ou remarcação mostrado no sino da agenda até o usuário abrir. */
#[Fillable(['agendamento_id', 'funcionario_id', 'funcionario_anterior_id', 'tipo', 'origem', 'autor_id', 'cliente_nome', 'inicio', 'inicio_anterior'])]
class AvisoAgenda extends Model
{
    use PertenceAEmpresa;

    protected $table = 'avisos_agenda';

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'inicio_anterior' => 'datetime',
        ];
    }

    /** O que o usuário vê: dono e recepção, tudo; barbeiro, só a própria agenda. Nunca o que ele mesmo fez. */
    public function scopeVisiveisPara(Builder $query, User $usuario): void
    {
        $query->where(fn ($q) => $q->whereNull('autor_id')->orWhere('autor_id', '!=', $usuario->id));

        if ($usuario->perfil === 'barbeiro') {
            $funcionarioId = Funcionario::where('usuario_id', $usuario->id)->value('id');
            $query->where(fn ($q) => $q->where('funcionario_id', $funcionarioId)->orWhere('funcionario_anterior_id', $funcionarioId));
        }
    }
}
