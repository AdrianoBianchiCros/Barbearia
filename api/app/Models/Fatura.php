<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Fatura da mensalidade da barbearia: a próxima a pagar, gerada a partir da última contratação.
 * O dono paga por Pix e avisa; o administrador confirma o recebimento e o acesso é renovado.
 */
#[Fillable(['empresa_id', 'periodo', 'valor', 'taxa_instalacao', 'vencimento', 'inicio', 'fim', 'txid', 'status', 'informada_em', 'paga_em', 'assinatura_id', 'administrador_id'])]
class Fatura extends Model
{
    use PertenceAEmpresa;

    /** Situações em que a fatura ainda espera o pagamento. */
    public const EM_ABERTO = ['aberta', 'informada'];

    protected $appends = ['periodo_rotulo', 'total'];

    protected function casts(): array
    {
        return [
            'vencimento' => 'date:Y-m-d',
            'inicio' => 'date:Y-m-d',
            'fim' => 'date:Y-m-d',
            'valor' => 'decimal:2',
            'taxa_instalacao' => 'decimal:2',
            'informada_em' => 'datetime',
            'paga_em' => 'datetime',
        ];
    }

    /** Valor a pagar: mensalidade + taxa de instalação (só na 1ª fatura depois do teste grátis). */
    protected function total(): Attribute
    {
        return Attribute::get(fn () => number_format((float) $this->valor + (float) $this->taxa_instalacao, 2, '.', ''));
    }

    protected function periodoRotulo(): Attribute
    {
        return Attribute::get(fn () => Assinatura::rotuloCobranca($this->periodo));
    }
}
