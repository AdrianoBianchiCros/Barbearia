<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\AvisoAgenda;
use App\Models\User;
use Carbon\CarbonInterface;

/** Grava os avisos do sino da agenda (cancelamento e remarcação). */
class AvisosAgenda
{
    public function cancelamento(Agendamento $agendamento, ?User $autor): AvisoAgenda
    {
        return $this->registrar($agendamento, 'cancelamento', $autor);
    }

    public function remarcacao(Agendamento $agendamento, CarbonInterface $inicioAnterior, ?int $funcionarioAnteriorId, ?User $autor): AvisoAgenda
    {
        return $this->registrar($agendamento, 'remarcacao', $autor, [
            'inicio_anterior' => $inicioAnterior->copy()->utc(),
            'funcionario_anterior_id' => $funcionarioAnteriorId !== $agendamento->funcionario_id ? $funcionarioAnteriorId : null,
        ]);
    }

    private function registrar(Agendamento $agendamento, string $tipo, ?User $autor, array $extra = []): AvisoAgenda
    {
        return AvisoAgenda::create([
            'agendamento_id' => $agendamento->id,
            'funcionario_id' => $agendamento->funcionario_id,
            'tipo' => $tipo,
            'origem' => $autor ? 'painel' : 'cliente',
            'autor_id' => $autor?->id,
            'cliente_nome' => $agendamento->cliente()->withTrashed()->value('nome') ?? 'Cliente',
            'inicio' => $agendamento->inicio->copy()->utc(),
        ] + $extra);
    }
}
