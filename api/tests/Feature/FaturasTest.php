<?php

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\Assinatura;
use App\Models\Empresa;
use App\Models\Fatura;
use App\Models\User;
use App\Notifications\PagamentoInformado;
use App\Services\PixCopiaECola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Faturas da mensalidade no painel do dono: histórico, próxima fatura, Pix e confirmação pelo administrador.
 * Hoje (nos testes) = terça, 10/11/2026.
 */
class FaturasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 10:00', 'America/Sao_Paulo'));
        Notification::fake();
        config(['assinatura.pix' => ['chave' => 'pix@abianchitech.com', 'recebedor' => 'ABianchi Tech', 'cidade' => 'São Paulo']]);
    }

    private function barbearia(string $periodo = '1m', float $valor = 99.90, string $acessoAte = '2026-11-20'): Empresa
    {
        $empresa = Empresa::factory()->create(['acesso_ate' => $acessoAte]);
        Assinatura::create([
            'empresa_id' => $empresa->id, 'periodo' => $periodo, 'inicio' => '2026-10-21', 'fim' => $acessoAte,
            'forma_pagamento' => 'pix', 'valor' => $valor,
        ]);

        return $empresa;
    }

    private function logarDono(Empresa $empresa, string $perfil = 'dono'): User
    {
        $usuario = User::factory()->for($empresa)->create(['perfil' => $perfil]);
        Sanctum::actingAs($usuario);

        return $usuario;
    }

    public function test_dono_ve_as_pagas_e_a_proxima_repetindo_a_ultima_contratacao(): void
    {
        $this->logarDono($this->barbearia());

        $this->getJson('/api/assinatura')
            ->assertOk()
            ->assertJsonPath('pix_disponivel', true)
            ->assertJsonPath('pagas.0.periodo', 'Mensalidade')
            ->assertJsonPath('pagas.0.valor', '99.90')
            ->assertJsonPath('proxima.periodo', '1m')
            ->assertJsonPath('proxima.valor', '99.90')
            ->assertJsonPath('proxima.vencimento', '2026-11-20')
            ->assertJsonPath('proxima.inicio', '2026-11-21')
            ->assertJsonPath('proxima.fim', '2026-12-20')
            ->assertJsonPath('proxima.status', 'aberta');

        // A mesma fatura volta nas próximas consultas (o Pix não muda).
        $this->getJson('/api/assinatura')->assertOk();
        $this->assertSame(1, Fatura::count());
    }

    public function test_contrato_de_12_meses_e_cobrado_mes_a_mes(): void
    {
        $this->logarDono($this->barbearia('12m', 100));

        $this->getJson('/api/assinatura')
            ->assertOk()
            ->assertJsonPath('pagas.0.periodo', 'Mensalidade · contrato de 12 meses')
            ->assertJsonPath('proxima.valor', '100.00') // a mensalidade, não o total do contrato
            ->assertJsonPath('proxima.inicio', '2026-11-21')
            ->assertJsonPath('proxima.fim', '2026-12-20') // libera 1 mês
            ->assertJsonPath('proxima.periodo_rotulo', 'Mensalidade · contrato de 12 meses');
    }

    public function test_sem_proxima_fatura_no_teste_ou_sem_prazo(): void
    {
        $this->logarDono($this->barbearia('teste', 0));
        $this->getJson('/api/assinatura')->assertOk()->assertJsonPath('proxima', null);

        $this->logarDono(Empresa::factory()->create(['acesso_ate' => null]));
        $this->getJson('/api/assinatura')->assertOk()->assertJsonPath('proxima', null)->assertJsonPath('pagas', []);
    }

    public function test_pix_copia_e_cola_da_fatura(): void
    {
        $this->logarDono($this->barbearia());
        $fatura = $this->getJson('/api/assinatura')->json('proxima');

        $codigo = $this->postJson("/api/assinatura/faturas/{$fatura['id']}/pix")
            ->assertOk()
            ->json('copia_e_cola');

        $this->assertStringStartsWith('000201', $codigo);
        $this->assertStringContainsString('0014br.gov.bcb.pix0120pix@abianchitech.com', $codigo);
        $this->assertStringContainsString('540599.90', $codigo);
        $this->assertStringContainsString('5913ABIANCHI TECH6009SAO PAULO', $codigo);
        $this->assertStringContainsString('0525'.$fatura['txid'], $codigo);
        $this->assertMatchesRegularExpression('/6304[0-9A-F]{4}$/', $codigo);
    }

    public function test_codigo_pix_com_crc16_correto(): void
    {
        // Chave e dados do exemplo do Manual do BR Code; CRC conferido com o binascii.crc_hqx do Python.
        $codigo = PixCopiaECola::gerar('123e4567-e12b-12d1-a456-426655440000', 'Fulano de Tal', 'Brasília', 1, '***');
        $this->assertSame(
            '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-426655440000520400005303986540'.'41.005802BR5913FULANO DE TAL6008BRASILIA62070503***6304',
            substr($codigo, 0, -4),
        );
        $this->assertSame('5519', substr($codigo, -4));
    }

    public function test_sem_chave_pix_nao_gera_o_codigo(): void
    {
        config(['assinatura.pix.chave' => null]);
        $this->logarDono($this->barbearia());
        $fatura = $this->getJson('/api/assinatura')->assertJsonPath('pix_disponivel', false)->json('proxima');

        $this->postJson("/api/assinatura/faturas/{$fatura['id']}/pix")->assertStatus(422);
    }

    public function test_dono_informa_o_pagamento_e_o_administrador_e_avisado(): void
    {
        $admin = Administrador::create(['nome' => 'Adriano', 'email' => 'admin@exemplo.test', 'password' => 'segredo123', 'ativo' => true]);
        $this->logarDono($this->barbearia());
        $fatura = $this->getJson('/api/assinatura')->json('proxima');

        $this->postJson("/api/assinatura/faturas/{$fatura['id']}/informar-pagamento")
            ->assertOk()
            ->assertJsonPath('fatura.status', 'informada');

        Notification::assertSentTo($admin, PagamentoInformado::class);
        $this->getJson('/api/assinatura')->assertJsonPath('proxima.status', 'informada');
    }

    public function test_administrador_confirma_e_o_acesso_e_renovado(): void
    {
        $empresa = $this->barbearia();
        $this->logarDono($empresa);
        $fatura = $this->getJson('/api/assinatura')->json('proxima');
        $this->postJson("/api/assinatura/faturas/{$fatura['id']}/informar-pagamento")->assertOk();

        $admin = Administrador::create(['nome' => 'Adriano', 'email' => 'admin@exemplo.test', 'password' => 'segredo123', 'ativo' => true]);
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/barbearias')->assertJsonPath('barbearias.0.pagamento_informado', true);

        $this->postJson("/api/admin/faturas/{$fatura['id']}/confirmar")
            ->assertOk()
            ->assertJsonPath('acesso.vence_em', '2026-12-20')
            ->assertJsonPath('assinaturas.0.forma_pagamento', 'pix')
            ->assertJsonPath('assinaturas.0.inicio', '2026-11-21')
            ->assertJsonPath('proxima_fatura.inicio', '2026-12-21'); // já aparece a seguinte

        $this->assertSame('paga', Fatura::find($fatura['id'])->status);
        $this->postJson("/api/admin/faturas/{$fatura['id']}/confirmar")->assertStatus(422);
    }

    public function test_renovacao_manual_cancela_a_fatura_antiga(): void
    {
        $empresa = $this->barbearia();
        $dono = $this->logarDono($empresa);
        $antiga = $this->getJson('/api/assinatura')->json('proxima');

        $empresa->update(['acesso_ate' => '2027-01-20']);
        Sanctum::actingAs($dono->fresh());
        $this->getJson('/api/assinatura')->assertJsonPath('proxima.inicio', '2027-01-21');

        $this->assertSame('cancelada', Fatura::withoutGlobalScopes()->find($antiga['id'])->status);
        $this->postJson("/api/assinatura/faturas/{$antiga['id']}/pix")->assertStatus(422);
    }

    public function test_so_o_dono_da_propria_barbearia(): void
    {
        $outra = $this->barbearia();
        $this->logarDono($outra);
        $faturaOutra = $this->getJson('/api/assinatura')->json('proxima');

        $this->logarDono($this->barbearia());
        $this->postJson("/api/assinatura/faturas/{$faturaOutra['id']}/pix")->assertNotFound();

        $this->logarDono($outra, 'recepcao');
        $this->getJson('/api/assinatura')->assertForbidden();
    }
}
