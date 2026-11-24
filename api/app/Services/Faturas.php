<?php

namespace App\Services;

use App\Models\Administrador;
use App\Models\Assinatura;
use App\Models\Empresa;
use App\Models\Fatura;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Próxima fatura da mensalidade: repete o período e o valor da última contratação e paga
 * a partir do dia seguinte ao vencimento atual. Confirmado o Pix, vira uma nova contratação.
 */
class Faturas
{
    /**
     * Fatura em aberto da barbearia, criada na primeira consulta. Não há próxima fatura sem prazo
     * (piloto), com a barbearia suspensa ou quando a última contratação foi teste ou cortesia (valor zero).
     * Se o administrador renovou por fora, a fatura antiga é cancelada e outra é gerada.
     */
    public function proxima(Empresa $empresa): ?Fatura
    {
        $abertas = Fatura::withoutGlobalScopes()->where('empresa_id', $empresa->id)->whereIn('status', Fatura::EM_ABERTO)->get();
        $ultima = $empresa->assinaturas()->latest('inicio')->latest('id')->first();

        // "teste" é o período antigo (antes do teste grátis em todo contrato): não gera fatura.
        if (! $empresa->ativo || ! $empresa->acesso_ate || ! $ultima || $ultima->periodo === 'teste' || (float) $ultima->valor <= 0) {
            return null;
        }

        $inicio = $empresa->acesso_ate->copy()->addDay();
        $atual = $abertas->first(fn (Fatura $f) => $f->inicio->isSameDay($inicio));
        $this->cancelar($abertas->reject(fn (Fatura $f) => $f->is($atual)));

        return $atual ?? Fatura::create([
            'empresa_id' => $empresa->id,
            'periodo' => $ultima->periodo,
            'valor' => $ultima->valor,
            // Depois do teste grátis, a 1ª fatura leva também a taxa de instalação.
            'taxa_instalacao' => $ultima->gratis && (float) $ultima->taxa_instalacao > 0 ? $ultima->taxa_instalacao : null,
            'vencimento' => $empresa->acesso_ate->toDateString(),
            'inicio' => $inicio->toDateString(),
            'fim' => Assinatura::calcularFim($ultima->periodo, $inicio)->toDateString(),
            'txid' => 'FAT'.strtoupper(Str::random(22)),
            'status' => 'aberta',
        ]);
    }

    public function pixConfigurado(): bool
    {
        return filled(config('assinatura.pix.chave'));
    }

    /** Código "copia e cola" do Pix desta fatura (o QR Code é desenhado no painel). */
    public function copiaECola(Fatura $fatura): string
    {
        return PixCopiaECola::gerar(
            config('assinatura.pix.chave'),
            config('assinatura.pix.recebedor'),
            config('assinatura.pix.cidade'),
            (float) $fatura->total,
            $fatura->txid,
        );
    }

    /** Recebimento conferido pelo administrador: registra a contratação e estende o acesso. */
    public function confirmar(Fatura $fatura, ?Administrador $administrador): Assinatura
    {
        return DB::transaction(function () use ($fatura, $administrador) {
            $empresa = Empresa::findOrFail($fatura->empresa_id);

            $assinatura = Assinatura::create([
                'empresa_id' => $empresa->id,
                'administrador_id' => $administrador?->id,
                'periodo' => $fatura->periodo,
                'inicio' => $fatura->inicio->toDateString(),
                'fim' => $fatura->fim->toDateString(),
                'forma_pagamento' => 'pix',
                'valor' => $fatura->valor,
                'taxa_instalacao' => $fatura->taxa_instalacao,
                'observacao' => "Fatura paga pelo painel (Pix {$fatura->txid}).",
            ]);

            if (! $empresa->acesso_ate || $fatura->fim->gt($empresa->acesso_ate)) {
                $empresa->update(['acesso_ate' => $fatura->fim->toDateString()]);
            }

            $fatura->update([
                'status' => 'paga',
                'paga_em' => now(),
                'assinatura_id' => $assinatura->id,
                'administrador_id' => $administrador?->id,
            ]);

            return $assinatura;
        });
    }

    private function cancelar(iterable $faturas): void
    {
        foreach ($faturas as $fatura) {
            $fatura->update(['status' => 'cancelada']);
        }
    }
}
