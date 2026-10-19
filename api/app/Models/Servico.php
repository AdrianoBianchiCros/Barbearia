<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nome', 'descricao', 'duracao_minutos', 'preco', 'ativo'])]
class Servico extends Model
{
    use PertenceAEmpresa, SoftDeletes;

    protected $table = 'servicos';

    protected function casts(): array
    {
        return [
            'duracao_minutos' => 'integer',
            'preco' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    public function funcionarios(): BelongsToMany
    {
        return $this->belongsToMany(Funcionario::class, 'funcionario_servicos')
            ->using(FuncionarioServico::class)
            ->withPivot(['empresa_id', 'preco', 'duracao_minutos'])
            ->withTimestamps();
    }
}
