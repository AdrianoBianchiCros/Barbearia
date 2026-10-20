<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\BloqueioAgenda;
use App\Models\Funcionario;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Calcula os horários livres de um barbeiro em um dia.
 *
 * Livre = dentro do expediente, fora de folgas/bloqueios, sem conflito com
 * outro agendamento e (se for hoje) ainda não passou. Os cálculos são feitos
 * em minutos desde 00:00 no fuso da barbearia.
 */
class Disponibilidade
{
    /** Intervalo entre os horários oferecidos (ex.: 09:00, 09:15, 09:30…). */
    public const PASSO_MINUTOS = 15;

    /** Situações que ocupam a cadeira. Cancelado e faltou liberam o horário. */
    public const STATUS_OCUPAM = ['agendado', 'confirmado', 'em_atendimento', 'concluido'];

    public function __construct(public readonly string $fuso) {}

    /**
     * Horários de início ("HH:MM") em que cabe um atendimento de $duracao minutos.
     */
    public function horariosLivres(Funcionario $barbeiro, string $data, int $duracao, ?int $ignorarAgendamentoId = null): array
    {
        $dia = $this->inicioDoDia($data);
        $faixas = $this->expediente($barbeiro, $dia);
        $ocupado = [
            ...$this->bloqueios($barbeiro, $dia),
            ...$this->agendamentos($barbeiro, $dia, $ignorarAgendamentoId),
        ];
        $minimo = $this->minutoMinimo($dia);

        $livres = [];
        foreach ($faixas as [$ini, $fim]) {
            for ($m = $ini; $m + $duracao <= $fim; $m += self::PASSO_MINUTOS) {
                if ($m >= $minimo && ! $this->conflita($m, $m + $duracao, $ocupado)) {
                    $livres[] = $this->formatar($m);
                }
            }
        }

        return $livres;
    }

    /**
     * Primeiro horário do dia em que cabe um encaixe: sem sobrepor outro cliente,
     * ignorando expediente e bloqueios. Começa no início do expediente (ou às 08:00 se o
     * barbeiro não trabalha nesse dia); hoje, a partir de agora se já passou desse horário.
     */
    public function primeiroEncaixe(Funcionario $barbeiro, string $data, int $duracao, ?int $ignorarAgendamentoId = null): ?string
    {
        $dia = $this->inicioDoDia($data);
        $agora = now($this->fuso);
        if ($dia->lt($agora->copy()->startOfDay())) {
            return null; // dia que já passou
        }

        $passo = 5;
        $faixas = $this->expediente($barbeiro, $dia);
        $inicio = $faixas[0][0] ?? 8 * 60;
        if ($dia->isSameDay($agora)) {
            $inicio = max($inicio, (int) ceil(($agora->hour * 60 + $agora->minute) / $passo) * $passo);
        }

        $ocupado = $this->agendamentos($barbeiro, $dia, $ignorarAgendamentoId);
        for ($m = $inicio; $m + $duracao <= 24 * 60; $m += $passo) {
            if (! $this->conflita($m, $m + $duracao, $ocupado)) {
                return $this->formatar($m);
            }
        }

        return null;
    }

    /**
     * Verifica um horário específico. Devolve null se estiver livre ou o motivo do conflito.
     * No encaixe, a recepção pode agendar fora do expediente/bloqueio, mas nunca por cima
     * de outro cliente.
     */
    public function motivoIndisponivel(
        Funcionario $barbeiro,
        string $data,
        string $hora,
        int $duracao,
        bool $encaixe = false,
        ?int $ignorarAgendamentoId = null,
    ): ?string {
        $dia = $this->inicioDoDia($data);
        $ini = $this->minutos($hora);
        $fim = $ini + $duracao;

        if ($fim > 24 * 60) {
            return 'O atendimento passaria da meia-noite.';
        }
        if ($this->conflita($ini, $fim, $this->agendamentos($barbeiro, $dia, $ignorarAgendamentoId))) {
            return 'Este horário já está ocupado por outro cliente.';
        }
        if ($encaixe) {
            return null;
        }
        if ($ini < $this->minutoMinimo($dia)) {
            return 'Este horário já passou.';
        }
        $dentroDoExpediente = collect($this->expediente($barbeiro, $dia))
            ->contains(fn ($f) => $ini >= $f[0] && $fim <= $f[1]);
        if (! $dentroDoExpediente) {
            return 'Fora do expediente do barbeiro.';
        }
        if ($this->conflita($ini, $fim, $this->bloqueios($barbeiro, $dia))) {
            return 'O barbeiro está com a agenda bloqueada neste horário.';
        }

        return null;
    }

    /** Converte data + hora locais da barbearia em um instante UTC. */
    public function instante(string $data, string $hora): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', "{$data} {$hora}", $this->fuso)->utc();
    }

    private function inicioDoDia(string $data): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $data, $this->fuso)->startOfDay();
    }

    /** @return array<array{int,int}> */
    private function expediente(Funcionario $barbeiro, Carbon $dia): array
    {
        return $barbeiro->horariosTrabalho()
            ->where('dia_semana', $dia->dayOfWeek)
            ->orderBy('inicio')
            ->get()
            ->map(fn ($h) => [$this->minutos(substr($h->inicio, 0, 5)), $this->minutos(substr($h->fim, 0, 5))])
            ->all();
    }

    /** @return array<array{int,int}> */
    private function bloqueios(Funcionario $barbeiro, Carbon $dia): array
    {
        [$ini, $fim] = $this->limitesUtc($dia);

        return BloqueioAgenda::query()
            ->where(fn ($q) => $q->whereNull('funcionario_id')->orWhere('funcionario_id', $barbeiro->id))
            ->where('inicio', '<', $fim)
            ->where('fim', '>', $ini)
            ->get()
            ->map(fn ($b) => [$this->minutosNoDia($b->inicio, $dia), $this->minutosNoDia($b->fim, $dia)])
            ->all();
    }

    /** @return array<array{int,int}> */
    private function agendamentos(Funcionario $barbeiro, Carbon $dia, ?int $ignorar): array
    {
        [$ini, $fim] = $this->limitesUtc($dia);

        return Agendamento::query()
            ->where('funcionario_id', $barbeiro->id)
            ->whereIn('status', self::STATUS_OCUPAM)
            ->when($ignorar, fn ($q) => $q->where('id', '!=', $ignorar))
            ->where('inicio', '<', $fim)
            ->where('fim', '>', $ini)
            ->get()
            ->map(fn ($a) => [$this->minutosNoDia($a->inicio, $dia), $this->minutosNoDia($a->fim, $dia)])
            ->all();
    }

    private function conflita(int $ini, int $fim, array $ocupados): bool
    {
        foreach ($ocupados as [$a, $b]) {
            if ($ini < $b && $fim > $a) {
                return true;
            }
        }

        return false;
    }

    /** Hoje: só a partir do próximo horário cheio do passo; outros dias: sem limite (passado bloqueado). */
    private function minutoMinimo(Carbon $dia): int
    {
        $agora = now($this->fuso);
        if ($dia->isSameDay($agora)) {
            $min = $agora->hour * 60 + $agora->minute;

            return (int) ceil($min / self::PASSO_MINUTOS) * self::PASSO_MINUTOS;
        }

        return $dia->lt($agora) ? PHP_INT_MAX : 0;
    }

    private function limitesUtc(Carbon $dia): array
    {
        return [$dia->copy()->utc(), $dia->copy()->addDay()->utc()];
    }

    private function minutosNoDia(CarbonInterface $instante, Carbon $dia): int
    {
        $local = $instante->copy()->setTimezone($this->fuso);
        if ($local->lt($dia)) {
            return 0;
        }
        if ($local->gte($dia->copy()->addDay())) {
            return 24 * 60;
        }

        return $local->hour * 60 + $local->minute;
    }

    private function minutos(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

    private function formatar(int $minutos): string
    {
        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }
}
