<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Na HostGator o cron do cPanel roda "php artisan schedule:run" a cada minuto.
Schedule::command('whatsapp:lembretes')->everyFiveMinutes()->withoutOverlapping();

// Hospedagem compartilhada não mantém processos rodando: a fila é processada em lotes.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();
