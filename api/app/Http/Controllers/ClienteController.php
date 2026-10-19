<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Support\Telefone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClienteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $busca = trim((string) $request->input('q'));
        $digitos = preg_replace('/\D/', '', $busca);

        $clientes = Cliente::query()
            ->with('consentimentoWhatsapp')
            ->when($busca !== '', function ($q) use ($busca, $digitos) {
                $q->where(function ($q) use ($busca, $digitos) {
                    $q->where('nome', 'like', "%{$busca}%");
                    if (strlen($digitos) >= 3) {
                        $q->orWhere('telefone', 'like', "%{$digitos}%");
                    }
                });
            })
            ->orderBy('nome')
            ->paginate(min($request->integer('por_pagina', 20), 100));

        $clientes->getCollection()->transform(fn ($c) => $this->formatar($c));

        return response()->json($clientes);
    }

    public function store(Request $request): JsonResponse
    {
        $dados = $this->validar($request);

        $cliente = DB::transaction(function () use ($dados, $request) {
            $cliente = Cliente::create($dados);
            $this->registrarConsentimento($cliente, $request->boolean('aceita_whatsapp'));

            return $cliente;
        });

        return response()->json($this->formatar($cliente->load('consentimentoWhatsapp')), 201);
    }

    public function show(Cliente $cliente): JsonResponse
    {
        return response()->json($this->formatar($cliente->load('consentimentoWhatsapp')));
    }

    public function update(Request $request, Cliente $cliente): JsonResponse
    {
        $dados = $this->validar($request, $cliente);

        DB::transaction(function () use ($cliente, $dados, $request) {
            $cliente->update($dados);
            if ($request->has('aceita_whatsapp')) {
                $this->registrarConsentimento($cliente, $request->boolean('aceita_whatsapp'));
            }
        });

        return response()->json($this->formatar($cliente->load('consentimentoWhatsapp')));
    }

    public function destroy(Cliente $cliente): JsonResponse
    {
        $cliente->delete();

        return response()->json(null, 204);
    }

    private function validar(Request $request, ?Cliente $cliente = null): array
    {
        $request->merge(['telefone' => Telefone::normalizar($request->input('telefone'))]);

        $unico = $this->unicoNaEmpresa('clientes', 'telefone')->whereNull('deleted_at');
        if ($cliente) {
            $unico->ignore($cliente->id);
        }

        return $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'telefone' => ['required', Telefone::REGRA, $unico],
            'email' => ['nullable', 'email', 'max:180'],
            'data_nascimento' => ['nullable', 'date', 'before:today'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'aceita_whatsapp' => ['sometimes', 'boolean'],
        ], [
            'telefone.regex' => 'Informe o WhatsApp com DDD (10 ou 11 dígitos).',
            'telefone.unique' => 'Já existe um cliente com este telefone.',
        ], [
            'telefone' => 'WhatsApp',
            'data_nascimento' => 'data de nascimento',
            'observacoes' => 'observações',
        ]);
    }

    /** Grava o histórico de consentimento (LGPD) apenas quando a escolha muda. */
    private function registrarConsentimento(Cliente $cliente, bool $aceito): void
    {
        $atual = $cliente->consentimentoWhatsapp()->first();
        if ($atual && $atual->aceito === $aceito) {
            return;
        }
        if (! $atual && ! $aceito) {
            return;
        }

        $cliente->consentimentos()->create([
            'canal' => 'whatsapp',
            'aceito' => $aceito,
            'origem' => 'recepcao',
            'registrado_em' => now(),
        ]);
    }

    private function formatar(Cliente $cliente): array
    {
        $dados = $cliente->toArray();
        unset($dados['consentimento_whatsapp']);
        $dados['data_nascimento'] = $cliente->data_nascimento?->toDateString();
        $dados['aceita_whatsapp'] = (bool) $cliente->consentimentoWhatsapp?->aceito;

        return $dados;
    }
}
