<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Comanda;
use App\Models\ComandaItem;
use App\Models\Funcionario;
use App\Models\HorarioTrabalho;
use App\Models\Lancamento;
use App\Models\Pagamento;
use App\Services\Disponibilidade;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Relatórios gerenciais: faturamento, atendimentos e faltas, desempenho por barbeiro,
 * serviços/produtos mais vendidos, horários de pico e clientes que sumiram.
 */
class RelatorioController extends Controller
{
    private const MAX_DIAS = 366;

    public function resumo(Request $request): JsonResponse
    {
        [$inicio, $fim, $fuso] = $this->periodo($request);
        $dias = (int) $inicio->diffInDays($fim) + 1;
        $anteriorFim = $inicio->copy()->subDay()->endOfDay();
        $anteriorInicio = $anteriorFim->copy()->subDays($dias - 1)->startOfDay();

        $atual = $this->numeros($inicio, $fim);
        $anterior = $this->numeros($anteriorInicio, $anteriorFim);

        return response()->json([
            'inicio' => $inicio->toDateString(),
            'fim' => $fim->toDateString(),
            'periodo_anterior' => ['inicio' => $anteriorInicio->toDateString(), 'fim' => $anteriorFim->toDateString()],
            'indicadores' => $atual,
            'anterior' => $anterior,
            'formas_pagamento' => $this->formasPagamento($inicio, $fim),
            'serie' => $this->serie($inicio, $fim, $fuso),
            'barbeiros' => $this->barbeiros($inicio, $fim),
            'servicos' => $this->maisVendidos('servico', $inicio, $fim),
            'produtos' => $this->maisVendidos('produto', $inicio, $fim),
            'horarios_pico' => $this->horariosPico($inicio, $fim, $fuso),
        ]);
    }

    /**
     * Clientes atendidos, novos e recorrentes no período, e clientes que não voltam há X dias.
     */
    public function clientes(Request $request): JsonResponse
    {
        $request->validate(['dias_sem_voltar' => ['nullable', 'integer', 'min:7', 'max:365']]);
        [$inicio, $fim, $fuso] = $this->periodo($request);
        $limite = (int) $request->input('dias_sem_voltar', 45);

        $concluidos = Agendamento::query()
            ->where('status', 'concluido')
            ->get(['id', 'cliente_id', 'funcionario_id', 'inicio']);
        $porCliente = $concluidos->groupBy('cliente_id');

        $noPeriodo = $concluidos->filter(fn ($a) => $a->inicio->between($inicio->copy()->utc(), $fim->copy()->utc()));
        $atendidos = $noPeriodo->pluck('cliente_id')->unique();
        $novos = $atendidos->filter(fn ($id) => $porCliente[$id]->min('inicio')->gte($inicio->copy()->utc()));

        $gastoPorCliente = Comanda::where('status', 'paga')
            ->whereNotNull('cliente_id')
            ->selectRaw('cliente_id, SUM(total) as total')
            ->groupBy('cliente_id')
            ->pluck('total', 'cliente_id');
        $comFuturo = Agendamento::whereIn('status', ['agendado', 'confirmado'])
            ->where('inicio', '>=', now())
            ->pluck('cliente_id')
            ->unique()
            ->flip();
        $barbeiros = Funcionario::withTrashed()->pluck('nome', 'id');
        $corte = now()->subDays($limite);

        $sumidos = $porCliente
            ->map(fn (Collection $visitas, $clienteId) => [
                'cliente_id' => $clienteId,
                'ultima' => $visitas->sortByDesc('inicio')->first(),
                'visitas' => $visitas->count(),
            ])
            ->filter(fn ($c) => $c['ultima']->inicio->lt($corte) && ! isset($comFuturo[$c['cliente_id']]))
            ->sortByDesc(fn ($c) => $c['ultima']->inicio)
            ->take(100);
        $dadosClientes = Cliente::whereIn('id', $sumidos->pluck('cliente_id'))
            ->with('consentimentoWhatsapp')
            ->get(['id', 'nome', 'telefone'])
            ->keyBy('id');

        return response()->json([
            'atendidos' => $atendidos->count(),
            'novos' => $novos->count(),
            'recorrentes' => $atendidos->count() - $novos->count(),
            'dias_sem_voltar' => $limite,
            // Clientes excluídos (sem cadastro) ficam de fora
            'sumidos' => $sumidos->filter(fn ($c) => $dadosClientes->has($c['cliente_id']))->map(fn ($c) => [
                'id' => $c['cliente_id'],
                'nome' => $dadosClientes[$c['cliente_id']]->nome,
                'telefone' => $dadosClientes[$c['cliente_id']]->telefone,
                'aceita_whatsapp' => (bool) $dadosClientes[$c['cliente_id']]->consentimentoWhatsapp?->aceito,
                'ultima_visita' => $c['ultima']->inicio->copy()->setTimezone($fuso)->toDateString(),
                'dias_sem_vir' => (int) $c['ultima']->inicio->diffInDays(now()),
                'visitas' => $c['visitas'],
                'total_gasto' => round((float) ($gastoPorCliente[$c['cliente_id']] ?? 0), 2),
                'ultimo_barbeiro' => $barbeiros[$c['ultima']->funcionario_id] ?? null,
            ])->values(),
        ]);
    }

    /** @return array{Carbon, Carbon, string} início e fim no fuso da barbearia */
    private function periodo(Request $request): array
    {
        $request->validate([
            'inicio' => ['nullable', 'date_format:Y-m-d'],
            'fim' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:inicio'],
        ]);
        $fuso = $request->user()->empresa->fuso_horario;
        $inicio = Carbon::createFromFormat('Y-m-d', $request->input('inicio', now($fuso)->startOfMonth()->toDateString()), $fuso)->startOfDay();
        $fim = Carbon::createFromFormat('Y-m-d', $request->input('fim', now($fuso)->toDateString()), $fuso)->endOfDay();

        if ($inicio->diffInDays($fim) >= self::MAX_DIAS) {
            abort(response()->json(['message' => 'Escolha um período de até 1 ano.'], 422));
        }

        return [$inicio, $fim, $fuso];
    }

    private function entre(Carbon $inicio, Carbon $fim): array
    {
        return [$inicio->copy()->utc(), $fim->copy()->utc()];
    }

    /** Números principais de um período (usados também na comparação com o anterior). */
    private function numeros(Carbon $inicio, Carbon $fim): array
    {
        $comandas = Comanda::where('status', 'paga')->whereBetween('pago_em', $this->entre($inicio, $fim))->get(['id', 'total', 'desconto']);
        $agendamentos = Agendamento::whereBetween('inicio', $this->entre($inicio, $fim))->get(['status', 'origem']);
        $porStatus = $agendamentos->countBy('status');
        $concluidos = $porStatus['concluido'] ?? 0;
        $faltas = $porStatus['faltou'] ?? 0;

        $itens = ComandaItem::whereIn('comanda_id', $comandas->pluck('id'))->get(['tipo', 'total', 'comissao_valor']);
        $faturamento = round((float) $comandas->sum('total'), 2);
        $despesas = round((float) Lancamento::where('tipo', 'despesa')->whereBetween('pago_em', $this->entre($inicio, $fim))->sum('valor'), 2);
        $comissoes = round((float) $itens->sum('comissao_valor'), 2);

        return [
            'faturamento' => $faturamento,
            'comandas_pagas' => $comandas->count(),
            'ticket_medio' => $comandas->count() ? round($faturamento / $comandas->count(), 2) : 0,
            'descontos' => round((float) $comandas->sum('desconto'), 2),
            'faturamento_servicos' => round((float) $itens->where('tipo', 'servico')->sum('total'), 2),
            'faturamento_produtos' => round((float) $itens->where('tipo', 'produto')->sum('total'), 2),
            'agendamentos' => $agendamentos->where('status', '!=', 'cancelado')->count(),
            'concluidos' => $concluidos,
            'faltas' => $faltas,
            'cancelados' => $porStatus['cancelado'] ?? 0,
            // Faltas sobre os atendimentos que já deveriam ter acontecido
            'taxa_faltas' => ($concluidos + $faltas) ? round($faltas / ($concluidos + $faltas) * 100, 1) : 0,
            'agendados_online' => $agendamentos->where('origem', 'link_publico')->where('status', '!=', 'cancelado')->count(),
            'comissoes' => $comissoes,
            'despesas_pagas' => $despesas,
            'resultado' => round($faturamento - $comissoes - $despesas, 2),
        ];
    }

    private function formasPagamento(Carbon $inicio, Carbon $fim): array
    {
        $totais = Pagamento::query()
            ->whereHas('comanda', fn ($q) => $q->where('status', 'paga')->whereBetween('pago_em', $this->entre($inicio, $fim)))
            ->selectRaw('forma, SUM(valor) as total')
            ->groupBy('forma')
            ->pluck('total', 'forma');

        return collect(Pagamento::FORMAS)
            ->map(fn ($f) => ['forma' => $f, 'total' => round((float) ($totais[$f] ?? 0), 2)])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /** Faturamento e atendimentos por dia (ou por mês em períodos longos). */
    private function serie(Carbon $inicio, Carbon $fim, string $fuso): array
    {
        $porMes = $inicio->diffInDays($fim) > 62;
        $chave = fn (Carbon $dt) => $dt->copy()->setTimezone($fuso)->format($porMes ? 'Y-m' : 'Y-m-d');

        $faturado = Comanda::where('status', 'paga')
            ->whereBetween('pago_em', $this->entre($inicio, $fim))
            ->get(['total', 'pago_em'])
            ->groupBy(fn ($c) => $chave($c->pago_em))
            ->map(fn ($g) => round((float) $g->sum('total'), 2));
        $atendidos = Agendamento::where('status', 'concluido')
            ->whereBetween('inicio', $this->entre($inicio, $fim))
            ->get(['inicio'])
            ->groupBy(fn ($a) => $chave($a->inicio))
            ->map->count();

        $periodo = CarbonPeriod::create($inicio->copy()->startOfDay(), $porMes ? '1 month' : '1 day', $fim->copy()->startOfDay());
        $pontos = [];
        foreach ($periodo as $data) {
            $k = $data->format($porMes ? 'Y-m' : 'Y-m-d');
            $pontos[$k] = ['data' => $k, 'faturamento' => $faturado[$k] ?? 0, 'atendimentos' => $atendidos[$k] ?? 0];
        }
        if ($porMes) {
            $pontos[$fim->format('Y-m')] ??= ['data' => $fim->format('Y-m'), 'faturamento' => $faturado[$fim->format('Y-m')] ?? 0, 'atendimentos' => $atendidos[$fim->format('Y-m')] ?? 0];
        }

        return ['agrupamento' => $porMes ? 'mes' : 'dia', 'pontos' => array_values($pontos)];
    }

    /** Desempenho por barbeiro, com ocupação = minutos agendados ÷ minutos de expediente. */
    private function barbeiros(Carbon $inicio, Carbon $fim): array
    {
        $entre = $this->entre($inicio, $fim);
        $agendamentos = Agendamento::whereBetween('inicio', $entre)->get(['funcionario_id', 'status', 'inicio', 'fim'])->groupBy('funcionario_id');
        $itens = ComandaItem::whereHas('comanda', fn ($q) => $q->where('status', 'paga')->whereBetween('pago_em', $entre))
            ->whereNotNull('funcionario_id')
            ->get(['funcionario_id', 'comanda_id', 'tipo', 'total', 'comissao_valor'])
            ->groupBy('funcionario_id');

        // Minutos de expediente por dia da semana, para cada barbeiro
        $expediente = HorarioTrabalho::all()->groupBy('funcionario_id')->map(
            fn ($hs) => $hs->groupBy('dia_semana')->map(fn ($faixas) => $faixas->sum(
                fn ($h) => Carbon::parse($h->inicio)->diffInMinutes(Carbon::parse($h->fim)),
            )),
        );
        $diasPorSemana = collect(CarbonPeriod::create($inicio->copy()->startOfDay(), $fim->copy()->startOfDay()))
            ->countBy(fn ($d) => $d->dayOfWeek);

        $ids = $agendamentos->keys()->merge($itens->keys())->merge(
            Funcionario::where('ativo', true)->where('atende_clientes', true)->pluck('id'),
        )->unique();

        return Funcionario::withTrashed()->whereIn('id', $ids)->orderBy('nome')->get(['id', 'nome'])
            ->map(function (Funcionario $f) use ($agendamentos, $itens, $expediente, $diasPorSemana) {
                $ags = $agendamentos->get($f->id, collect());
                $meus = $itens->get($f->id, collect());
                $ocupados = $ags->whereIn('status', Disponibilidade::STATUS_OCUPAM)
                    ->sum(fn ($a) => $a->inicio->diffInMinutes($a->fim));
                $disponiveis = $diasPorSemana->sum(fn ($qtd, $dia) => $qtd * ($expediente[$f->id][$dia] ?? 0));
                $concluidos = $ags->where('status', 'concluido')->count();
                $faltas = $ags->where('status', 'faltou')->count();
                $faturado = round((float) $meus->sum('total'), 2);
                $comandas = $meus->pluck('comanda_id')->unique()->count();

                return [
                    'funcionario_id' => $f->id,
                    'nome' => $f->nome,
                    'concluidos' => $concluidos,
                    'faltas' => $faltas,
                    'taxa_faltas' => ($concluidos + $faltas) ? round($faltas / ($concluidos + $faltas) * 100, 1) : 0,
                    'faturamento' => $faturado,
                    'ticket_medio' => $comandas ? round($faturado / $comandas, 2) : 0,
                    'comissao' => round((float) $meus->sum('comissao_valor'), 2),
                    'ocupacao' => $disponiveis ? min(round($ocupados / $disponiveis * 100, 1), 100) : 0,
                ];
            })->values()->all();
    }

    private function maisVendidos(string $tipo, Carbon $inicio, Carbon $fim): array
    {
        return ComandaItem::query()
            ->where('tipo', $tipo)
            ->whereHas('comanda', fn ($q) => $q->where('status', 'paga')->whereBetween('pago_em', $this->entre($inicio, $fim)))
            ->selectRaw('descricao, SUM(quantidade) as quantidade, SUM(total) as receita')
            ->groupBy('descricao')
            ->orderByDesc('receita')
            ->limit(8)
            ->get()
            ->map(fn ($i) => ['nome' => $i->descricao, 'quantidade' => (int) $i->quantidade, 'receita' => round((float) $i->receita, 2)])
            ->all();
    }

    /** Quantidade de agendamentos por dia da semana × hora (no fuso da barbearia). */
    private function horariosPico(Carbon $inicio, Carbon $fim, string $fuso): array
    {
        return Agendamento::where('status', '!=', 'cancelado')
            ->whereBetween('inicio', $this->entre($inicio, $fim))
            ->get(['inicio'])
            ->map(fn ($a) => $a->inicio->copy()->setTimezone($fuso))
            ->groupBy(fn ($dt) => $dt->dayOfWeek.'-'.$dt->hour)
            ->map(fn ($g, $k) => [
                'dia_semana' => (int) explode('-', $k)[0],
                'hora' => (int) explode('-', $k)[1],
                'quantidade' => $g->count(),
            ])
            ->values()
            ->all();
    }
}
