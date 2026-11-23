<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assinatura;
use App\Models\Empresa;
use App\Models\Fatura;
use App\Models\MensagemWhatsapp;
use App\Models\User;
use App\Notifications\SenhaProvisoria;
use App\Services\Faturas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
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

        // Barbearias cujo dono avisou que pagou e aguardam a confirmação.
        $informadas = Fatura::where('status', 'informada')->pluck('empresa_id')->flip();

        return response()->json(['barbearias' => $empresas->map(fn (Empresa $e) => $this->resumo($e) + [
            'pagamento_informado' => $informadas->has($e->id),
        ])->values()]);
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
            'proxima_fatura' => app(Faturas::class)->proxima($empresa),
        ]);
    }

    /** Pix conferido no banco: a fatura vira uma contratação e o acesso é estendido. */
    public function confirmarFatura(Request $request, Fatura $fatura, Faturas $faturas): JsonResponse
    {
        abort_unless(in_array($fatura->status, Fatura::EM_ABERTO, true), 422, 'Esta fatura não está mais em aberto.');

        $faturas->confirmar($fatura, $request->user());

        return $this->show(Empresa::findOrFail($fatura->empresa_id));
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

        $senha = User::gerarSenhaProvisoria();
        $contrato = $this->guardarContrato($request->file('contrato'));

        try {
            [$empresa, $dono] = DB::transaction(function () use ($dados, $senha, $contrato, $request) {
                $empresa = Empresa::create([
                    'nome' => $dados['nome'],
                    'slug' => Empresa::gerarSlug($dados['nome']),
                    'telefone' => $dados['telefone'] ?? null,
                    'documento' => $dados['documento'] ?? null,
                    'email' => $dados['dono_email'],
                ])->refresh(); // carrega os valores padrão do banco (ex.: fuso horário)

                $dono = new User([
                    'empresa_id' => $empresa->id,
                    'nome' => $dados['dono_nome'],
                    'email' => $dados['dono_email'],
                    'password' => $senha,
                    'perfil' => 'dono',
                ]);
                $dono->forceFill(['trocar_senha' => true])->save();

                $inicio = isset($dados['inicio']) ? Carbon::parse($dados['inicio']) : $this->hoje();
                $this->registrarContratacao($empresa, $dados, $inicio, $contrato, $request);

                return [$empresa, $dono];
            });
        } catch (\Throwable $e) {
            $this->removerContrato($contrato);
            throw $e;
        }

        return response()->json([
            'barbearia' => $this->resumo($empresa->fresh()),
            'acesso' => $this->dadosAcesso($dono, $senha),
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

    /**
     * Exclui a barbearia: tira do ar a página, o agendamento online, os painéis e os links dos clientes,
     * encerra as sessões e cancela as mensagens pendentes. Os dados ficam guardados (sem acesso)
     * pelos prazos da Política de privacidade, e o endereço da página fica livre para outra barbearia.
     */
    public function excluir(Empresa $empresa): JsonResponse
    {
        DB::transaction(function () use ($empresa) {
            $usuarios = User::where('empresa_id', $empresa->id)->pluck('id');
            User::whereKey($usuarios)->update(['ativo' => false]);
            DB::table('personal_access_tokens')
                ->where('tokenable_type', (new User)->getMorphClass())
                ->whereIn('tokenable_id', $usuarios)
                ->delete();

            MensagemWhatsapp::withoutGlobalScopes()
                ->where('empresa_id', $empresa->id)
                ->where('status', 'pendente')
                ->update(['status' => 'ignorado', 'erro' => 'Barbearia excluída.']);

            $empresa->update(['ativo' => false, 'slug' => "{$empresa->slug}--excluida-{$empresa->id}"]);
            $empresa->delete();
        });

        return response()->json(['message' => "Barbearia {$empresa->nome} excluída."]);
    }

    public function reativar(Empresa $empresa): JsonResponse
    {
        $empresa->update(['ativo' => true]);

        return $this->show($empresa->fresh());
    }

    /**
     * Gera uma nova senha provisória para o dono: ela vai por e-mail com o link para criar a própria,
     * e quem entrar com ela é obrigado a trocá-la antes de usar o painel.
     */
    public function novaSenha(Empresa $empresa): JsonResponse
    {
        $dono = User::where('empresa_id', $empresa->id)->where('perfil', 'dono')->orderBy('id')->first();
        abort_unless($dono, 404, 'Esta barbearia não tem um dono cadastrado.');

        $senha = User::gerarSenhaProvisoria();
        $dono->forceFill(['password' => $senha, 'trocar_senha' => true])->save();
        $dono->tokens()->delete();

        return response()->json(['acesso' => $this->dadosAcesso($dono, $senha)]);
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

    /** Envia a senha provisória ao dono por e-mail e devolve os dados para o administrador ver (uma única vez). */
    private function dadosAcesso(User $dono, string $senha): array
    {
        return [
            'email' => $dono->email,
            'senha_provisoria' => $senha,
            'painel_url' => config('app.painel_url'),
            'email_enviado' => $this->enviarSenhaPorEmail($dono, $senha),
        ];
    }

    /** Falha no e-mail não desfaz a senha: o administrador ainda pode mandá-la pelo WhatsApp. */
    private function enviarSenhaPorEmail(User $dono, string $senha): bool
    {
        try {
            $link = rtrim(config('app.painel_url'), '/').'/redefinir-senha?'.http_build_query([
                'token' => Password::createToken($dono),
                'email' => $dono->email,
            ]);
            $dono->notify(new SenhaProvisoria($senha, $link));

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
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
