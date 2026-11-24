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
        config(['assinatura.fidelidade_meses' => 3, 'assinatura.multa_rescisoria_percentual' => 20]);
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

    public function test_multa_e_mensalidade_seguem_a_fidelidade_e_o_plano(): void
    {
        $contrato = app(ContratoPrestacao::class);
        $empresa = new Empresa(['nome' => 'Barbearia do Zé', 'documento' => '11222333000181']);

        $mensal = $contrato->dados($empresa, 'José', 'ze@exemplo.test', ['periodo' => '1m', 'forma_pagamento' => 'pix', 'valor' => 99.90], now());
        $this->assertSame([39.96, 19.98, 0.0], array_column($mensal['multas'], 'valor'));
        $this->assertSame('Após 1 mês', $mensal['multas'][0]['momento']);
        $this->assertSame('11.222.333/0001-81', $mensal['contratante']['documento']);

        // Plano trimestral pago à vista: a multa usa a mensalidade equivalente.
        $trimestral = $contrato->dados($empresa, 'José', 'ze@exemplo.test', ['periodo' => '3m', 'forma_pagamento' => 'boleto', 'valor' => 299.70], now());
        $this->assertSame(99.90, $trimestral['mensalidade']);
        $this->assertSame('Boleto', $trimestral['forma_pagamento']);
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
