<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Cliente;
use Illuminate\Support\Carbon;

/**
 * Limite para o próprio cliente marcar pela página ou remarcar pelo link:
 * no máximo 1 horário por semana (segunda a domingo) e 4 por mês.
 * A recepção não tem esse limite.
 */
class LimiteAgendamentoCliente
{
    public const POR_SEMANA = 1;

    public const POR_MES = 4;

    /** Cancelados não contam; quem faltou conta (o horário foi usado). */
    private const STATUS_CONTAM = ['agendado', 'confirmado', 'em_atendimento', 'concluido', 'faltou'];

    private const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

    private const DIAS = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];

    /**
     * Devolve o motivo do bloqueio ou null se o cliente pode marcar no dia informado.
     *
     * @param  string  $data  dia do novo horário (Y-m-d), no fuso da barbearia
     */
    public function motivo(Cliente $cliente, string $data, string $fuso, ?int $ignorarAgendamentoId = null): ?string
    {
        $dia = Carbon::createFromFormat('Y-m-d', $data, $fuso)->startOfDay();

        $semana = $this->doCliente($cliente, $dia->copy()->startOfWeek(Carbon::MONDAY), $dia->copy()->endOfWeek(Carbon::SUNDAY), $ignorarAgendamentoId);
        if ($semana->count() >= self::POR_SEMANA) {
            $outro = $semana->first()->inicio->copy()->setTimezone($fuso);

            return 'Você já tem um horário nesta semana ('.self::DIAS[$outro->dayOfWeek].', '.$outro->format('d/m').' às '.$outro->format('H:i').'). '
                .'Pela página dá para marcar 1 horário por semana; para outro, fale com a barbearia.';
        }

        $mes = $this->doCliente($cliente, $dia->copy()->startOfMonth(), $dia->copy()->endOfMonth(), $ignorarAgendamentoId);
        if ($mes->count() >= self::POR_MES) {
            return 'Você já tem '.self::POR_MES.' horários em '.self::MESES[$dia->month - 1].'. '
                .'Pela página dá para marcar até '.self::POR_MES.' por mês; para outro, fale com a barbearia.';
        }

        return null;
    }

    private function doCliente(Cliente $cliente, Carbon $de, Carbon $ate, ?int $ignorar)
    {
        return Agendamento::query()
            ->where('cliente_id', $cliente->id)
            ->whereIn('status', self::STATUS_CONTAM)
            ->when($ignorar, fn ($q) => $q->where('id', '!=', $ignorar))
            ->where('inicio', '>=', $de->copy()->utc())
            ->where('inicio', '<=', $ate->copy()->utc())
            ->orderBy('inicio')
            ->get(['id', 'inicio']);
    }
}
