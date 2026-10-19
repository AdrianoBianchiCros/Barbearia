<?php

namespace Tests;

use App\Models\Empresa;
use App\Models\User;
use App\Support\EmpresaAtual;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /** Autentica um usuário com o perfil informado em uma barbearia (nova ou existente). */
    protected function logarComo(string $perfil = 'dono', ?Empresa $empresa = null): User
    {
        $usuario = User::factory()
            ->for($empresa ?? Empresa::factory())
            ->perfil($perfil)
            ->create();

        Sanctum::actingAs($usuario);

        return $usuario;
    }

    /**
     * Cria registros de uma barbearia específica fora de uma requisição
     * (sem isso, o escopo da requisição anterior forçaria outra empresa).
     */
    protected function naEmpresa(Empresa|int $empresa, callable $criar): mixed
    {
        $contexto = app(EmpresaAtual::class);
        $anterior = $contexto->id();
        $contexto->definir($empresa instanceof Empresa ? $empresa->id : $empresa);

        try {
            return $criar();
        } finally {
            $contexto->definir($anterior);
        }
    }
}
