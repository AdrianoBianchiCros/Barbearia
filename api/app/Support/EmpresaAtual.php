<?php

namespace App\Support;

/**
 * Guarda a barbearia (empresa) da requisição atual.
 *
 * É preenchida pelo middleware DefinirEmpresaAtual a partir do usuário autenticado
 * e usada pelo escopo global que isola os dados de cada empresa.
 */
class EmpresaAtual
{
    private ?int $id = null;

    public function definir(?int $empresaId): void
    {
        $this->id = $empresaId;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function definida(): bool
    {
        return $this->id !== null;
    }
}
