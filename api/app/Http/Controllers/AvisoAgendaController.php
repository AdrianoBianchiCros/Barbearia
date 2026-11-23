<?php

namespace App\Http\Controllers;

use App\Models\AvisoAgenda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sino da agenda: cancelamentos e remarcações que o usuário ainda não viu.
 * Dono e recepção veem todos os barbeiros; o barbeiro, só a própria agenda.
 */
class AvisoAgendaController extends Controller
{
    private const LIMITE = 30;

    /** GET /api/avisos */
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $fuso = $usuario->empresa->fuso_horario;
        $visiveis = fn () => AvisoAgenda::query()->visiveisPara($usuario);
        $vistoAte = (int) $usuario->avisos_vistos_ate;

        $avisos = $visiveis()
            ->leftJoin('funcionarios', 'funcionarios.id', '=', 'avisos_agenda.funcionario_id')
            ->leftJoin('funcionarios as anterior', 'anterior.id', '=', 'avisos_agenda.funcionario_anterior_id')
            ->select('avisos_agenda.*', 'funcionarios.nome as barbeiro', 'anterior.nome as barbeiro_anterior')
            ->latest('avisos_agenda.id')
            ->limit(self::LIMITE)
            ->get();

        return response()->json([
            'nao_vistos' => $visiveis()->where('id', '>', $vistoAte)->count(),
            'avisos' => $avisos->map(fn (AvisoAgenda $a) => [
                'id' => $a->id,
                'agendamento_id' => $a->agendamento_id,
                'tipo' => $a->tipo,
                'origem' => $a->origem,
                'cliente' => $a->cliente_nome,
                'barbeiro' => $a->barbeiro,
                'barbeiro_anterior' => $a->barbeiro_anterior,
                'inicio' => $a->inicio->copy()->setTimezone($fuso)->toIso8601String(),
                'inicio_anterior' => $a->inicio_anterior?->copy()->setTimezone($fuso)->toIso8601String(),
                'quando' => $a->created_at->copy()->setTimezone($fuso)->toIso8601String(),
                'visto' => $a->id <= $vistoAte,
            ])->values(),
        ]);
    }

    /** POST /api/avisos/vistos: o usuário abriu o sino. */
    public function vistos(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $ultimo = AvisoAgenda::query()->visiveisPara($usuario)->max('id');
        if ($ultimo > (int) $usuario->avisos_vistos_ate) {
            $usuario->forceFill(['avisos_vistos_ate' => $ultimo])->save();
        }

        return response()->json(['nao_vistos' => 0]);
    }
}
