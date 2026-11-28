<?php

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\Assinatura;
use App\Models\Empresa;
use App\Models\Fatura;
use App\Services\ContratoPrestacao;
use App\Services\Faturas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Plano Divulgação: cliente parceiro que usa o sistema de graça em troca de divulgação.
 * Todos os acessos, sem mensalidade, sem taxa, sem teste grátis, sem vencimento e com termo de parceria.
 */
class DivulgacaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 10:00', 'America/Sao_Paulo'));
        Storage::fake('local');
        Notification::fake();
        config(['assinatura.pix' => ['chave' => 'pix@exemplo.test', 'recebedor' => 'Plataforma', 'cidade' => 'São Paulo']]);
        Sanctum::actingAs(Administrador::create(['nome' => 'Adriano', 'email' => 'admin@exemplo.test', 'password' => 'segredo123', 'ativo' => true]));
    }

    private function dados(array $dados = []): array
    {
        return $dados + [
            'nome' => 'Barbearia Parceira',
            'dono_nome' => 'Ana Parceira',
            'dono_email' => 'ana@exemplo.test',
            'periodo' => 'divulgacao',
            // A tela não manda valores na Divulgação; mesmo que mande, são ignorados.
            'forma_pagamento' => 'pix',
            'valor' => 150,
            'taxa_instalacao' => 200,
        ];
    }

    public function test_aparece_entre_os_planos(): void
    {
        $this->getJson('/api/admin/opcoes')->assertOk()
            ->assertJsonFragment(['valor' => 'divulgacao', 'rotulo' => 'Divulgação (parceria, sem mensalidade)']);
    }

    public function test_cadastro_libera_sem_vencimento_sem_cobranca_e_com_termo_de_parceria(): void
    {
        $id = $this->postJson('/api/admin/barbearias', $this->dados(['valor' => null]))
            ->assertCreated()
            ->assertJsonPath('barbearia.acesso.situacao', 'sem_prazo')
            ->assertJsonPath('barbearia.acesso.vence_em', null)
            ->assertJsonPath('barbearia.divulgacao', true)
            ->json('barbearia.id');

        $empresa = Empresa::findOrFail($id);
        $this->assertNull($empresa->acesso_ate);
        $this->assertTrue($empresa->acessoLiberado());

        // Uma linha só, sem teste grátis, sem valor nem taxa, e o termo de parceria gerado.
        $assinaturas = Assinatura::where('empresa_id', $id)->get();
        $this->assertCount(1, $assinaturas);
        $a = $assinaturas->first();
        $this->assertSame('divulgacao', $a->periodo);
        $this->assertFalse((bool) $a->gratis);
        $this->assertSame('0.00', $a->valor);
        $this->assertNull($a->taxa_instalacao);
        $this->assertSame('cortesia', $a->forma_pagamento);
        $this->assertSame('Termo de parceria - Barbearia Parceira.pdf', $a->contrato_nome);
        Storage::disk('local')->assertExists($a->contrato_path);

        // Nenhuma fatura para o dono pagar.
        $this->assertNull(app(Faturas::class)->proxima($empresa));
    }

    public function test_previa_gera_o_termo_de_parceria(): void
    {
        $resposta = $this->post('/api/admin/contrato/previa', $this->dados())->assertOk();

        $this->assertSame('application/pdf', $resposta->headers->get('Content-Type'));
        $this->assertStringContainsString('Termo de parceria', (string) $resposta->headers->get('Content-Disposition'));

        $html = view('contratos.parceria-divulgacao', app(ContratoPrestacao::class)
            ->dados(new Empresa(['nome' => 'Barbearia Parceira']), 'Ana', 'ana@exemplo.test', ['periodo' => 'divulgacao', 'forma_pagamento' => 'cortesia', 'valor' => 0], now()))->render();
        $this->assertStringContainsString('prazo indeterminado', $html);
        $this->assertStringContainsString('gratuitamente', $html);
        $this->assertStringNotContainsString('mensais', $html);
    }

    public function test_cliente_pago_vira_parceiro_e_parceiro_vira_pago(): void
    {
        $id = $this->postJson('/api/admin/barbearias', $this->dados(['periodo' => '1m', 'valor' => 100, 'taxa_instalacao' => null]))
            ->assertCreated()->json('barbearia.id');
        $empresa = Empresa::findOrFail($id);
        $this->assertNotNull(app(Faturas::class)->proxima($empresa)); // tem fatura em aberto

        // Vira parceiro: sem vencimento e a fatura em aberto é cancelada.
        $this->postJson("/api/admin/barbearias/{$id}/renovar", ['periodo' => 'divulgacao'])
            ->assertOk()
            ->assertJsonPath('acesso.situacao', 'sem_prazo')
            ->assertJsonPath('divulgacao', true);
        $empresa->refresh();
        $this->assertNull($empresa->acesso_ate);
        $this->assertNull(app(Faturas::class)->proxima($empresa));
        $this->assertSame(0, Fatura::withoutGlobalScopes()->where('empresa_id', $id)->whereIn('status', Fatura::EM_ABERTO)->count());

        // Parceria encerrada com plano pago: volta a vencer, começando hoje.
        $this->postJson("/api/admin/barbearias/{$id}/renovar", ['periodo' => '1m', 'forma_pagamento' => 'pix', 'valor' => 120])
            ->assertOk()
            ->assertJsonPath('acesso.vence_em', '2026-12-09')
            ->assertJsonPath('divulgacao', false);
    }
}
