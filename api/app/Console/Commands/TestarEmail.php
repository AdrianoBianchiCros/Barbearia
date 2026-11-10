<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Diagnóstico do e-mail em produção: mostra a configuração em uso (sem a senha)
 * e tenta enviar uma mensagem de teste, exibindo o erro exato se falhar.
 * Ex.: php artisan email:testar voce@exemplo.com
 */
#[Signature('email:testar {para : E-mail que vai receber o teste}')]
#[Description('Mostra a configuração de e-mail e envia uma mensagem de teste')]
class TestarEmail extends Command
{
    public function handle(): int
    {
        $mailer = config('mail.default');
        $smtp = config('mail.mailers.smtp');
        $senha = (string) ($smtp['password'] ?? '');

        $this->table(['Configuração', 'Valor em uso'], [
            ['MAIL_MAILER', $mailer],
            ['MAIL_HOST', $smtp['host'] ?? ''],
            ['MAIL_PORT', $smtp['port'] ?? ''],
            ['MAIL_SCHEME', $smtp['scheme'] ?? '(vazio)'],
            ['MAIL_USERNAME', $smtp['username'] ?? ''],
            ['MAIL_PASSWORD', $senha === '' ? '(vazia)' : strlen($senha).' caracteres'],
            ['MAIL_FROM_ADDRESS', config('mail.from.address')],
            ['Configuração em cache', app()->configurationIsCached() ? 'sim (depois de mudar o .env, rode config:cache)' : 'não'],
        ]);

        if ($mailer === 'log') {
            $this->warn('MAIL_MAILER=log: os e-mails só vão para o laravel.log, nada é enviado.');
        }
        if ($mailer === 'smtp' && $smtp['username'] && $smtp['username'] !== config('mail.from.address')) {
            $this->warn('O remetente (MAIL_FROM_ADDRESS) é diferente do usuário do SMTP; a HostGator pode recusar.');
        }

        try {
            Mail::raw('Teste de envio do sistema da barbearia. Se chegou, o e-mail está funcionando.', function ($mensagem) {
                $mensagem->to($this->argument('para'))->subject('Teste de e-mail do sistema');
            });
        } catch (\Throwable $e) {
            $this->error('Falhou: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Enviado. Confira a caixa de entrada (e o spam) de '.$this->argument('para').'.');

        return self::SUCCESS;
    }
}
