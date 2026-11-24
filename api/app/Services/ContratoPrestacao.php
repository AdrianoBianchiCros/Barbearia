<?php

namespace App\Services;

use App\Models\Empresa;
use App\Support\Telefone;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

/**
 * Contrato de prestação de serviço e licença de uso do sistema (SaaS), gerado em PDF
 * com os dados da barbearia, do dono e da contratação. Modelo em
 * resources/views/contratos/prestacao-servico.blade.php.
 */
class ContratoPrestacao
{
    /**
     * @param  array{periodo: string, forma_pagamento: string, valor: float|string}  $contratacao
     * @return string conteúdo do PDF
     */
    public function pdf(Empresa $empresa, string $donoNome, string $donoEmail, array $contratacao, Carbon $inicio): string
    {
        return Pdf::loadView('contratos.prestacao-servico', $this->dados($empresa, $donoNome, $donoEmail, $contratacao, $inicio))
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    public static function nomeArquivo(Empresa $empresa): string
    {
        return 'Contrato - '.preg_replace('/[\\\\\/:*?"<>|]/', '', $empresa->nome).'.pdf';
    }

    public function dados(Empresa $empresa, string $donoNome, string $donoEmail, array $contratacao, Carbon $inicio): array
    {
        $periodo = config('assinatura.periodos.'.$contratacao['periodo'], []);
        $meses = $periodo['meses'] ?? null;
        $valor = (float) $contratacao['valor'];
        $mensalidade = $meses ? round($valor / $meses, 2) : $valor;
        $fidelidade = (int) config('assinatura.fidelidade_meses');
        $percentual = (float) config('assinatura.multa_rescisoria_percentual');

        // Multa se o cancelamento for pedido após N meses: % das mensalidades que faltam para completar a fidelidade.
        $multas = [];
        for ($n = 1; $n <= $fidelidade; $n++) {
            $multas[] = [
                'momento' => 'Após '.$n.' '.($n === 1 ? 'mês' : 'meses'),
                'valor' => round(($fidelidade - $n) * $mensalidade * $percentual / 100, 2),
            ];
        }

        $rua = trim(implode(', ', array_filter([$empresa->logradouro, $empresa->numero, $empresa->complemento])));
        $cidade = trim(implode('/', array_filter([$empresa->cidade, $empresa->uf])));
        $cep = $empresa->cep ? preg_replace('/^(\d{5})(\d{3})$/', '$1-$2', $empresa->cep) : null;

        return [
            'contratada' => config('plataforma'),
            'contratante' => [
                'nome' => $empresa->nome,
                'razao_social' => $empresa->razao_social ?: $empresa->nome,
                'documento' => $this->documento($empresa->documento),
                'tipo_documento' => strlen((string) $empresa->documento) === 11 ? 'CPF' : 'CNPJ',
                'endereco' => implode(' · ', array_filter([$rua, $empresa->bairro, $cidade, $cep ? 'CEP '.$cep : null])) ?: null,
                'telefone' => $empresa->telefone ? Telefone::formatar($empresa->telefone) : null,
                'email' => $empresa->email ?: $donoEmail,
                'representante' => $donoNome,
                'representante_email' => $donoEmail,
            ],
            'plano' => $periodo['rotulo'] ?? $contratacao['periodo'],
            'valor' => $valor,
            'mensalidade' => $mensalidade,
            'meses' => $meses,
            'forma_pagamento' => config('assinatura.formas_pagamento.'.$contratacao['forma_pagamento'], $contratacao['forma_pagamento']),
            'inicio' => $inicio,
            'fidelidade' => $fidelidade,
            'percentual_multa' => $percentual,
            'multas' => $multas,
            'carencia_dias' => (int) config('assinatura.carencia_dias'),
            'emitido_em' => now('America/Sao_Paulo'),
        ];
    }

    private function documento(?string $documento): ?string
    {
        $d = preg_replace('/\D/', '', (string) $documento);

        return match (strlen($d)) {
            14 => preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $d),
            11 => preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $d),
            default => $documento ?: null,
        };
    }
}
