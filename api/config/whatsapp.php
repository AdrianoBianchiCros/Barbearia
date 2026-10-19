<?php

return [

    /*
    | "log": modo de teste — as mensagens são registradas no sistema e no log,
    |        sem enviar nada (use enquanto a conta da Meta não está aprovada).
    | "cloud": envio real pela WhatsApp Cloud API (Meta).
    */
    'driver' => env('WHATSAPP_DRIVER', 'log'),

    'graph_url' => env('WHATSAPP_GRAPH_URL', 'https://graph.facebook.com/v21.0'),

    // Do aplicativo na Meta (Configurações do app > Básico): valida a assinatura dos webhooks.
    'app_secret' => env('WHATSAPP_APP_SECRET'),

    // Texto livre que você define ao cadastrar o webhook na Meta.
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),

    'idioma' => env('WHATSAPP_IDIOMA', 'pt_BR'),

    // Nomes dos modelos (templates) aprovados na Meta.
    'templates' => [
        'confirmacao' => env('WHATSAPP_TEMPLATE_CONFIRMACAO', 'confirmacao_agendamento'),
        'lembrete' => env('WHATSAPP_TEMPLATE_LEMBRETE', 'lembrete_agendamento'),
    ],

];
