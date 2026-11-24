<?php

namespace App\Http\Controllers;

use App\Models\Administrador;
use App\Models\Assinatura;
use App\Models\Fatura;
use App\Notifications\PagamentoInformado;
use App\Services\Faturas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * Mensalidade da barbearia no painel do dono: faturas já pagas, a próxima e o pagamento por Pix.
 */
class AssinaturaController extends Controller
{
    public function __construct(private Faturas $faturas) {}

    public function show(Request $request): JsonResponse
    {
        $empresa = $request->user()->empresa;

        $pagas = $empresa->assinaturas()->latest('inicio')->latest('id')->get()->map(fn ($a) => [
            'id' => $a->id,
            // O teste grátis aparece como "Teste grátis", sem valor; a taxa de instalação soma no pagamento em que entrou.
            'periodo' => $a->gratis ? 'Teste grátis de '.($a->inicio->diffInDays($a->fim) + 1).' dias' : Assinatura::rotuloCobranca($a->periodo),
            'inicio' => $a->inicio->toDateString(),
            'fim' => $a->fim->toDateString(),
            'valor' => $a->gratis ? '0.00' : number_format((float) $a->valor + (float) $a->taxa_instalacao, 2, '.', ''),
            'gratis' => $a->gratis,
            'forma_pagamento' => config("assinatura.formas_pagamento.{$a->forma_pagamento}", $a->forma_pagamento),
            'registrada_em' => $a->created_at?->toDateString(),
        ]);

        return response()->json([
            'acesso' => $empresa->resumoAcesso(),
            'proxima' => $this->faturas->proxima($empresa),
            'pix_disponivel' => $this->faturas->pixConfigurado(),
            'pagas' => $pagas,
        ]);
    }

    /** Código Pix (copia e cola) da fatura em aberto; o painel desenha o QR Code com ele. */
    public function pix(Fatura $fatura): JsonResponse
    {
        $this->exigirEmAberto($fatura);
        abort_unless($this->faturas->pixConfigurado(), 422, 'O pagamento por Pix ainda não está disponível. Fale com a '.config('plataforma.nome').'.');

        return response()->json([
            'copia_e_cola' => $this->faturas->copiaECola($fatura),
            'recebedor' => config('assinatura.pix.recebedor'),
            'valor' => $fatura->valor,
        ]);
    }

    /** O dono avisa que pagou: a fatura fica aguardando a confirmação do administrador. */
    public function informarPagamento(Request $request, Fatura $fatura): JsonResponse
    {
        $this->exigirEmAberto($fatura);

        if ($fatura->status === 'aberta') {
            $fatura->update(['status' => 'informada', 'informada_em' => now()]);

            try {
                Notification::send(Administrador::where('ativo', true)->get(), new PagamentoInformado($request->user()->empresa, $fatura));
            } catch (\Throwable $e) {
                report($e); // o aviso fica na administração mesmo sem o e-mail
            }
        }

        return response()->json(['fatura' => $fatura->fresh(), 'message' => 'Recebemos o seu aviso. O acesso é renovado assim que o pagamento for confirmado.']);
    }

    private function exigirEmAberto(Fatura $fatura): void
    {
        abort_unless(in_array($fatura->status, Fatura::EM_ABERTO, true), 422, 'Esta fatura não está mais em aberto.');
    }
}
