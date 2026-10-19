<?php

namespace App\Http\Controllers;

use App\Models\MensagemWhatsapp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Configuração do WhatsApp da barbearia e histórico das mensagens enviadas (somente dono).
 */
class WhatsappController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->config($request));
    }

    public function update(Request $request): JsonResponse
    {
        $empresa = $request->user()->empresa;

        $dados = $request->validate([
            'wa_ativo' => ['sometimes', 'boolean'],
            'wa_lembrete_24h' => ['sometimes', 'boolean'],
            'wa_lembrete_2h' => ['sometimes', 'boolean'],
            'wa_phone_number_id' => ['nullable', 'string', 'regex:/^\d{6,30}$/', Rule::unique('empresas', 'wa_phone_number_id')->ignore($empresa->id)],
            'wa_token' => ['nullable', 'string', 'max:2000'],
            'cancelamento_antecedencia_horas' => ['sometimes', 'integer', 'min:0', 'max:72'],
        ], [
            'wa_phone_number_id.regex' => 'O ID do número tem só dígitos (copie do painel da Meta).',
            'wa_phone_number_id.unique' => 'Este número já está ligado a outra barbearia.',
        ], [
            'wa_phone_number_id' => 'ID do número',
            'wa_token' => 'token de acesso',
            'cancelamento_antecedencia_horas' => 'antecedência para remarcar/cancelar',
        ]);

        // O token não volta para o painel: em branco mantém o atual.
        if (blank($dados['wa_token'] ?? null)) {
            unset($dados['wa_token']);
        }
        if ($request->boolean('remover_token')) {
            $dados['wa_token'] = null;
        }

        $empresa->update($dados);

        return response()->json($this->config($request));
    }

    /** Histórico de mensagens: GET /whatsapp/mensagens?status=falhou&page=2 */
    public function mensagens(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'status' => ['nullable', Rule::in(['pendente', 'enviado', 'entregue', 'lido', 'falhou', 'ignorado'])],
        ]);
        $fuso = $request->user()->empresa->fuso_horario;

        $pagina = MensagemWhatsapp::query()
            ->with('cliente:id,nome', 'agendamento:id,inicio,status')
            ->when($filtros['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate(30);

        return response()->json([
            'data' => $pagina->getCollection()->map(fn (MensagemWhatsapp $m) => [
                'id' => $m->id,
                'tipo' => $m->tipo,
                'status' => $m->status,
                'telefone' => $m->telefone,
                'cliente' => $m->cliente?->nome,
                'horario' => $m->agendamento?->inicio?->copy()->setTimezone($fuso)->toIso8601String(),
                'erro' => $m->erro,
                'resposta' => $m->resposta,
                'criada_em' => $m->created_at->copy()->setTimezone($fuso)->toIso8601String(),
            ]),
            'pagina' => $pagina->currentPage(),
            'ultima_pagina' => $pagina->lastPage(),
            'total' => $pagina->total(),
        ]);
    }

    private function config(Request $request): array
    {
        $empresa = $request->user()->empresa->fresh();
        $inicioMes = now($empresa->fuso_horario)->startOfMonth()->utc();

        return [
            'modo' => config('whatsapp.driver') === 'cloud' ? 'real' : 'teste',
            'wa_ativo' => $empresa->wa_ativo,
            'wa_lembrete_24h' => $empresa->wa_lembrete_24h,
            'wa_lembrete_2h' => $empresa->wa_lembrete_2h,
            'wa_phone_number_id' => $empresa->wa_phone_number_id,
            'wa_token_configurado' => $empresa->wa_token_configurado,
            'cancelamento_antecedencia_horas' => $empresa->cancelamento_antecedencia_horas,
            'webhook_url' => url('/api/webhooks/whatsapp'),
            'mes' => MensagemWhatsapp::where('created_at', '>=', $inicioMes)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ];
    }
}
