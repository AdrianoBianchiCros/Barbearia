<?php

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\Assinatura;
use App\Models\Empresa;
use App\Services\ContratoPrestacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Contrato de prestação de serviço gerado em PDF no cadastro da barbearia. */
class AdminContratoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 10:00', 'America/Sao_Paulo'));
        Storage::fake('local');
        Notification::fake();
        Sanctum::actingAs(Administrador::create(['nome' => 'Adriano', 'email' => 'admin@exemplo.test', 'password' => 'segredo123', 'ativo' => true]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function dados(array $extra = []): array
    {
        return $extra + [
            'nome' => 'Barbearia do Zé',
            'documento' => '11.222.333/0001-81',
            'razao_social' => 'JOSE DA SILVA BARBEARIA LTDA',
            'telefone' => '11988887777',
            'cidade' => 'São Paulo',
            'uf' => 'SP',
            'dono_nome' => 'José Silva',
            'dono_email' => 'ze@exemplo.test',
            'periodo' => '1m',
            'forma_pagamento' => 'pix',
            'valor' => 99.90,
        ];
    }

    public function test_textos_do_contrato_seguem_o_plano_e_a_taxa(): void
    {
        $contrato = app(ContratoPrestacao::class);
        $empresa = new Empresa(['nome' => 'Barbearia do Zé', 'documento' => '11222333000181']);

        $mensal = $contrato->dados($empresa, 'José', 'ze@exemplo.test', ['periodo' => '1m', 'forma_pagamento' => 'pix', 'valor' => 100, 'taxa_instalacao' => 250], now());
        $this->assertSame('mensal', $mensal['ciclo']);
        $this->assertSame('cem reais', $mensal['valor_extenso']);
        $this->assertSame(250.0, $mensal['taxa_instalacao']);
        $this->assertSame('duzentos e cinquenta reais', $mensal['taxa_extenso']);
        $this->assertSame('11.222.333/0001-81', $mensal['contratante']['documento']);

        // Plano trimestral pago à vista, sem taxa de instalação.
        $trimestral = $contrato->dados($empresa, 'José', 'ze@exemplo.test', ['periodo' => '3m', 'forma_pagamento' => 'boleto', 'valor' => 299.70], now());
        $this->assertSame('de 3 meses', $trimestral['ciclo']);
        $this->assertNull($trimestral['taxa_instalacao']);
        $this->assertSame('Boleto', $trimestral['forma_pagamento']);
        $this->assertSame('duzentos e noventa e nove reais e setenta centavos', $trimestral['valor_extenso']);
    }

    public function test_taxa_de_instalacao_fica_na_contratacao(): void
    {
        $this->postJson('/api/admin/barbearias', $this->dados(['taxa_instalacao' => 250]))->assertCreated();

        $this->assertSame('250.00', Assinatura::firstOrFail()->taxa_instalacao);
        $this->postJson('/api/admin/barbearias', $this->dados(['dono_email' => 'outro@exemplo.test', 'taxa_instalacao' => -1]))
            ->assertUnprocessable()->assertJsonValidationErrors('taxa_instalacao');
    }

    public function test_cadastro_sem_anexo_gera_e_guarda_o_contrato_em_pdf(): void
    {
        $this->postJson('/api/admin/barbearias', $this->dados())->assertCreated();

        $assinatura = Assinatura::firstOrFail();
        $this->assertSame('Contrato - Barbearia do Zé.pdf', $assinatura->contrato_nome);
        Storage::disk('local')->assertExists($assinatura->contrato_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($assinatura->contrato_path));

        $this->get("/api/admin/assinaturas/{$assinatura->id}/contrato")->assertOk()->assertDownload('Contrato - Barbearia do Ze.pdf'); // nome sem acento no cabeçalho (o com acento vai em filename*)
    }

    public function test_contrato_anexado_continua_valendo(): void
    {
        $this->post('/api/admin/barbearias', $this->dados([
            'contrato' => UploadedFile::fake()->create('assinado.pdf', 50, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame('assinado.pdf', Assinatura::firstOrFail()->contrato_nome);
    }

    public function test_previa_mostra_o_pdf_sem_gravar_nada(): void
    {
        $r = $this->post('/api/admin/contrato/previa', $this->dados(), ['Accept' => 'application/json'])->assertOk();

        $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $r->getContent());
        $this->assertSame(0, Empresa::count());
        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->postJson('/api/admin/contrato/previa', ['nome' => 'X'])->assertUnprocessable()
            ->assertJsonValidationErrors(['dono_nome', 'dono_email', 'periodo', 'valor']);
    }

    public function test_contrato_de_barbearia_ja_cadastrada_e_so_para_o_admin(): void
    {
        $id = $this->postJson('/api/admin/barbearias', $this->dados())->assertCreated()->json('barbearia.id');

        $r = $this->get("/api/admin/barbearias/{$id}/contrato-gerado")->assertOk();
        $this->assertStringStartsWith('%PDF', $r->getContent());

        $semContratacao = Empresa::factory()->create();
        $this->getJson("/api/admin/barbearias/{$semContratacao->id}/contrato-gerado")->assertUnprocessable();

        $this->logarComo('dono');
        $this->getJson("/api/admin/barbearias/{$id}/contrato-gerado")->assertForbidden();
    }
}
