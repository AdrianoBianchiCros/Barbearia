<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nome', 'telefone', 'email', 'data_nascimento', 'observacoes'])]
class Cliente extends Model
{
    use PertenceAEmpresa, SoftDeletes;

    protected $table = 'clientes';

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
        ];
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class);
    }

    public function consentimentos(): HasMany
    {
        return $this->hasMany(Consentimento::class);
    }
}
