<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\BloqueioAgenda;
use App\Models\Funcionario;
use App\Services\Comandas;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
            ->with(['cliente:id,nome,telefone,observacoes', 'itens:id,agendamento_id,servico_id,nome,duracao_minutos', 'comanda:id,agendamento_id,status,total'])
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
                        'servico_ids' => $a->itens->pluck('servico_id')->values(),
                        'comanda' => $a->comanda?->only(['id', 'status', 'total']),
                    ])->values(),
            ])->values(),
        ]);
    }

    /**
     * Busca agendamentos por nome ou telefone do cliente, ou pelo serviço.
     * Devolve os próximos (a partir de hoje) e os dos últimos 60 dias.
     */
    public function busca(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        $termo = trim($request->input('q'));
        $digitos = preg_replace('/\D/', '', $termo);
        $fuso = $request->user()->empresa->fuso_horario;
        $hoje = now($fuso)->startOfDay()->utc();

        $base = fn () => Agendamento::query()
            ->with(['cliente:id,nome,telefone', 'funcionario:id,nome', 'itens:id,agendamento_id,nome'])
            ->where('status', '!=', 'cancelado')
            ->where(function ($q) use ($termo, $digitos) {
                $q->whereHas('cliente', function ($c) use ($termo, $digitos) {
                    $c->where('nome', 'like', "%{$termo}%");
                    if (strlen($digitos) >= 3) {
                        $c->orWhere('telefone', 'like', "%{$digitos}%");
                    }
                })->orWhereHas('itens', fn ($i) => $i->where('nome', 'like', "%{$termo}%"));
            });

        $formatar = fn (Agendamento $a) => [
            'id' => $a->id,
            'data' => $a->inicio->copy()->setTimezone($fuso)->toDateString(),
            'inicio' => $a->inicio->copy()->setTimezone($fuso)->toIso8601String(),
            'fim' => $a->fim->copy()->setTimezone($fuso)->toIso8601String(),
            'status' => $a->status,
            'cliente' => $a->cliente?->only(['id', 'nome', 'telefone']),
            'barbeiro' => $a->funcionario?->only(['id', 'nome']),
            'servicos' => $a->itens->pluck('nome')->values(),
        ];

        return response()->json([
            'proximos' => $base()->where('inicio', '>=', $hoje)->orderBy('inicio')->limit(15)->get()->map($formatar),
            'anteriores' => $base()
                ->where('inicio', '<', $hoje)
                ->where('inicio', '>=', $hoje->copy()->subDays(60))
                ->orderByDesc('inicio')
                ->limit(10)
                ->get()
                ->map($formatar),
        ]);
    }

    /**
     * Atualiza a situação do agendamento (confirmar, iniciar, concluir, faltou, cancelar).
     * Barbeiros só alteram os próprios agendamentos.
     */
    public function status(Request $request, Agendamento $agendamento, Comandas $comandas): JsonResponse
    {
        $dados = $request->validate([
            'status' => ['required', 'in:'.implode(',', Agendamento::STATUS)],
        ]);

        $usuario = $request->user();
        if ($usuario->perfil === 'barbeiro' && $agendamento->funcionario?->usuario_id !== $usuario->id) {
            return response()->json(['message' => 'Você só pode alterar os seus próprios atendimentos.'], 403);
        }

        DB::transaction(function () use ($agendamento, $dados, $comandas) {
            // Concluído gera a comanda para receber; saindo de concluído, a comanda não paga é descartada
            if ($agendamento->status === 'concluido' && $dados['status'] !== 'concluido') {
                $comandas->descartarAberta($agendamento);
            }

            $agendamento->status = $dados['status'];
            $agendamento->cancelado_em = $dados['status'] === 'cancelado' ? now() : null;
            $agendamento->save();

            if ($dados['status'] === 'concluido') {
                $comandas->abrirDoAgendamento($agendamento);
            }
        });

        return response()->json([
            'id' => $agendamento->id,
            'status' => $agendamento->status,
            'comanda' => $agendamento->comanda()->first()?->only(['id', 'status', 'total']),
        ]);
    }
}
