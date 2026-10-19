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
 * Agenda do dia: barbeiros, expediente, bloqueios e agendamentos.
 * Horários são devolvidos no fuso da barbearia (ISO 8601 com offset).
 */
class AgendaController extends Controller
{
    public function dia(Request $request): JsonResponse
    {
        $request->validate(['data' => ['nullable', 'date_format:Y-m-d']]);

        $usuario = $request->user();
        $fuso = $usuario->empresa->fuso_horario;
        $inicioDia = $request->filled('data')
            ? Carbon::createFromFormat('Y-m-d', $request->input('data'), $fuso)->startOfDay()
            : now($fuso)->startOfDay();
        $fimDia = $inicioDia->copy()->addDay();
        [$inicioUtc, $fimUtc] = [$inicioDia->copy()->utc(), $fimDia->copy()->utc()];

        $barbeiros = Funcionario::query()
            ->where('ativo', true)
            ->where('atende_clientes', true)
            ->with(['horariosTrabalho' => fn ($q) => $q->where('dia_semana', $inicioDia->dayOfWeek)->orderBy('inicio')])
            ->orderBy('nome')
            ->get();

        $bloqueios = BloqueioAgenda::query()
            ->where('inicio', '<', $fimUtc)
            ->where('fim', '>', $inicioUtc)
            ->get();

        $agendamentos = Agendamento::query()
            ->with(['cliente:id,nome,telefone,observacoes', 'itens:id,agendamento_id,nome,duracao_minutos'])
            ->where('inicio', '<', $fimUtc)
            ->where('fim', '>', $inicioUtc)
            ->where('status', '!=', 'cancelado')
            ->orderBy('inicio')
            ->get();

        $hora = fn (CarbonInterface $dt) => $dt->copy()->setTimezone($fuso)->toIso8601String();
        // Bloqueios de vários dias aparecem recortados para o dia exibido.
        $recorte = fn (CarbonInterface $dt) => $hora($dt->max($inicioUtc)->min($fimUtc));

        return response()->json([
            'data' => $inicioDia->toDateString(),
            'fuso_horario' => $fuso,
            'agora' => $hora(now()),
            'meu_funcionario_id' => Funcionario::where('usuario_id', $usuario->id)->value('id'),
            'barbeiros' => $barbeiros->map(fn (Funcionario $f) => [
                'id' => $f->id,
                'nome' => $f->nome,
                'foto_url' => $f->foto_url,
                'expediente' => $f->horariosTrabalho->map(fn ($h) => [
                    'inicio' => substr($h->inicio, 0, 5),
                    'fim' => substr($h->fim, 0, 5),
                ])->values(),
                'bloqueios' => $bloqueios
                    ->filter(fn ($b) => $b->funcionario_id === null || $b->funcionario_id === $f->id)
                    ->map(fn ($b) => [
                        'id' => $b->id,
                        'inicio' => $recorte($b->inicio),
                        'fim' => $recorte($b->fim),
                        'motivo' => $b->motivo,
                    ])->values(),
                'agendamentos' => $agendamentos
                    ->where('funcionario_id', $f->id)
                    ->map(fn (Agendamento $a) => [
                        'id' => $a->id,
                        'inicio' => $hora($a->inicio),
                        'fim' => $hora($a->fim),
                        'status' => $a->status,
                        'origem' => $a->origem,
                        'valor_total' => $a->valor_total,
                        'observacoes' => $a->observacoes,
                        'cliente' => $a->cliente?->only(['id', 'nome', 'telefone', 'observacoes']),
                        'servicos' => $a->itens->pluck('nome')->values(),
                    ])->values(),
            ])->values(),
        ]);
    }

    /**
     * Atualiza a situação do agendamento (confirmar, iniciar, concluir, faltou, cancelar).
     * Barbeiros só alteram os próprios agendamentos.
     */
    public function status(Request $request, Agendamento $agendamento): JsonResponse
    {
        $dados = $request->validate([
            'status' => ['required', 'in:'.implode(',', Agendamento::STATUS)],
        ]);

        $usuario = $request->user();
        if ($usuario->perfil === 'barbeiro' && $agendamento->funcionario?->usuario_id !== $usuario->id) {
            return response()->json(['message' => 'Você só pode alterar os seus próprios atendimentos.'], 403);
        }

        $agendamento->status = $dados['status'];
        $agendamento->cancelado_em = $dados['status'] === 'cancelado' ? now() : null;
        $agendamento->save();

        return response()->json(['id' => $agendamento->id, 'status' => $agendamento->status]);
    }
}
