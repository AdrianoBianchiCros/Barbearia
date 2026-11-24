<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assinatura;
use App\Models\Empresa;
use App\Models\Fatura;
use App\Models\MensagemWhatsapp;
use App\Models\User;
use App\Notifications\SenhaProvisoria;
use App\Services\ConsultaCnpj;
use App\Services\ContratoPrestacao;
use App\Services\Faturas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
    /**
     * Dados da Receita Federal a partir do CNPJ (API pública do OpenCNPJ), para preencher o cadastro.
     * GET /api/admin/cnpj/{cnpj}
     */
    public function consultarCnpj(string $cnpj, ConsultaCnpj $consulta): JsonResponse
    {
        try {
            $dados = $consulta->consultar($cnpj);
        } catch (\RuntimeException $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 503);
        }

        $dados['ja_cadastrada'] = Empresa::withTrashed()->where('documento', $dados['documento'])->value('nome');

        return response()->json($dados);
    }

    /** Períodos, formas de pagamento e regras de prazo para montar os formulários. */
    public function opcoes(): JsonResponse
    {
        return response()->json([
            'periodos' => collect(config('assinatura.periodos'))->reject(fn ($p) => $p['historico'] ?? false)
                ->map(fn ($p, $chave) => ['valor' => $chave, 'rotulo' => $p['rotulo']])->values(),
            'teste_gratis_dias' => config('assinatura.teste_gratis_dias'),
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
    public function store(Request $request, ContratoPrestacao $contratos): JsonResponse
    {
        $this->normalizarDadosEmpresa($request);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'documento' => ['nullable', 'string', 'max:20'],
            ...$this->regrasDadosEmpresa(),
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
        // Sem arquivo anexado, o sistema gera o contrato de prestação de serviço com os dados preenchidos.
        $contrato = $this->guardarContrato($request->file('contrato'))
            ?? $this->gerarContrato($contratos, $this->empresaDosDados($dados), $dados);

        try {
            [$empresa, $dono] = DB::transaction(function () use ($dados, $senha, $contrato, $request) {
                $empresa = Empresa::create([
                    'nome' => $dados['nome'],
                    'slug' => Empresa::gerarSlug($dados['nome']),
                    'telefone' => $dados['telefone'] ?? null,
                    'documento' => $dados['documento'] ?? null,
                    ...collect($dados)->only(array_keys($this->regrasDadosEmpresa()))->all(),
                    'email' => ($dados['email'] ?? null) ?: $dados['dono_email'],
                ])->refresh(); // carrega os valores padrão do banco (ex.: fuso horário)

                $dono = new User([
                    'empresa_id' => $empresa->id,
                    'nome' => $dados['dono_nome'],
                    'email' => $dados['dono_email'],
                    'password' => $senha,
                    'perfil' => 'dono',
                ]);
                $dono->forceFill(['trocar_senha' => true])->save();

                $inicio = $this->inicioDosDados($dados);
                $this->registrarContratacao($empresa, $dados, $inicio, $contrato, $request, gratis: true);

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
            'periodo' => ['required', Rule::in(collect(config('assinatura.periodos'))->reject(fn ($p) => $p['historico'] ?? false)->keys()->all())],
            'forma_pagamento' => ['required', Rule::in(array_keys(config('assinatura.formas_pagamento')))],
            'valor' => ['required', 'numeric', 'min:0', 'max:999999'],
            'taxa_instalacao' => ['nullable', 'numeric', 'min:0', 'max:99999'],
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
            'taxa_instalacao' => 'taxa de instalação',
        ];
    }

    /**
     * Grava a contratação e estende o acesso. No cadastro ($gratis), o contrato começa com os dias grátis:
     * nada é cobrado agora; a mensalidade e a taxa ficam guardadas para a 1ª fatura, que vence no fim do teste.
     */
    private function registrarContratacao(Empresa $empresa, array $dados, Carbon $inicio, ?array $contrato, Request $request, bool $gratis = false): Assinatura
    {
        $fim = $gratis
            ? $inicio->copy()->addDays(max(1, (int) config('assinatura.teste_gratis_dias')) - 1)
            : Assinatura::calcularFim($dados['periodo'], $inicio);

        $assinatura = Assinatura::create([
            'empresa_id' => $empresa->id,
            'administrador_id' => $request->user()->id,
            'periodo' => $dados['periodo'],
            'inicio' => $inicio->toDateString(),
            'fim' => $fim->toDateString(),
            'forma_pagamento' => $dados['forma_pagamento'],
            'valor' => $dados['valor'],
            'taxa_instalacao' => $dados['taxa_instalacao'] ?? null,
            'gratis' => $gratis,
            'observacao' => $dados['observacao'] ?? null,
            'contrato_path' => $contrato['path'] ?? null,
            'contrato_nome' => $contrato['nome'] ?? null,
        ]);

        $empresa->update(['acesso_ate' => $fim->toDateString()]);

        return $assinatura;
    }

    /**
     * Contrato gerado com os dados do formulário, sem gravar nada (botão "Ver contrato" do cadastro).
     * POST /api/admin/contrato/previa
     */
    public function previaContrato(Request $request, ContratoPrestacao $contratos): Response
    {
        $this->normalizarDadosEmpresa($request);
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'documento' => ['nullable', 'string', 'max:20'],
            ...$this->regrasDadosEmpresa(),
            'dono_nome' => ['required', 'string', 'max:120'],
            'dono_email' => ['required', 'email', 'max:180'],
            ...collect($this->regrasContratacao())->except('contrato')->all(),
        ], [], $this->nomesCampos() + ['nome' => 'nome da barbearia', 'dono_nome' => 'nome do dono', 'dono_email' => 'e-mail do dono']);

        $empresa = $this->empresaDosDados($dados);
        $pdf = $contratos->pdf($empresa, $dados['dono_nome'], $dados['dono_email'], $dados, $this->inicioDosDados($dados));

        return $this->respostaPdf($pdf, ContratoPrestacao::nomeArquivo($empresa));
    }

    /**
     * Contrato de uma barbearia já cadastrada, com os dados atuais e as condições da última contratação
     * (início do serviço = a primeira contratação). GET /api/admin/barbearias/{empresa}/contrato-gerado
     */
    public function contratoGerado(Empresa $empresa, ContratoPrestacao $contratos): Response
    {
        $ultima = $empresa->assinaturas()->latest('inicio')->latest('id')->first();
        abort_unless($ultima, 422, 'Esta barbearia ainda não tem contratação registrada.');
        $primeira = $empresa->assinaturas()->oldest('inicio')->oldest('id')->first();
        $dono = $empresa->usuarios()->where('perfil', 'dono')->orderBy('id')->first();

        $pdf = $contratos->pdf(
            $empresa,
            $dono?->nome ?? $empresa->nome,
            $dono?->email ?? (string) $empresa->email,
            $ultima->only(['periodo', 'forma_pagamento', 'valor']) + ['taxa_instalacao' => $primeira->taxa_instalacao],
            Carbon::parse($primeira->inicio),
        );

        return $this->respostaPdf($pdf, ContratoPrestacao::nomeArquivo($empresa));
    }

    /** CNPJ/CPF e CEP só com números; UF em maiúsculas. */
    private function normalizarDadosEmpresa(Request $request): void
    {
        foreach (['documento', 'cep'] as $campo) {
            if ($request->filled($campo)) {
                $request->merge([$campo => preg_replace('/\D/', '', $request->input($campo))]);
            }
        }
        if ($request->filled('uf')) {
            $request->merge(['uf' => strtoupper($request->input('uf'))]);
        }
    }

    /** Barbearia montada com os dados do formulário (sem gravar), para o contrato. */
    private function empresaDosDados(array $dados): Empresa
    {
        return new Empresa([
            'nome' => $dados['nome'],
            'documento' => $dados['documento'] ?? null,
            'telefone' => $dados['telefone'] ?? null,
            ...collect($dados)->only(array_keys($this->regrasDadosEmpresa()))->all(),
            'email' => ($dados['email'] ?? null) ?: $dados['dono_email'],
        ]);
    }

    private function inicioDosDados(array $dados): Carbon
    {
        return isset($dados['inicio']) ? Carbon::parse($dados['inicio']) : $this->hoje();
    }

    /** Gera o PDF do contrato e guarda no disco privado, como se fosse um anexo. */
    private function gerarContrato(ContratoPrestacao $contratos, Empresa $empresa, array $dados): array
    {
        $pdf = $contratos->pdf($empresa, $dados['dono_nome'], $dados['dono_email'], $dados, $this->inicioDosDados($dados));
        $path = 'contratos/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, $pdf);

        return ['path' => $path, 'nome' => ContratoPrestacao::nomeArquivo($empresa)];
    }

    private function respostaPdf(string $pdf, string $nome): Response
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes($nome).'"',
        ]);
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

    /** Dados da empresa (preenchidos pela consulta do CNPJ, mas editáveis). */
    private function regrasDadosEmpresa(): array
    {
        return [
            'email' => ['nullable', 'email', 'max:180'],
            'razao_social' => ['nullable', 'string', 'max:160'],
            'situacao_cadastral' => ['nullable', 'string', 'max:30'],
            'data_abertura' => ['nullable', 'date_format:Y-m-d'],
            'natureza_juridica' => ['nullable', 'string', 'max:120'],
            'cnae_principal' => ['nullable', 'string', 'max:200'],
            'porte' => ['nullable', 'string', 'max:40'],
            'optante_simples' => ['nullable', 'boolean'],
            'optante_mei' => ['nullable', 'boolean'],
            'cep' => ['nullable', 'string', 'max:9'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:80'],
            'bairro' => ['nullable', 'string', 'max:80'],
            'cidade' => ['nullable', 'string', 'max:80'],
            'uf' => ['nullable', 'string', 'size:2'],
        ];
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
            'empresa' => $empresa->only([
                'razao_social', 'email', 'situacao_cadastral', 'data_abertura', 'natureza_juridica', 'cnae_principal',
                'porte', 'optante_simples', 'optante_mei', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf',
            ]),
            'ativo' => $empresa->ativo,
            'criada_em' => $empresa->created_at?->toDateString(),
            'acesso' => $empresa->resumoAcesso(),
            'dono' => $dono?->only(['id', 'nome', 'email']),
            'ultima_assinatura' => $ultima ? collect($ultima->toArray())->only(['periodo', 'forma_pagamento', 'valor', 'inicio', 'fim']) : null,
            'pagina_url' => url($empresa->slug),
        ];
    }
}
