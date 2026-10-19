<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Funcionario;
use App\Services\Disponibilidade;
use App\Services\WhatsApp\Mensageiro;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * O cliente confirma, remarca ou cancela o próprio horário pelo link
 * recebido no WhatsApp (/a/{token}), sem login.
 */
class ClienteAgendamentoController extends Controller
{
    public function show(Request $request): View
    {
        $agendamento = $this->agendamento($request)->load('empresa', 'funcionario', 'itens', 'cliente');
        $empresa = $agendamento->empresa;
        $base = url("/api/a/{$agendamento->token}");

        return view('pagina.agendamento', [
            'a' => $agendamento,
            'empresa' => $empresa,
            'inicio' => $agendamento->inicio->copy()->setTimezone($empresa->fuso_horario),
            'ativo' => $this->ativo($agendamento),
            'podeAlterar' => $this->podeAlterar($agendamento),
            'config' => [
                'urlHorarios' => "{$base}/horarios",
                'urlConfirmar' => "{$base}/confirmar",
                'urlCancelar' => "{$base}/cancelar",
                'urlRemarcar' => "{$base}/remarcar",
                'hoje' => now($empresa->fuso_horario)->toDateString(),
                'diasAntecedencia' => PaginaPublicaController::DIAS_ANTECEDENCIA,
            ],
        ]);
    }

    public function confirmar(Request $request): JsonResponse
    {
        $agendamento = $this->agendamento($request);
        if (! $this->ativo($agendamento)) {
            return $this->erro('Este horário não pode mais ser confirmado.');
        }

        if ($agendamento->status === 'agendado') {
            $agendamento->update(['status' => 'confirmado', 'confirmado_em' => now()]);
        }

        return response()->json(['status' => $agendamento->status]);
    }

    public function cancelar(Request $request): JsonResponse
    {
        $agendamento = $this->agendamento($request);
        $this->exigirAntecedencia($agendamento);

        $agendamento->update(['status' => 'cancelado', 'cancelado_em' => now()]);

        return response()->json(['status' => 'cancelado']);
    }

    /** Horários livres no mesmo barbeiro e com os mesmos serviços. */
    public function horarios(Request $request): JsonResponse
    {
        $agendamento = $this->agendamento($request);
        $dados = $request->validate(['data' => ['required', 'date_format:Y-m-d']]);
        if (! $this->podeAlterar($agendamento) || ! $this->dataPermitida($agendamento, $dados['data'])) {
            return response()->json([]);
        }

        return response()->json($this->disponibilidade($agendamento)->horariosLivres(
            $agendamento->funcionario, $dados['data'], $this->duracao($agendamento), $agendamento->id,
        ));
    }

    public function remarcar(Request $request, Mensageiro $mensageiro): JsonResponse
    {
        $agendamento = $this->agendamento($request);
        $this->exigirAntecedencia($agendamento);

        $dados = $request->validate([
            'data' => ['required', 'date_format:Y-m-d'],
            'hora' => ['required', 'date_format:H:i'],
        ]);
        if (! $this->dataPermitida($agendamento, $dados['data'])) {
            throw ValidationException::withMessages([
                'data' => 'Escolha uma data entre hoje e os próximos '.PaginaPublicaController::DIAS_ANTECEDENCIA.' dias.',
            ]);
        }

        DB::transaction(function () use ($agendamento, $dados) {
            // Trava a agenda do barbeiro para que dois clientes não peguem o mesmo horário.
            Funcionario::whereKey($agendamento->funcionario_id)->lockForUpdate()->first();
            $duracao = $this->duracao($agendamento);
            $disponibilidade = $this->disponibilidade($agendamento);

            $motivo = $disponibilidade->motivoIndisponivel(
                $agendamento->funcionario, $dados['data'], $dados['hora'], $duracao, false, $agendamento->id,
            );
            if ($motivo) {
                throw ValidationException::withMessages(['hora' => $motivo]);
            }

            $inicio = $disponibilidade->instante($dados['data'], $dados['hora']);
            $agendamento->update([
                'inicio' => $inicio,
                'fim' => $inicio->copy()->addMinutes($duracao),
                'status' => 'confirmado',
                'confirmado_em' => now(),
            ]);
        });

        $mensageiro->agendamento($agendamento, 'remarcacao');
        $inicio = $agendamento->inicio->copy()->setTimezone($agendamento->empresa->fuso_horario);

        return response()->json([
            'data' => $inicio->toDateString(),
            'hora' => $inicio->format('H:i'),
            'status' => $agendamento->status,
        ]);
    }

    private function agendamento(Request $request): Agendamento
    {
        return $request->attributes->get('agendamento');
    }

    private function ativo(Agendamento $a): bool
    {
        return in_array($a->status, ['agendado', 'confirmado'], true) && $a->inicio->isFuture();
    }

    /** Remarcar e cancelar só até X horas antes (política da barbearia). */
    private function podeAlterar(Agendamento $a): bool
    {
        return $this->ativo($a)
            && now()->addHours($a->empresa->cancelamento_antecedencia_horas)->lte($a->inicio);
    }

    private function exigirAntecedencia(Agendamento $a): void
    {
        if (! $this->ativo($a)) {
            abort($this->erro('Este horário não pode mais ser alterado.'));
        }
        if (! $this->podeAlterar($a)) {
            $horas = $a->empresa->cancelamento_antecedencia_horas;
            abort($this->erro('Alterações só até '.$horas.' h antes do horário. Fale com a barbearia pelo WhatsApp.'));
        }
    }

    private function dataPermitida(Agendamento $a, string $data): bool
    {
        $fuso = $a->empresa->fuso_horario;

        return $data >= now($fuso)->toDateString()
            && $data <= now($fuso)->addDays(PaginaPublicaController::DIAS_ANTECEDENCIA)->toDateString();
    }

    private function duracao(Agendamento $a): int
    {
        return (int) $a->itens()->sum('duracao_minutos');
    }

    private function disponibilidade(Agendamento $a): Disponibilidade
    {
        return new Disponibilidade($a->empresa->fuso_horario);
    }

    private function erro(string $mensagem): JsonResponse
    {
        return response()->json(['message' => $mensagem], 422);
    }
}
