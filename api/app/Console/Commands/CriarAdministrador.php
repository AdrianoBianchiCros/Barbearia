<?php

namespace App\Console\Commands;

use App\Models\Administrador;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Cria (ou troca a senha de) um administrador da plataforma.
 * Ex.: php artisan admin:criar voce@abianchitech.com "Seu Nome"
 */
#[Signature('admin:criar {email} {nome} {--senha= : Senha (se omitida, é pedida sem aparecer na tela)}')]
#[Description('Cria um administrador da plataforma ou troca a senha de um existente')]
class CriarAdministrador extends Command
{
    public function handle(): int
    {
        $senha = $this->option('senha') ?: $this->secret('Senha (mínimo 8 caracteres, com letras e números)');

        $validacao = Validator::make(
            ['email' => $this->argument('email'), 'senha' => $senha],
            ['email' => ['required', 'email'], 'senha' => ['required', Password::min(8)->letters()->numbers()]],
        );
        if ($validacao->fails()) {
            foreach ($validacao->errors()->all() as $erro) {
                $this->error($erro);
            }

            return self::FAILURE;
        }

        $admin = Administrador::updateOrCreate(
            ['email' => $this->argument('email')],
            ['nome' => $this->argument('nome'), 'password' => $senha, 'ativo' => true],
        );
        $admin->tokens()->delete();

        $this->info($admin->wasRecentlyCreated ? 'Administrador criado.' : 'Senha do administrador atualizada.');

        return self::SUCCESS;
    }
}
