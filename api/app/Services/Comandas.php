<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Caixa;
use App\Models\Comanda;
use App\Models\Funcionario;
use App\Models\Produto;
use App\Models\Servico;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Regras da comanda: o que o cliente consumiu, comissões e pagamento.
 */
class Comandas
{
    /** Comanda em aberto do atendimento, criada com os serviços agendados. */
    public function abrirDoAgendamento(Agendamento $agendamento): Comanda
    {
        $existente = $agendamento->comanda()->first();
        if ($existente) {
            return $existente;
        }

        return DB::transaction(function () use ($agendamento) {
            $comanda = Comanda::create([
                'agendamento_id' => $agendamento->id,
                'cliente_id' => $agendamento->cliente_id,
                'funcionario_id' => $agendamento->funcionario_id,
                'status' => 'aberta',
            ]);

            foreach ($agendamento->itens as $item) {
                $comanda->itens()->create([
                    'tipo' => 'servico',
                    'servico_id' => $item->servico_id,
                    'funcionario_id' => $agendamento->funcionario_id,
                    'descricao' => $item->nome,
                    'quantidade' => 1,
                    'preco_unitario' => $item->preco,
                    'total' => $item->preco,
                ]);
            }

            return $this->recalcular($comanda);
        });
    }

    /** Ao reabrir um atendimento, descarta a comanda que ainda não foi paga. */
    public function descartarAberta(Agendamento $agendamento): void
    {
        $comanda = $agendamento->comanda()->first();
        if ($comanda?->status === 'paga') {
            throw ValidationException::withMessages([
                'status' => 'Este atendimento já foi pago. Estorne a comanda antes de reabrir.',
            ]);
        }
        $comanda?->delete();
    }

    /**
     * Substitui os itens, aplica o desconto, registra os pagamentos no caixa aberto
     * e grava a comissão de cada item com o percentual vigente.
     *
     * @param  array<int, array{tipo:string, servico_id?:int|null, produto_id?:int|null, funcionario_id?:int|null, quantidade:int, preco_unitario:float|string}>  $itens
     * @param  array<int, array{forma:string, valor:float|string}>  $pagamentos
     */
    public function fechar(Comanda $comanda, array $itens, float $desconto, array $pagamentos, int $usuarioId, ?string $observacoes = null): Comanda
    {
        return DB::transaction(function () use ($comanda, $itens, $desconto, $pagamentos, $usuarioId, $observacoes) {
            $comanda = Comanda::whereKey($comanda->id)->lockForUpdate()->firstOrFail();
            if ($comanda->status !== 'aberta') {
                throw ValidationException::withMessages(['comanda' => 'Esta comanda já foi fechada.']);
            }

            $caixa = $this->caixaAberto();
            if (! $caixa) {
                throw ValidationException::withMessages(['caixa' => 'Abra o caixa antes de receber pagamentos.']);
            }

            $comanda->itens()->delete();
            foreach ($itens as $dados) {
                $comanda->itens()->create($this->montarItem($dados, $comanda));
            }

            $subtotal = round((float) $comanda->itens()->sum('total'), 2);
            if ($desconto > $subtotal) {
                throw ValidationException::withMessages(['desconto' => 'O desconto não pode ser maior que o total.']);
            }
            $total = round($subtotal - $desconto, 2);

            $pago = round(array_sum(array_map(fn ($p) => (float) $p['valor'], $pagamentos)), 2);
            if (abs($pago - $total) > 0.009) {
                throw ValidationException::withMessages([
                    'pagamentos' => sprintf('A soma dos pagamentos (R$ %s) deve ser igual ao total (R$ %s).',
                        number_format($pago, 2, ',', '.'), number_format($total, 2, ',', '.')),
                ]);
            }

            foreach ($pagamentos as $p) {
                $comanda->pagamentos()->create([
                    'caixa_id' => $caixa->id,
                    'forma' => $p['forma'],
                    'valor' => $p['valor'],
                ]);
            }

            // Baixa de estoque dos produtos controlados
            foreach ($comanda->itens()->where('tipo', 'produto')->whereNotNull('produto_id')->get() as $item) {
                Produto::whereKey($item->produto_id)->whereNotNull('estoque')->decrement('estoque', $item->quantidade);
            }

            $comanda->fill([
                'subtotal' => $subtotal,
                'desconto' => $desconto,
                'total' => $total,
                'status' => 'paga',
                'pago_em' => now(),
                'caixa_id' => $caixa->id,
                'fechado_por' => $usuarioId,
                'observacoes' => $observacoes,
            ])->save();

            if ($comanda->agendamento && $comanda->agendamento->status !== 'concluido') {
                $comanda->agendamento->update(['status' => 'concluido']);
            }

            return $comanda;
        });
    }

    /** Desfaz o pagamento (somente com o caixa do pagamento ainda aberto). */
    public function estornar(Comanda $comanda): Comanda
    {
        return DB::transaction(function () use ($comanda) {
            if ($comanda->status !== 'paga') {
                throw ValidationException::withMessages(['comanda' => 'Só é possível estornar comandas pagas.']);
            }
            $caixa = $this->caixaAberto();
            if (! $caixa || $caixa->id !== $comanda->caixa_id) {
                throw ValidationException::withMessages(['comanda' => 'O caixa deste pagamento já foi fechado.']);
            }

            foreach ($comanda->itens()->where('tipo', 'produto')->whereNotNull('produto_id')->get() as $item) {
                Produto::whereKey($item->produto_id)->whereNotNull('estoque')->increment('estoque', $item->quantidade);
            }
            $comanda->pagamentos()->delete();
            $comanda->update(['status' => 'aberta', 'pago_em' => null, 'caixa_id' => null, 'fechado_por' => null]);

            return $comanda;
        });
    }

    public function caixaAberto(): ?Caixa
    {
        return Caixa::whereNull('fechado_em')->latest('aberto_em')->first();
    }

    private function montarItem(array $dados, Comanda $comanda): array
    {
        $quantidade = (int) ($dados['quantidade'] ?? 1);
        $preco = round((float) $dados['preco_unitario'], 2);
        $total = round($preco * $quantidade, 2);
        $funcionarioId = $dados['funcionario_id'] ?? null;

        if ($dados['tipo'] === 'servico') {
            $servico = Servico::withTrashed()->findOrFail($dados['servico_id']);
            $funcionarioId ??= $comanda->funcionario_id;
            $percentual = $funcionarioId ? (float) Funcionario::withTrashed()->find($funcionarioId)?->comissao_percentual : 0;
            $descricao = $servico->nome;
        } else {
            $produto = Produto::withTrashed()->findOrFail($dados['produto_id']);
            // Comissão de produto só quando um barbeiro fez a venda
            $percentual = $funcionarioId ? (float) $produto->comissao_percentual : 0;
            $descricao = $produto->nome;
        }

        return [
            'tipo' => $dados['tipo'],
            'servico_id' => $dados['tipo'] === 'servico' ? $dados['servico_id'] : null,
            'produto_id' => $dados['tipo'] === 'produto' ? $dados['produto_id'] : null,
            'funcionario_id' => $funcionarioId,
            'descricao' => $descricao,
            'quantidade' => $quantidade,
            'preco_unitario' => $preco,
            'total' => $total,
            'comissao_percentual' => $percentual,
            'comissao_valor' => round($total * $percentual / 100, 2),
        ];
    }

    private function recalcular(Comanda $comanda): Comanda
    {
        $subtotal = (float) $comanda->itens()->sum('total');
        $comanda->update(['subtotal' => $subtotal, 'total' => max($subtotal - (float) $comanda->desconto, 0)]);

        return $comanda;
    }
}
