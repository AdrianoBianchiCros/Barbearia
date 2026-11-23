<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\BloqueioAgenda;
use App\Models\Funcionario;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Painel do barbeiro: a agenda do dia só com os atendimentos dele.
 * A comissão usa o ComissaoController, que já filtra pelo barbeiro logado.
 */
class BarbeiroController extends Controller
{
    public function agenda(Request $request): JsonResponse
    {
        $request->validate(['data' => ['nullable', 'date_format:Y-m-d']]);

        $usuario = $request->user();
        $barbeiro = Funcionario::where('usuario_id', $usuario->id)->first();
        abort_unless($barbeiro, 404, 'Seu acesso não está ligado a um barbeiro. Fale com o dono da barbearia.');

        $fuso = $usuario->empresa->fuso_horario;
        $inicioDia = $request->filled('data')
            ? Carbon::createFromFormat('Y-m-d', $request->input('data'), $fuso)->startOfDay()
            : now($fuso)->startOfDay();
        [$inicioUtc, $fimUtc] = [$inicioDia->copy()->utc(), $inicioDia->copy()->addDay()->utc()];

        $hora = fn (CarbonInterface $dt) => $dt->copy()->setTimezone($fuso)->toIso8601String();
        // Bloqueios de vários dias aparecem recortados para o dia exibido.
        $recorte = fn (CarbonInterface $dt) => $hora($dt->max($inicioUtc)->min($fimUtc));

        $agendamentos = Agendamento::query()
            ->with(['cliente:id,nome,observacoes', 'itens:id,agendamento_id,nome,duracao_minutos'])
            ->where('funcionario_id', $barbeiro->id)
            ->where('inicio', '<', $fimUtc)
            ->where('fim', '>', $inicioUtc)
            ->where('status', '!=', 'cancelado')
            ->orderBy('inicio')
            ->get();

        $bloqueios = BloqueioAgenda::query()
            ->where(fn ($q) => $q->whereNull('funcionario_id')->orWhere('funcionario_id', $barbeiro->id))
            ->where('inicio', '<', $fimUtc)
            ->where('fim', '>', $inicioUtc)
            ->orderBy('inicio')
            ->get();

        return response()->json([
            'data' => $inicioDia->toDateString(),
            'agora' => $hora(now()),
            'barbeiro' => ['id' => $barbeiro->id, 'nome' => $barbeiro->nome, 'foto_url' => $barbeiro->foto_url],
            'expediente' => $barbeiro->horariosTrabalho()
                ->where('dia_semana', $inicioDia->dayOfWeek)
                ->orderBy('inicio')
                ->get()
                ->map(fn ($h) => ['inicio' => substr($h->inicio, 0, 5), 'fim' => substr($h->fim, 0, 5)])
                ->values(),
            'bloqueios' => $bloqueios->map(fn (BloqueioAgenda $b) => [
                'inicio' => $recorte($b->inicio),
                'fim' => $recorte($b->fim),
                'motivo' => $b->motivo,
            ])->values(),
            // Só o necessário para o atendimento: sem telefone nem dados de contato do cliente.
            'agendamentos' => $agendamentos->map(fn (Agendamento $a) => [
                'id' => $a->id,
                'inicio' => $hora($a->inicio),
                'fim' => $hora($a->fim),
                'status' => $a->status,
                'valor_total' => $a->valor_total,
                'observacoes' => $a->observacoes,
                'cliente' => $a->cliente?->only(['nome', 'observacoes']),
                'servicos' => $a->itens->pluck('nome')->values(),
            ])->values(),
        ]);
    }
}
