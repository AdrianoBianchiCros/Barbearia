<?php

namespace App\Http\Controllers;

use App\Support\EmpresaAtual;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

abstract class Controller
{
    /** Regra "exists" restrita à barbearia atual (ignora registros excluídos). */
    protected function existeNaEmpresa(string $tabela, string $coluna = 'id', bool $softDelete = true): Exists
    {
        $regra = Rule::exists($tabela, $coluna)->where('empresa_id', app(EmpresaAtual::class)->id());

        return $softDelete ? $regra->whereNull('deleted_at') : $regra;
    }

    /** Regra "unique" restrita à barbearia atual. */
    protected function unicoNaEmpresa(string $tabela, string $coluna): Unique
    {
        return Rule::unique($tabela, $coluna)->where('empresa_id', app(EmpresaAtual::class)->id());
    }
}
