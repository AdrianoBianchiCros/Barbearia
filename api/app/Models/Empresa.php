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
    'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cor_primaria', 'fuso_horario', 'ativo',
])]
class Empresa extends Model
{
    /** @use HasFactory<EmpresaFactory> */
    use HasFactory;

    protected $appends = ['logo_url', 'capa_url'];

    protected $hidden = ['logo_path', 'capa_path'];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null);
    }

    protected function capaUrl(): Attribute
    {
        return Attribute::get(fn () => $this->capa_path ? Storage::disk('public')->url($this->capa_path) : null);
    }

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
        $slug = $base;
        $n = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
