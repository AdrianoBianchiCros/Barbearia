<?php

namespace Tests\Feature;

use App\Models\Aceite;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\Servico;
use App\Support\EmpresaAtual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LgpdTest extends TestCase
{
    use RefreshDatabase;

    private const DIA = '2026-10-20'; // terça-feira

    private function registrar(array $dados = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/auth/registrar', $dados + [
            'empresa_nome' => 'Barbearia do Zé',
            'nome' => 'José Silva',
            'email' => 'ze@exemplo.test',
            'password' => 'segredo123',
            'password_confirmation' => 'segredo123',
        ]);
    }

    public function test_cadastro_exige_aceite_dos_termos(): void
    {
        $this->registrar()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['aceite_termos']);

        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_cadastro_registra_aceite_de_termos_e_privacidade(): void
    {
        $id = $this->registrar(['aceite_termos' => true])->assertCreated()->json('usuario.id');

        foreach (['termos', 'privacidade'] as $documento) {
            $this->assertDatabaseHas('aceites', [
                'usuario_id' => $id,
                'documento' => $documento,
                'versao' => Aceite::versaoVigente($documento),
                'origem' => 'cadastro_painel',
                'ip' => '127.0.0.1',
            ]);
        }
    }

    private function prepararPagina(): void
    {
        Carbon::setTestNow(Carbon::parse(self::DIA.' 08:00', 'America/Sao_Paulo'));
        $empresa = Empresa::factory()->create(['slug' => 'barbearia-do-ze']);
        $this->naEmpresa($empresa, function () {
            $corte = Servico::create(['nome' => 'Corte', 'preco' => 50, 'duracao_minutos' => 30, 'ativo' => true]);
            $barbeiro = Funcionario::create(['nome' => 'Carlos', 'ativo' => true]);
            $barbeiro->servicos()->attach([$corte->id => []]);
            $barbeiro->horariosTrabalho()->create(['dia_semana' => 2, 'inicio' => '09:00', 'fim' => '12:00']);
        });
    }

    private function agendar(array $dados = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/publico/barbearia-do-ze/agendamentos', $dados + [
            'nome' => 'João',
            'telefone' => '(11) 98888-7777',
            'servico_ids' => [Servico::withoutGlobalScopes()->value('id')],
            'data' => self::DIA,
            'hora' => '09:00',
        ]);
    }

    public function test_agendamento_online_exige_privacidade_e_declaracao_de_maioridade(): void
    {
        $this->prepararPagina();

        $this->agendar()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['aceite_privacidade', 'maioridade']);
    }

    public function test_agendamento_online_registra_aceites_uma_vez_por_versao(): void
    {
        $this->prepararPagina();

        $this->agendar(['aceite_privacidade' => true, 'maioridade' => true])->assertCreated();
        $this->agendar(['aceite_privacidade' => true, 'maioridade' => true, 'hora' => '10:00', 'data' => '2026-10-27'])->assertCreated();

        $this->assertSame(1, Aceite::where('documento', 'privacidade')->whereNotNull('cliente_id')->count());
        $this->assertSame(1, Aceite::where('documento', 'maioridade')->whereNotNull('cliente_id')->count());
    }

    public function test_paginas_de_privacidade_e_termos_mostram_dados_da_plataforma(): void
    {
        $this->get('/privacidade')->assertOk()->assertSee('Política de privacidade')->assertSee(config('plataforma.cnpj'));
        $this->get('/termos')->assertOk()->assertSee('Termos de uso')->assertSee('7 dias');
    }

    public function test_pagina_da_barbearia_nao_carrega_mapa_nem_fontes_de_terceiros(): void
    {
        $this->prepararPagina();
        Empresa::query()->update(['logradouro' => 'Rua A', 'numero' => '10', 'cidade' => 'São Paulo', 'uf' => 'SP']);

        $html = $this->get('/barbearia-do-ze')->assertOk()->getContent();

        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('aviso-cookies', $html);
        $this->assertStringContainsString(route('privacidade'), $html);
    }

    public function test_enderecos_legais_sao_reservados(): void
    {
        $this->assertContains('privacidade', Empresa::SLUGS_RESERVADOS);
        $this->assertContains('termos', Empresa::SLUGS_RESERVADOS);
        $this->assertNotSame('privacidade', Empresa::gerarSlug('Privacidade'));
    }
}
