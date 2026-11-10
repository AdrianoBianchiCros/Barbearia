<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assinatura;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Administrador da plataforma: cadastra a barbearia com o dono e o contrato,
 * define o período pago e renova, suspende ou reativa o acesso.
 */
class BarbeariaController extends Controller
{
    /** Períodos, formas de pagamento e regras de prazo para montar os formulários. */
    public function opcoes(): JsonResponse
    {
        return response()->json([
            'periodos' => collect(config('assinatura.periodos'))->map(fn ($p, $chave) => ['valor' => $chave, 'rotulo' => $p['rotulo']])->values(),
            'formas_pagamento' => collect(config('assinatura.formas_pagamento'))->map(fn ($rotulo, $chave) => ['valor' => $chave, 'rotulo' => $rotulo])->values(),
            'carencia_dias' => config('assinatura.carencia_dias'),
            'aviso_dias' => config('assinatura.aviso_dias'),
        ]);
    }

    public function index(): JsonResponse
    {
        $empresas = Empresa::query()
            ->with(['usuarios' => fn ($q) => $q->where('perfil', 'dono')->orderBy('id')])
            ->with(['assinaturas' => fn ($q) => $q->latest('id')])
            ->orderBy('nome')
            ->get();

        return response()->json(['barbearias' => $empresas->map(fn (Empresa $e) => $this->resumo($e))->values()]);
    }

    public function show(Empresa $empresa): JsonResponse
    {
        $empresa->load([
            'usuarios' => fn ($q) => $q->where('perfil', 'dono')->orderBy('id'),
            'assinaturas' => fn ($q) => $q->with('administrador:id,nome')->latest('inicio')->latest('id'),
        ]);

        return response()->json($this->resumo($empresa) + [
            'assinaturas' => $empresa->assinaturas,
            'total_usuarios' => User::where('empresa_id', $empresa->id)->count(),
        ]);
    }

    /**
     * Cria a barbearia, o login do dono (com senha provisória) e a primeira contratação.
     */
    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'documento' => ['nullable', 'string', 'max:20'],
            'dono_nome' => ['required', 'string', 'max:120'],
            'dono_email' => ['required', 'email', 'max:180', 'unique:usuarios,email'],
            ...$this->regrasContratacao(),
        ], [], $this->nomesCampos() + [
            'nome' => 'nome da barbearia',
            'dono_nome' => 'nome do dono',
            'dono_email' => 'e-mail do dono',
            'documento' => 'CNPJ/CPF',
        ]);

        $senha = $this->senhaProvisoria();
        $contrato = $this->guardarContrato($request->file('contrato'));

        try {
            $empresa = DB::transaction(function () use ($dados, $senha, $contrato, $request) {
                $empresa = Empresa::create([
                    'nome' => $dados['nome'],
                    'slug' => Empresa::gerarSlug($dados['nome']),
                    'telefone' => $dados['telefone'] ?? null,
                    'documento' => $dados['documento'] ?? null,
                    'email' => $dados['dono_email'],
                ])->refresh(); // carrega os valores padrão do banco (ex.: fuso horário)

                User::create([
                    'empresa_id' => $empresa->id,
                    'nome' => $dados['dono_nome'],
                    'email' => $dados['dono_email'],
                    'password' => $senha,
                    'perfil' => 'dono',
                ]);

                $inicio = isset($dados['inicio']) ? Carbon::parse($dados['inicio']) : $this->hoje();
                $this->registrarContratacao($empresa, $dados, $inicio, $contrato, $request);

                return $empresa;
            });
        } catch (\Throwable $e) {
            $this->removerContrato($contrato);
            throw $e;
        }

        return response()->json([
            'barbearia' => $this->resumo($empresa->fresh()),
            'acesso' => $this->dadosAcesso($dados['dono_email'], $senha),
        ], 201);
    }

    /**
     * Nova contratação: o período começa no dia seguinte ao vencimento atual
     * (ou hoje, se já venceu ou não tinha prazo), a não ser que o início seja informado.
     */
    public function renovar(Request $request, Empresa $empresa): JsonResponse
    {
        $dados = $request->validate($this->regrasContratacao(), [], $this->nomesCampos());

        $hoje = $this->hoje();
        $inicio = match (true) {
            isset($dados['inicio']) => Carbon::parse($dados['inicio']),
            $empresa->acesso_ate && $empresa->acesso_ate->gte($hoje) => $empresa->acesso_ate->copy()->addDay(),
            default => $hoje,
        };

        $contrato = $this->guardarContrato($request->file('contrato'));

        try {
            DB::transaction(fn () => $this->registrarContratacao($empresa, $dados, $inicio, $contrato, $request));
        } catch (\Throwable $e) {
            $this->removerContrato($contrato);
            throw $e;
        }

        return $this->show($empresa->fresh());
    }

    /** Bloqueia o acesso na hora (ex.: falta de pagamento) e encerra as sessões abertas. */
    public function suspender(Empresa $empresa): JsonResponse
    {
        $empresa->update(['ativo' => false]);
        DB::table('personal_access_tokens')
            ->where('tokenable_type', (new User)->getMorphClass())
            ->whereIn('tokenable_id', User::where('empresa_id', $empresa->id)->pluck('id'))
            ->delete();

        return $this->show($empresa->fresh());
    }

    public function reativar(Empresa $empresa): JsonResponse
    {
        $empresa->update(['ativo' => true]);

        return $this->show($empresa->fresh());
    }

    /** Gera uma nova senha provisória para o dono (ex.: perdeu a senha e o e-mail não chega). */
    public function novaSenha(Empresa $empresa): JsonResponse
    {
        $dono = User::where('empresa_id', $empresa->id)->where('perfil', 'dono')->orderBy('id')->first();
        abort_unless($dono, 404, 'Esta barbearia não tem um dono cadastrado.');

        $senha = $this->senhaProvisoria();
        $dono->forceFill(['password' => $senha])->save();
        $dono->tokens()->delete();

        return response()->json(['acesso' => $this->dadosAcesso($dono->email, $senha)]);
    }

    public function contrato(Assinatura $assinatura): StreamedResponse
    {
        abort_unless($assinatura->contrato_path && Storage::disk('local')->exists($assinatura->contrato_path), 404, 'Contrato não encontrado.');

        return Storage::disk('local')->download($assinatura->contrato_path, $assinatura->contrato_nome ?? 'contrato.pdf');
    }

    private function regrasContratacao(): array
    {
        return [
            'periodo' => ['required', Rule::in(array_keys(config('assinatura.periodos')))],
            'forma_pagamento' => ['required', Rule::in(array_keys(config('assinatura.formas_pagamento')))],
            'valor' => ['required', 'numeric', 'min:0', 'max:999999'],
            'inicio' => ['nullable', 'date'],
            'observacao' => ['nullable', 'string', 'max:500'],
            'contrato' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    private function nomesCampos(): array
    {
        return [
            'periodo' => 'período',
            'forma_pagamento' => 'forma de pagamento',
            'inicio' => 'início',
            'observacao' => 'observação',
        ];
    }

    /** Grava a contratação e estende o acesso da barbearia até o fim do período. */
    private function registrarContratacao(Empresa $empresa, array $dados, Carbon $inicio, ?array $contrato, Request $request): Assinatura
    {
        $fim = Assinatura::calcularFim($dados['periodo'], $inicio);

        $assinatura = Assinatura::create([
            'empresa_id' => $empresa->id,
            'administrador_id' => $request->user()->id,
            'periodo' => $dados['periodo'],
            'inicio' => $inicio->toDateString(),
            'fim' => $fim->toDateString(),
            'forma_pagamento' => $dados['forma_pagamento'],
            'valor' => $dados['valor'],
            'observacao' => $dados['observacao'] ?? null,
            'contrato_path' => $contrato['path'] ?? null,
            'contrato_nome' => $contrato['nome'] ?? null,
        ]);

        $empresa->update(['acesso_ate' => $fim->toDateString()]);

        return $assinatura;
    }

    /** O contrato fica no disco privado (storage/app/private), nunca na pasta pública. */
    private function guardarContrato(?UploadedFile $arquivo): ?array
    {
        if (! $arquivo) {
            return null;
        }

        return [
            'path' => $arquivo->store('contratos', 'local'),
            'nome' => Str::limit($arquivo->getClientOriginalName(), 170, ''),
        ];
    }

    private function removerContrato(?array $contrato): void
    {
        if ($contrato) {
            Storage::disk('local')->delete($contrato['path']);
        }
    }

    /** Letras e números, sem símbolos, para ditar ou mandar por WhatsApp sem confusão. */
    private function senhaProvisoria(): string
    {
        return Str::password(10, symbols: false);
    }

    private function dadosAcesso(string $email, string $senha): array
    {
        return [
            'email' => $email,
            'senha_provisoria' => $senha,
            'painel_url' => config('app.painel_url'),
        ];
    }

    private function hoje(): Carbon
    {
        return Carbon::parse(now('America/Sao_Paulo')->toDateString());
    }

    private function resumo(Empresa $empresa): array
    {
        $dono = $empresa->usuarios->first();
        $ultima = $empresa->assinaturas->first();

        return [
            'id' => $empresa->id,
            'nome' => $empresa->nome,
            'slug' => $empresa->slug,
            'telefone' => $empresa->telefone,
            'documento' => $empresa->documento,
            'ativo' => $empresa->ativo,
            'criada_em' => $empresa->created_at?->toDateString(),
            'acesso' => $empresa->resumoAcesso(),
            'dono' => $dono?->only(['id', 'nome', 'email']),
            'ultima_assinatura' => $ultima ? collect($ultima->toArray())->only(['periodo', 'forma_pagamento', 'valor', 'inicio', 'fim']) : null,
            'pagina_url' => url($empresa->slug),
        ];
    }
}
