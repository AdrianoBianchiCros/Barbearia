<?php

namespace App\Models;

use Database\Factories\EmpresaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'nome', 'slug', 'slogan', 'descricao', 'documento', 'telefone', 'whatsapp', 'email', 'instagram',
    'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cor_primaria', 'limite_gaveta', 'fuso_horario', 'ativo', 'acesso_ate',
    'wa_ativo', 'wa_lembrete_24h', 'wa_lembrete_2h', 'wa_phone_number_id', 'wa_token', 'cancelamento_antecedencia_horas',
])]
class Empresa extends Model
{
    /** @use HasFactory<EmpresaFactory> */
    use HasFactory;

    protected $appends = ['logo_url', 'capa_url', 'wa_token_configurado'];

    protected $hidden = ['logo_path', 'capa_path', 'wa_token'];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'acesso_ate' => 'date:Y-m-d',
            'limite_gaveta' => 'decimal:2',
            'wa_ativo' => 'boolean',
            'wa_lembrete_24h' => 'boolean',
            'wa_lembrete_2h' => 'boolean',
            'wa_token' => 'encrypted',
            'cancelamento_antecedencia_horas' => 'integer',
        ];
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null);
    }

    /** O token da Meta nunca sai da API; o painel só sabe se ele foi informado. */
    protected function waTokenConfigurado(): Attribute
    {
        return Attribute::get(fn () => filled($this->wa_token));
    }

    protected function capaUrl(): Attribute
    {
        return Attribute::get(fn () => $this->capa_path ? Storage::disk('public')->url($this->capa_path) : null);
    }

    /** Endereços que não podem ser usados como página de barbearia (são rotas do sistema). */
    public const SLUGS_RESERVADOS = ['a', 'api', 'up', 'storage', 'img', 'fonts', 'login', 'admin', 'app', 'painel', 'privacidade', 'termos', 'cookies', 'descadastro', 'meus-dados', 'sistemas'];

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function assinaturas(): HasMany
    {
        return $this->hasMany(Assinatura::class);
    }

    /**
     * Situação do acesso pago:
     * suspensa (desativada pelo administrador), sem_prazo, em_dia, vencendo (aviso no painel),
     * carencia (venceu, mas ainda funciona) ou vencida (bloqueada).
     */
    public function situacaoAcesso(): string
    {
        if (! $this->ativo) {
            return 'suspensa';
        }
        if (! $this->acesso_ate) {
            return 'sem_prazo';
        }

        $dias = $this->diasRestantes();
        if ($dias >= 0) {
            return $dias <= config('assinatura.aviso_dias') ? 'vencendo' : 'em_dia';
        }

        return -$dias <= config('assinatura.carencia_dias') ? 'carencia' : 'vencida';
    }

    /** Barbearia ativa e dentro do prazo pago (ou da carência). */
    public function acessoLiberado(): bool
    {
        return ! in_array($this->situacaoAcesso(), ['suspensa', 'vencida'], true);
    }

    /** Dias até o vencimento no fuso da barbearia (0 = vence hoje, negativo = já venceu). */
    public function diasRestantes(): ?int
    {
        if (! $this->acesso_ate) {
            return null;
        }

        $hoje = Carbon::parse(now($this->fuso_horario)->toDateString());

        return (int) $hoje->diffInDays($this->acesso_ate->copy()->startOfDay(), false);
    }

    /** Último dia em que o sistema ainda funciona (vencimento + carência). */
    public function bloqueiaEm(): ?Carbon
    {
        return $this->acesso_ate?->copy()->addDays(config('assinatura.carencia_dias'));
    }

    /** Resumo do prazo para o painel (faixa de aviso do dono) e para o administrador. */
    public function resumoAcesso(): array
    {
        return [
            'situacao' => $this->situacaoAcesso(),
            'vence_em' => $this->acesso_ate?->toDateString(),
            'dias_restantes' => $this->diasRestantes(),
            'bloqueia_em' => $this->bloqueiaEm()?->toDateString(),
        ];
    }

    /** Mensagem mostrada a quem tenta entrar com o acesso bloqueado. */
    public function mensagemBloqueio(): string
    {
        $contato = config('plataforma.nome');

        return $this->ativo
            ? "O acesso desta barbearia venceu em {$this->acesso_ate->format('d/m/Y')}. Para renovar, fale com a {$contato}."
            : 'Acesso desativado. Fale com o responsável pela barbearia.';
    }

    /** Barbearias que podem usar o sistema hoje (ativas e dentro do prazo + carência). */
    public function scopeComAcessoLiberado(Builder $query): void
    {
        // Fuso padrão das barbearias; o bloqueio exato de cada uma é conferido por acessoLiberado().
        $limite = now('America/Sao_Paulo')->subDays(config('assinatura.carencia_dias'))->toDateString();

        $query->where('ativo', true)
            ->where(fn ($q) => $q->whereNull('acesso_ate')->orWhere('acesso_ate', '>=', $limite));
    }

    /**
     * Gera um slug único para o link público (ex.: barbearia-do-ze, barbearia-do-ze-2).
     */
    public static function gerarSlug(string $nome): string
    {
        $base = Str::slug($nome) ?: 'barbearia';
        if (in_array($base, self::SLUGS_RESERVADOS, true)) {
            $base = "barbearia-{$base}";
        }
        $slug = $base;
        $n = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
