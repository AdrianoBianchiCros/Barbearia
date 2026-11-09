<?php

/*
 * Dados da empresa dona da plataforma (ABianchi Tech), usados no rodapé e nas páginas
 * de Política de privacidade e Termos de uso. Preencher no .env de produção.
 */
return [
    'nome' => env('PLATAFORMA_NOME', 'ABianchi Tech'),
    'razao_social' => env('PLATAFORMA_RAZAO_SOCIAL', '[preencher razão social]'),
    'cnpj' => env('PLATAFORMA_CNPJ', '54.942.870/0001-09'),
    'endereco' => env('PLATAFORMA_ENDERECO', '[preencher endereço]'),
    'email' => env('PLATAFORMA_EMAIL', '[preencher e-mail de contato]'),
    'telefone' => env('PLATAFORMA_TELEFONE'),

    // Encarregado pelo tratamento de dados pessoais (LGPD, art. 41)
    'encarregado_nome' => env('PLATAFORMA_ENCARREGADO_NOME', '[preencher nome do encarregado]'),
    'encarregado_email' => env('PLATAFORMA_ENCARREGADO_EMAIL', '[preencher e-mail do encarregado]'),

    // Versão vigente de cada documento. Ao mudar o texto, trocar a data: os próximos aceites ficam com a nova versão.
    'versao_termos' => '2026-11-09',
    'versao_privacidade' => '2026-11-09',
];
