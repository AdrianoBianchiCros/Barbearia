<?php

namespace App\Models;

use Database\Factories\EmpresaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'nome', 'slug', 'slogan', 'descricao', 'documento', 'telefone', 'whatsapp', 'email', 'instagram',
    'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cor_primaria', 'limite_gaveta', 'fuso_horario', 'ativo',
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
    public const SLUGS_RESERVADOS = ['a', 'api', 'up', 'storage', 'img', 'login', 'admin', 'app', 'painel'];

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
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
