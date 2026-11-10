<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Administrador da plataforma (ABianchi Tech): cadastra as barbearias, anexa o contrato
 * e controla o prazo de acesso. Não pertence a nenhuma barbearia e não entra no painel delas.
 * Criado pelo comando "php artisan admin:criar".
 */
#[Fillable(['nome', 'email', 'password', 'ativo'])]
#[Hidden(['password'])]
class Administrador extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'administradores';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'ativo' => 'boolean',
        ];
    }
}
