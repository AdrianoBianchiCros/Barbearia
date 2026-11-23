<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Empresa;

/**
 * Dados do horário em texto pronto para o cliente: usados no e-mail de confirmação e na
 * mensagem do WhatsApp que a recepção envia pelo link do número na agenda.
 * Precisa do agendamento com empresa, funcionario e itens carregados.
 */
class ResumoAgendamento
{
    private const DIAS = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];

    /**
     * @return array{barbearia: string, email_barbearia: ?string, nome: string, data: string, hora: string,
     *               barbeiro: string, servicos: string, valor: string, endereco: ?string, link: string}
     */
    public function dados(Agendamento $agendamento, Cliente $cliente): array
    {
        $empresa = $agendamento->empresa;
        $inicio = $agendamento->inicio->copy()->setTimezone($empresa->fuso_horario);
        $nome = trim($cliente->nome);

        return [
            'barbearia' => $empresa->nome,
            'email_barbearia' => $empresa->email ?: null,
            'nome' => strtok($nome, ' ') ?: $nome,
            'data' => self::DIAS[$inicio->dayOfWeek].', '.$inicio->format('d/m/Y'),
            'hora' => $inicio->format('H:i'),
            'barbeiro' => $agendamento->funcionario->nome,
            'servicos' => $agendamento->itens->pluck('nome')->join(' + '),
            'valor' => number_format((float) $agendamento->valor_total, 2, ',', '.'),
            'endereco' => $this->endereco($empresa),
            'link' => $agendamento->linkCliente(),
        ];
    }

    /** Mesmo conteúdo do e-mail, com o negrito do WhatsApp (*texto*). */
    public function textoWhatsapp(Agendamento $agendamento, Cliente $cliente): string
    {
        $d = $this->dados($agendamento, $cliente);

        $linhas = [
            "Olá, {$d['nome']}! Seu horário na *{$d['barbearia']}* está marcado:",
            '',
            "*{$d['data']} às {$d['hora']}*, com {$d['barbeiro']}",
            "Serviço: {$d['servicos']} · R$ {$d['valor']}",
        ];
        if ($d['endereco']) {
            $linhas[] = "Endereço: {$d['endereco']}";
        }
        array_push($linhas,
            '',
            "Para confirmar, remarcar ou cancelar: {$d['link']}",
            '',
            'Se não puder vir, cancele pelo link para liberar o horário para outro cliente. Até lá!',
        );

        return implode("\n", $linhas);
    }

    private function endereco(Empresa $empresa): ?string
    {
        $rua = trim(implode(', ', array_filter([$empresa->logradouro, $empresa->numero, $empresa->complemento])));
        $cidade = trim(implode(' - ', array_filter([$empresa->cidade, $empresa->uf])));
        $partes = array_filter([$rua, $empresa->bairro, $cidade]);

        return $partes ? implode(' · ', $partes) : null;
    }
}
