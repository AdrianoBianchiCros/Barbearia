<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Foto da galeria exibida na página pública da barbearia. */
#[Fillable(['caminho', 'legenda', 'ordem'])]
class GaleriaFoto extends Model
{
    use PertenceAEmpresa;

    protected $table = 'galeria_fotos';

    protected $appends = ['url'];

    protected $hidden = ['caminho', 'empresa_id'];

    protected function casts(): array
    {
        return ['ordem' => 'integer'];
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn () => Storage::disk('public')->url($this->caminho));
    }
}
