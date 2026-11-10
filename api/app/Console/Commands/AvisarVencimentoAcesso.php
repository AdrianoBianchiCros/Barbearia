<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\User;
use App\Notifications\AvisoVencimentoAcesso;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Avisa o dono por e-mail quando faltam 10, 3 e 1 dia(s), no dia do vencimento
 * e no primeiro dia de carência. Roda uma vez por dia pelo agendador.
 */
#[Signature('assinaturas:avisos')]
#[Description('Envia por e-mail os avisos de vencimento do acesso das barbearias')]
class AvisarVencimentoAcesso extends Command
{
    public function handle(): int
    {
        $diasAviso = config('assinatura.aviso_email_dias');
        $enviados = 0;

        $empresas = Empresa::where('ativo', true)->whereNotNull('acesso_ate')->get()
            ->filter(fn (Empresa $e) => in_array($e->diasRestantes(), [...$diasAviso, -1], true));

        foreach ($empresas as $empresa) {
            $donos = User::where('empresa_id', $empresa->id)->where('perfil', 'dono')->where('ativo', true)->get();

            foreach ($donos as $dono) {
                try {
                    $dono->notify(new AvisoVencimentoAcesso($empresa));
                    $enviados++;
                } catch (\Throwable $e) {
                    report($e); // ex.: servidor de e-mail fora; não impede os demais avisos
                }
            }
        }

        $this->info("{$enviados} aviso(s) enviado(s).");

        return self::SUCCESS;
    }
}
