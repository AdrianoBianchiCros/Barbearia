<?php

namespace App\Console\Commands;

use App\Models\Agendamento;
use App\Models\Empresa;
use App\Services\WhatsApp\Mensageiro;
use App\Support\EmpresaAtual;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Cria os lembretes de 24 h e 2 h antes do horário. Roda a cada 5 minutos pelo
 * agendador; cada lembrete é criado uma única vez por horário do agendamento.
 */
#[Signature('whatsapp:lembretes')]
#[Description('Cria os lembretes do WhatsApp dos próximos agendamentos')]
class EnviarLembretesWhatsapp extends Command
{
    /**
     * Para cada lembrete: com quanta antecedência sai e quanto tempo o cliente precisa
     * ter marcado antes (quem marcou há pouco já recebeu a confirmação).
     */
    private const LEMBRETES = [
        'lembrete_24h' => ['antes' => 24 * 60, 'marcado_ha_pelo_menos' => 30 * 60, 'campo' => 'wa_lembrete_24h'],
        'lembrete_2h' => ['antes' => 2 * 60, 'marcado_ha_pelo_menos' => 4 * 60, 'campo' => 'wa_lembrete_2h'],
    ];

    public function handle(Mensageiro $mensageiro, EmpresaAtual $empresaAtual): int
    {
        $criados = 0;

        foreach (Empresa::where('ativo', true)->where('wa_ativo', true)->get() as $empresa) {
            $empresaAtual->definir($empresa->id);

            foreach (self::LEMBRETES as $tipo => $regra) {
                if (! $empresa->{$regra['campo']}) {
                    continue;
                }

                $agendamentos = Agendamento::query()
                    ->whereIn('status', ['agendado', 'confirmado'])
                    ->where('inicio', '>', now())
                    ->where('inicio', '<=', now()->addMinutes($regra['antes']))
                    ->with('cliente.consentimentoWhatsapp', 'funcionario', 'itens')
                    ->get()
                    ->filter(fn ($a) => $a->created_at->diffInMinutes($a->inicio) >= $regra['marcado_ha_pelo_menos']);

                foreach ($agendamentos as $agendamento) {
                    $agendamento->setRelation('empresa', $empresa);
                    if ($mensageiro->agendamento($agendamento, $tipo)) {
                        $criados++;
                    }
                }
            }
        }

        $empresaAtual->definir(null);
        $this->info("{$criados} lembrete(s) criado(s).");

        return self::SUCCESS;
    }
}
