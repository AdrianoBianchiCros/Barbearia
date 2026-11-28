<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Support\Cores;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cor principal escolhida em Configurações: vira a cor dos botões e destaques da página pública e do link
 * do cliente, com variações legíveis no fundo escuro e nas faixas claras. O logo vai para o topo do menu.
 */
class CorPrincipalTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagina_usa_a_cor_escolhida_nos_botoes_e_destaques(): void
    {
        Empresa::factory()->create(['slug' => 'zen', 'cor_primaria' => '#c0392b']);

        $cores = Cores::paraPagina('#c0392b', '#000000');

        $this->get('/zen')->assertOk()
            ->assertSee('--acento: #c0392b;', false)
            ->assertSee("--acento-texto: {$cores['corTexto']};", false)
            ->assertSee("--ouro: {$cores['acentoClaro']};", false)
            ->assertSee("--acento-escuro: {$cores['acentoEscuro']};", false)
            ->assertSee('.botao-ouro { background: var(--acento); color: var(--acento-texto); }', false);
    }

    public function test_variacoes_ficam_legiveis_com_qualquer_cor(): void
    {
        // Azul-marinho: texto branco em cima; clareado para aparecer no fundo escuro.
        $marinho = Cores::paraPagina('#1f3a5f', '#000000');
        $this->assertSame('#ffffff', $marinho['corTexto']);
        $this->assertNotSame('#1f3a5f', $marinho['acentoClaro']);

        // Amarelo: texto preto em cima; escurecido para aparecer nas faixas claras.
        $amarelo = Cores::paraPagina('#ffd400', '#000000');
        $this->assertSame('#111111', $amarelo['corTexto']);
        $this->assertNotSame('#ffd400', $amarelo['acentoEscuro']);

        // Cor inválida no banco: usa a padrão do sistema.
        $this->assertSame('#5f7a63', Cores::paraPagina('vermelho', '#5f7a63')['acento']);
    }

    public function test_painel_recebe_o_logo_para_o_topo_do_menu(): void
    {
        $this->logarComo('dono');

        $this->getJson('/api/auth/eu')->assertOk()->assertJsonPath('empresa.logo_url', null);
    }
}
