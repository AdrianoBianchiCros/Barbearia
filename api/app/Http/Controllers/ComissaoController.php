<?php

namespace App\Http\Controllers;

use App\Models\ComandaItem;
use App\Models\Funcionario;
use App\Models\Lancamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Comissões por período: geradas pelas comandas pagas, menos vales e repasses já feitos.
 * O barbeiro vê apenas a própria.
 */
class ComissaoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'inicio' => ['nullable', 'date_format:Y-m-d'],
            'fim' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:inicio'],
        ]);

        $usuario = $request->user();
        $fuso = $usuario->empresa->fuso_horario;
        $inicio = Carbon::createFromFormat('Y-m-d', $request->input('inicio', now($fuso)->startOfMonth()->toDateString()), $fuso)->startOfDay();
        $fim = Carbon::createFromFormat('Y-m-d', $request->input('fim', now($fuso)->toDateString()), $fuso)->endOfDay();
        [$de, $ate] = [$inicio->copy()->utc(), $fim->copy()->utc()];

        $barbeiros = Funcionario::withTrashed()
            ->when($usuario->perfil === 'barbeiro', fn ($q) => $q->where('usuario_id', $usuario->id))
            ->orderBy('nome')
            ->get(['id', 'nome', 'comissao_percentual', 'ativo', 'deleted_at']);

        $itens = ComandaItem::query()
            ->whereIn('funcionario_id', $barbeiros->pluck('id'))
            ->whereHas('comanda', fn ($q) => $q->where('status', 'paga')->whereBetween('pago_em', [$de, $ate]))
            ->get(['funcionario_id', 'comanda_id', 'tipo', 'total', 'comissao_valor'])
            ->groupBy('funcionario_id');

        $lancamentos = Lancamento::query()
            ->whereIn('tipo', ['vale', 'repasse_comissao'])
            ->whereIn('funcionario_id', $barbeiros->pluck('id'))
            ->whereBetween('pago_em', [$de, $ate])
            ->get(['funcionario_id', 'tipo', 'valor'])
            ->groupBy('funcionario_id');

        $linhas = $barbeiros->map(function (Funcionario $f) use ($itens, $lancamentos) {
            $meus = $itens->get($f->id, collect());
            $lanc = $lancamentos->get($f->id, collect());
            $comissao = round((float) $meus->sum('comissao_valor'), 2);
            $vales = round((float) $lanc->where('tipo', 'vale')->sum('valor'), 2);
            $repasses = round((float) $lanc->where('tipo', 'repasse_comissao')->sum('valor'), 2);

            return [
                'funcionario_id' => $f->id,
                'nome' => $f->nome,
                'comissao_percentual' => $f->comissao_percentual,
                'atendimentos' => $meus->where('tipo', 'servico')->pluck('comanda_id')->unique()->count(),
                'faturado_servicos' => round((float) $meus->where('tipo', 'servico')->sum('total'), 2),
                'faturado_produtos' => round((float) $meus->where('tipo', 'produto')->sum('total'), 2),
                'comissao' => $comissao,
                'vales' => $vales,
                'repasses' => $repasses,
                'saldo' => round($comissao - $vales - $repasses, 2),
            ];
        })
            // Barbeiros excluídos só aparecem se tiveram movimento no período
            ->filter(fn ($l, $i) => ! $barbeiros[$i]->trashed() || $l['comissao'] || $l['vales'] || $l['repasses'])
            ->values();

        return response()->json([
            'inicio' => $inicio->toDateString(),
            'fim' => $fim->toDateString(),
            'barbeiros' => $linhas,
            'totais' => [
                'comissao' => round($linhas->sum('comissao'), 2),
                'vales' => round($linhas->sum('vales'), 2),
                'repasses' => round($linhas->sum('repasses'), 2),
                'saldo' => round($linhas->sum('saldo'), 2),
            ],
        ]);
    }
}
