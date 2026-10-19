<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

#[Fillable(['usuario_id', 'nome', 'telefone', 'email', 'especialidades', 'tipo_vinculo', 'comissao_percentual', 'atende_clientes', 'ativo'])]
class Funcionario extends Model
{
    use PertenceAEmpresa, SoftDeletes;

    protected $table = 'funcionarios';

    protected function casts(): array
    {
        return [
            'comissao_percentual' => 'decimal:2',
            'atende_clientes' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    protected function fotoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->foto_path ? Storage::disk('public')->url($this->foto_path) : null);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function servicos(): BelongsToMany
    {
        return $this->belongsToMany(Servico::class, 'funcionario_servicos')
            ->using(FuncionarioServico::class)
            ->withPivot(['empresa_id', 'preco', 'duracao_minutos'])
            ->withTimestamps();
    }

    /**
     * Serviços escolhidos com o preço/duração deste barbeiro (ou o padrão do serviço),
     * na ordem em que foram escolhidos. Requer a relação servicos carregada.
     */
    public function itensDosServicos(array $servicoIds): Collection
    {
        return $this->servicos
            ->whereIn('id', $servicoIds)
            ->sortBy(fn ($s) => array_search($s->id, $servicoIds))
            ->map(fn ($s) => [
                'servico_id' => $s->id,
                'nome' => $s->nome,
                'preco' => (float) ($s->pivot->preco ?? $s->preco),
                'duracao_minutos' => (int) ($s->pivot->duracao_minutos ?? $s->duracao_minutos),
            ])
            ->values();
    }

    public function horariosTrabalho(): HasMany
    {
        return $this->hasMany(HorarioTrabalho::class);
    }

    public function bloqueios(): HasMany
    {
        return $this->hasMany(BloqueioAgenda::class);
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class);
    }
}
