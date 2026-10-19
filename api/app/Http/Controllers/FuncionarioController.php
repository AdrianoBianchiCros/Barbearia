<?php

namespace App\Http\Controllers;

use App\Models\Funcionario;
use App\Support\Telefone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Barbeiros e demais funcionários: dados, serviços que realizam, expediente e foto.
 */
class FuncionarioController extends Controller
{
    private const RELACOES = ['servicos:id,nome,duracao_minutos,preco', 'horariosTrabalho'];

    public function index(Request $request): JsonResponse
    {
        $funcionarios = Funcionario::query()
            ->with(self::RELACOES)
            ->when($request->boolean('ativos'), fn ($q) => $q->where('ativo', true))
            ->orderBy('nome')
            ->get();

        return response()->json($funcionarios->map(fn ($f) => $this->formatar($f)));
    }

    public function store(Request $request): JsonResponse
    {
        $funcionario = Funcionario::create($this->validar($request));

        return response()->json($this->formatar($funcionario->load(self::RELACOES)), 201);
    }

    public function show(Funcionario $funcionario): JsonResponse
    {
        return response()->json($this->formatar($funcionario->load(self::RELACOES)));
    }

    public function update(Request $request, Funcionario $funcionario): JsonResponse
    {
        $funcionario->update($this->validar($request));

        return response()->json($this->formatar($funcionario->load(self::RELACOES)));
    }

    public function destroy(Funcionario $funcionario): JsonResponse
    {
        $funcionario->delete();

        return response()->json(null, 204);
    }

    /**
     * Define quais serviços o barbeiro realiza, com preço/duração próprios opcionais.
     */
    public function servicos(Request $request, Funcionario $funcionario): JsonResponse
    {
        $dados = $request->validate([
            'servicos' => ['present', 'array'],
            'servicos.*.id' => ['required', 'integer', 'distinct', $this->existeNaEmpresa('servicos')],
            'servicos.*.preco' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'servicos.*.duracao_minutos' => ['nullable', 'integer', 'min:5', 'max:480'],
        ]);

        $sync = collect($dados['servicos'])->mapWithKeys(fn ($s) => [$s['id'] => [
            'empresa_id' => $funcionario->empresa_id,
            'preco' => $s['preco'] ?? null,
            'duracao_minutos' => $s['duracao_minutos'] ?? null,
        ]]);
        $funcionario->servicos()->sync($sync);

        return response()->json($this->formatar($funcionario->load(self::RELACOES)));
    }

    /**
     * Substitui o expediente semanal do barbeiro.
     * Formato: [{dia_semana: 0-6, inicio: "09:00", fim: "12:00"}, ...]
     */
    public function horarios(Request $request, Funcionario $funcionario): JsonResponse
    {
        $dados = $request->validate([
            'horarios' => ['present', 'array'],
            'horarios.*.dia_semana' => ['required', 'integer', 'between:0,6'],
            'horarios.*.inicio' => ['required', 'date_format:H:i'],
            'horarios.*.fim' => ['required', 'date_format:H:i'],
        ]);

        $porDia = collect($dados['horarios'])->groupBy('dia_semana');
        foreach ($porDia as $dia => $faixas) {
            $ordenadas = $faixas->sortBy('inicio')->values();
            foreach ($ordenadas as $i => $faixa) {
                if ($faixa['fim'] <= $faixa['inicio']) {
                    throw ValidationException::withMessages(['horarios' => 'O horário final deve ser depois do inicial.']);
                }
                if ($i > 0 && $faixa['inicio'] < $ordenadas[$i - 1]['fim']) {
                    throw ValidationException::withMessages(['horarios' => 'Existem horários sobrepostos no mesmo dia.']);
                }
            }
        }

        DB::transaction(function () use ($funcionario, $dados) {
            $funcionario->horariosTrabalho()->delete();
            foreach ($dados['horarios'] as $faixa) {
                $funcionario->horariosTrabalho()->create($faixa);
            }
        });

        return response()->json($this->formatar($funcionario->load(self::RELACOES)));
    }

    public function foto(Request $request, Funcionario $funcionario): JsonResponse
    {
        $request->validate(['foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);

        $antiga = $funcionario->foto_path;
        $funcionario->foto_path = $request->file('foto')->store("empresas/{$funcionario->empresa_id}/equipe", 'public');
        $funcionario->save();

        if ($antiga) {
            Storage::disk('public')->delete($antiga);
        }

        return response()->json($this->formatar($funcionario->load(self::RELACOES)));
    }

    private function validar(Request $request): array
    {
        $request->merge(['telefone' => Telefone::normalizar($request->input('telefone'))]);

        return $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'telefone' => ['nullable', Telefone::REGRA],
            'email' => ['nullable', 'email', 'max:180'],
            'especialidades' => ['nullable', 'string', 'max:160'],
            'tipo_vinculo' => ['nullable', 'in:clt,parceiro,autonomo'],
            'comissao_percentual' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'atende_clientes' => ['sometimes', 'boolean'],
            'ativo' => ['sometimes', 'boolean'],
        ], [
            'telefone.regex' => 'Informe o telefone com DDD (10 ou 11 dígitos).',
        ], [
            'tipo_vinculo' => 'tipo de vínculo',
            'comissao_percentual' => 'comissão',
        ]);
    }

    private function formatar(Funcionario $funcionario): array
    {
        $funcionario->append('foto_url');

        return array_merge($funcionario->toArray(), [
            'servicos' => $funcionario->servicos->sortBy('nome')->map(fn ($s) => [
                'id' => $s->id,
                'nome' => $s->nome,
                'preco' => $s->pivot->preco,
                'duracao_minutos' => $s->pivot->duracao_minutos,
                'preco_padrao' => $s->preco,
                'duracao_padrao' => $s->duracao_minutos,
            ])->values(),
            'horarios_trabalho' => $funcionario->horariosTrabalho
                ->sortBy([['dia_semana', 'asc'], ['inicio', 'asc']])
                ->map(fn ($h) => [
                    'dia_semana' => $h->dia_semana,
                    'inicio' => substr($h->inicio, 0, 5),
                    'fim' => substr($h->fim, 0, 5),
                ])->values(),
        ]);
    }
}
