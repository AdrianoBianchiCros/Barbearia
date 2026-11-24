<?php

namespace App\Services;

use App\Models\Funcionario;
use App\Models\User;
use App\Notifications\AcessoBarbeiro as EmailAcessoBarbeiro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Login do barbeiro no painel dele (agenda e comissão), criado pelo dono a partir do e-mail do cadastro.
 */
class AcessoBarbeiro
{
    /** Endereço do painel do barbeiro, que o dono envia junto com a senha provisória. */
    public static function link(): string
    {
        return rtrim(config('app.painel_url'), '/').'/barbeiro';
    }

    /**
     * Cria o login do barbeiro (ou gera uma nova senha provisória, se já existir)
     * e envia por e-mail. Devolve os dados para o dono ver uma única vez.
     */
    public function liberar(Funcionario $barbeiro): array
    {
        if (! $barbeiro->email) {
            throw ValidationException::withMessages(['email' => 'Informe o e-mail do barbeiro para liberar o acesso.']);
        }

        $usuario = $barbeiro->usuario_id ? User::find($barbeiro->usuario_id) : null;
        // Dono ou recepção que também atende: o login é o do painel da barbearia e não pode
        // ser trocado aqui (antes, a senha e o e-mail do dono viravam os do barbeiro e a sessão caía).
        if ($usuario && $usuario->perfil !== 'barbeiro') {
            throw ValidationException::withMessages([
                'email' => "{$barbeiro->nome} já entra no painel da barbearia com o e-mail {$usuario->email} e vê a agenda completa por lá. Não precisa de outro acesso.",
            ]);
        }
        $this->garantirEmailLivre($barbeiro->email, $usuario);

        $senha = User::gerarSenhaProvisoria();

        $usuario = DB::transaction(function () use ($barbeiro, $usuario, $senha) {
            $usuario ??= new User(['empresa_id' => $barbeiro->empresa_id, 'perfil' => 'barbeiro']);
            $usuario->fill(['nome' => $barbeiro->nome, 'email' => $barbeiro->email, 'password' => $senha, 'ativo' => $barbeiro->ativo]);
            $usuario->forceFill(['trocar_senha' => true])->save();
            $usuario->tokens()->delete();

            $barbeiro->forceFill(['usuario_id' => $usuario->id])->save();

            return $usuario;
        });

        return [
            'email' => $usuario->email,
            'senha_provisoria' => $senha,
            'painel_url' => self::link(),
            'email_enviado' => $this->enviar($usuario, $barbeiro, $senha),
        ];
    }

    /** Mantém o login igual ao cadastro: nome, e-mail e situação (inativo não entra). */
    public function sincronizar(Funcionario $barbeiro): void
    {
        $usuario = $barbeiro->usuario_id ? User::find($barbeiro->usuario_id) : null;
        if (! $usuario || $usuario->perfil !== 'barbeiro') {
            return;
        }

        if ($barbeiro->email) {
            $this->garantirEmailLivre($barbeiro->email, $usuario);
            $usuario->email = $barbeiro->email;
        }
        $usuario->fill(['nome' => $barbeiro->nome, 'ativo' => $barbeiro->ativo && ! $barbeiro->trashed()])->save();

        if (! $usuario->ativo) {
            $usuario->tokens()->delete();
        }
    }

    private function garantirEmailLivre(string $email, ?User $usuario): void
    {
        $emUso = User::withTrashed()
            ->where('email', $email)
            ->when($usuario, fn ($q) => $q->whereKeyNot($usuario->id))
            ->exists();

        if ($emUso) {
            throw ValidationException::withMessages(['email' => 'Este e-mail já é usado por outro acesso ao painel. Use outro e-mail para o barbeiro.']);
        }
    }

    /** Falha no e-mail não desfaz o acesso: o dono ainda pode mandar a senha pelo WhatsApp. */
    private function enviar(User $usuario, Funcionario $barbeiro, string $senha): bool
    {
        try {
            $linkNovaSenha = rtrim(config('app.painel_url'), '/').'/redefinir-senha?'.http_build_query([
                'token' => Password::createToken($usuario),
                'email' => $usuario->email,
            ]);
            $usuario->notify(new EmailAcessoBarbeiro($usuario->empresa->nome, $senha, self::link(), $linkNovaSenha));

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
