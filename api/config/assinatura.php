<?php

/*
 * Prazo de acesso das barbearias (venda do SaaS). A data paga fica em empresas.acesso_ate
 * e cada contratação ou renovação gera uma linha em assinaturas.
 */
return [
    // Dias depois do vencimento em que o sistema ainda funciona, com aviso em vermelho.
    'carencia_dias' => (int) env('ASSINATURA_CARENCIA_DIAS', 5),

    // A partir de quantos dias antes do vencimento o dono vê o aviso no painel.
    'aviso_dias' => (int) env('ASSINATURA_AVISO_DIAS', 10),

    // Dias antes do vencimento em que o dono recebe o aviso por e-mail (0 = no dia).
    'aviso_email_dias' => [10, 3, 1, 0],

    // Períodos que podem ser contratados: rótulo e duração.
    'periodos' => [
        'teste' => ['rotulo' => 'Teste de 7 dias', 'dias' => 7],
        '1m' => ['rotulo' => '1 mês', 'meses' => 1],
        '3m' => ['rotulo' => '3 meses', 'meses' => 3],
        '6m' => ['rotulo' => '6 meses', 'meses' => 6],
        '12m' => ['rotulo' => '12 meses', 'meses' => 12],
    ],

    /*
     * Pix para o dono pagar a mensalidade pelo painel (QR Code gerado pelo sistema, sem API do banco).
     * Chave como está cadastrada no banco: celular com +55 (ex.: +5511999990000), e-mail, CPF/CNPJ só
     * com números ou chave aleatória. Sem chave, a tela de faturas mostra só o histórico.
     */
    'pix' => [
        'chave' => env('PIX_CHAVE'),
        'recebedor' => env('PIX_RECEBEDOR', env('PLATAFORMA_NOME', 'ABianchi Tech')),
        'cidade' => env('PIX_CIDADE', 'SAO PAULO'),
    ],

    'formas_pagamento' => [
        'pix' => 'Pix',
        'boleto' => 'Boleto',
        'cartao' => 'Cartão',
        'transferencia' => 'Transferência',
        'dinheiro' => 'Dinheiro',
        'cortesia' => 'Cortesia',
    ],
];
