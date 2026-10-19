<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Serviço que um barbeiro realiza, com preço e duração próprios (opcionais).
 */
class FuncionarioServico extends Pivot
{
    use PertenceAEmpresa;

    protected $table = 'funcionario_servicos';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'preco' => 'decimal:2',
            'duracao_minutos' => 'integer',
        ];
    }
}
