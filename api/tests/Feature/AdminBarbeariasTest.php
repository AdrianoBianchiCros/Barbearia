<?php

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\Assinatura;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBarbeariasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 10:00', 'America/Sao_Paulo'));
        Storage::fake('local');
    }

    private function logarAdmin(): Administrador
    {
        $admin = Administrador::create(['nome' => 'Adriano', 'email' => 'admin@exemplo.test', 'password' => 'segredo123', 'ativo' => true]);
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function dadosNova(array $dados = []): array
    {
        return $dados + [
            'nome' => 'Barbearia do Zé',
            'telefone' => '11999990000',
            'dono_nome' => 'José Silva',
            'dono_email' => 'ze@exemplo.test',
            'periodo' => '1m',
            'forma_pagamento' => 'pix',
            'valor' => 99.90,
        ];
    }

    public function test_login_do_administrador(): void
    {
        Administrador::create(['nome' => 'Adriano', 'email' => 'admin@exemplo.test', 'password' => 'segredo123', 'ativo' => true]);

        $this->postJson('/api/admin/auth/login', ['email' => 'admin@exemplo.test', 'password' => 'errada'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $token = $this->postJson('/api/admin/auth/login', ['email' => 'admin@exemplo.test', 'password' => 'segredo123'])
            ->assertOk()->assertJsonPath('admin.nome', 'Adriano')->json('token');

        $this->withToken($token)->getJson('/api/admin/barbearias')->assertOk();
    }

    public function test_area_do_admin_e_painel_da_barbearia_nao_se_misturam(): void
    {
        $this->getJson('/api/admin/barbearias')->assertUnauthorized();

        $this->logarComo('dono');
        $this->getJson('/api/admin/barbearias')->assertForbidden();

        $this->logarAdmin();
        $this->getJson('/api/agenda/dia')->assertForbidden();
    }

    public function test_cadastra_barbearia_com_dono_contrato_e_prazo(): void
    {
        $admin = $this->logarAdmin();
        $contrato = UploadedFile::fake()->create('contrato-ze.pdf', 200, 'application/pdf');

        $resposta = $this->post('/api/admin/barbearias', $this->dadosNova(['contrato' => $contrato]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('barbearia.slug', 'barbearia-do-ze')
            ->assertJsonPath('barbearia.acesso.situacao', 'em_dia')
            ->assertJsonPath('barbearia.acesso.vence_em', '2026-12-09')
            ->assertJsonPath('barbearia.dono.email', 'ze@exemplo.test')
            ->assertJsonPath('acesso.email', 'ze@exemplo.test');

        $empresa = Empresa::where('slug', 'barbearia-do-ze')->first();
        $this->assertSame('2026-12-09', $empresa->acesso_ate->toDateString());

        $assinatura = Assinatura::first();
        $this->assertSame($admin->id, $assinatura->administrador_id);
        $this->assertSame('2026-11-10', $assinatura->inicio->toDateString());
        $this->assertSame('99.90', $assinatura->valor);
        $this->assertSame('contrato-ze.pdf', $assinatura->contrato_nome);
        Storage::disk('local')->assertExists($assinatura->contrato_path);

        // A senha provisória entra no painel da barbearia.
        $senha = $resposta->json('acesso.senha_provisoria');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{10}$/', $senha);
        $this->postJson('/api/auth/login', ['email' => 'ze@exemplo.test', 'password' => $senha])
            ->assertOk()
            ->assertJsonPath('usuario.perfil', 'dono')
            ->assertJsonPath('acesso.vence_em', '2026-12-09');
    }

    public function test_cadastro_valida_os_campos(): void
    {
        $this->logarAdmin();
        User::factory()->create(['email' => 'ze@exemplo.test']);

        $this->postJson('/api/admin/barbearias', $this->dadosNova(['periodo' => '2m', 'forma_pagamento' => 'cheque', 'nome' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nome', 'dono_email', 'periodo', 'forma_pagamento']);

        $this->assertDatabaseCount('empresas', 1); // só a do usuário criado acima
    }

    public function test_periodo_de_teste_vale_sete_dias(): void
    {
        $this->logarAdmin();

        $this->postJson('/api/admin/barbearias', $this->dadosNova(['periodo' => 'teste', 'forma_pagamento' => 'cortesia', 'valor' => 0]))
            ->assertCreated()
            ->assertJsonPath('barbearia.acesso.vence_em', '2026-11-16')
            ->assertJsonPath('barbearia.acesso.situacao', 'vencendo');
    }

    public function test_renovacao_soma_ao_vencimento_atual(): void
    {
        $this->logarAdmin();
        $empresa = Empresa::factory()->create(['acesso_ate' => '2026-11-20']);

        $this->postJson("/api/admin/barbearias/{$empresa->id}/renovar", ['periodo' => '3m', 'forma_pagamento' => 'boleto', 'valor' => 270])
            ->assertOk()
            ->assertJsonPath('acesso.vence_em', '2027-02-20')
            ->assertJsonCount(1, 'assinaturas')
            ->assertJsonPath('assinaturas.0.inicio', '2026-11-21');
    }

    public function test_renovacao_de_acesso_vencido_comeca_hoje(): void
    {
        $this->logarAdmin();
        $empresa = Empresa::factory()->create(['acesso_ate' => '2026-10-01']);

        $this->postJson("/api/admin/barbearias/{$empresa->id}/renovar", ['periodo' => '12m', 'forma_pagamento' => 'pix', 'valor' => 900])
            ->assertOk()
            ->assertJsonPath('acesso.vence_em', '2027-11-09')
            ->assertJsonPath('acesso.situacao', 'em_dia');
    }

    public function test_suspender_bloqueia_na_hora_e_reativar_libera(): void
    {
        $empresa = Empresa::factory()->create();
        $dono = User::factory()->for($empresa)->create(['email' => 'ze@exemplo.test', 'password' => 'segredo123']);
        $dono->createToken('teste');

        $this->logarAdmin();
        $this->postJson("/api/admin/barbearias/{$empresa->id}/suspender")
            ->assertOk()->assertJsonPath('acesso.situacao', 'suspensa');
        $this->assertSame(0, $dono->tokens()->count());

        $this->postJson('/api/auth/login', ['email' => 'ze@exemplo.test', 'password' => 'segredo123'])
            ->assertUnprocessable();

        $this->postJson("/api/admin/barbearias/{$empresa->id}/reativar")
            ->assertOk()->assertJsonPath('acesso.situacao', 'sem_prazo');

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => 'ze@exemplo.test', 'password' => 'segredo123'])->assertOk();
    }

    public function test_gera_nova_senha_para_o_dono(): void
    {
        $empresa = Empresa::factory()->create();
        $dono = User::factory()->for($empresa)->create(['email' => 'ze@exemplo.test']);
        $dono->createToken('antigo');

        $this->logarAdmin();
        $senha = $this->postJson("/api/admin/barbearias/{$empresa->id}/nova-senha")
            ->assertOk()->json('acesso.senha_provisoria');

        $this->assertSame(0, $dono->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => 'ze@exemplo.test', 'password' => $senha])->assertOk();
    }

    public function test_lista_detalhe_e_download_do_contrato(): void
    {
        $this->logarAdmin();
        $this->post('/api/admin/barbearias', $this->dadosNova([
            'contrato' => UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertCreated();
        Empresa::factory()->create(['nome' => 'Antiga', 'acesso_ate' => null]);

        $this->getJson('/api/admin/barbearias')
            ->assertOk()
            ->assertJsonCount(2, 'barbearias')
            ->assertJsonPath('barbearias.0.nome', 'Antiga')
            ->assertJsonPath('barbearias.0.acesso.situacao', 'sem_prazo')
            ->assertJsonPath('barbearias.1.ultima_assinatura.periodo', '1m');

        $empresa = Empresa::where('slug', 'barbearia-do-ze')->first();
        $assinaturaId = $this->getJson("/api/admin/barbearias/{$empresa->id}")
            ->assertOk()
            ->assertJsonPath('assinaturas.0.tem_contrato', true)
            ->assertJsonMissingPath('assinaturas.0.contrato_path')
            ->json('assinaturas.0.id');

        $this->get("/api/admin/assinaturas/{$assinaturaId}/contrato")
            ->assertOk()
            ->assertDownload('contrato.pdf');
    }

    public function test_comando_cria_administrador(): void
    {
        $this->artisan('admin:criar', ['email' => 'novo@exemplo.test', 'nome' => 'Novo', '--senha' => 'curta'])
            ->assertFailed();

        $this->artisan('admin:criar', ['email' => 'novo@exemplo.test', 'nome' => 'Novo', '--senha' => 'segredo123'])
            ->expectsOutput('Administrador criado.')
            ->assertSuccessful();

        $this->postJson('/api/admin/auth/login', ['email' => 'novo@exemplo.test', 'password' => 'segredo123'])->assertOk();
    }
}
