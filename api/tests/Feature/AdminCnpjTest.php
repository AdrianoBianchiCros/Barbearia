<?php

namespace Tests\Feature;

use App\Models\Administrador;
use App\Models\Empresa;
use App\Services\ConsultaCnpj;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Cadastro da barbearia pelo administrador a partir do CNPJ (API pública do OpenCNPJ). */
class AdminCnpjTest extends TestCase
{
    use RefreshDatabase;

    private const CNPJ = '11222333000181';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
        Http::preventStrayRequests();
        Sanctum::actingAs(Administrador::create(['nome' => 'Adriano', 'email' => 'admin@exemplo.test', 'password' => 'segredo123', 'ativo' => true]));
    }

    /** Resposta no formato da API (campos reais do OpenCNPJ). */
    private function respostaApi(): array
    {
        return [
            'cnpj' => self::CNPJ,
            'razao_social' => 'JOSE DA SILVA BARBEARIA LTDA',
            'nome_fantasia' => 'BARBEARIA DO ZE',
            'situacao_cadastral' => 'Ativa',
            'data_inicio_atividade' => '2019-03-12',
            'cnae_principal' => '9602501',
            'cnaes' => [['codigo' => '9602501', 'descricao' => 'Cabeleireiros, manicure e pedicure', 'is_principal' => true]],
            'natureza_juridica' => 'Sociedade Empresária Limitada',
            'tipo_logradouro' => 'RUA',
            'logradouro' => 'DAS FLORES',
            'numero' => '10',
            'complemento' => 'SALA  2',
            'bairro' => 'CENTRO',
            'cep' => '01001000',
            'uf' => 'SP',
            'municipio' => 'SAO PAULO',
            'email' => 'CONTATO@BARBEARIADOZE.COM.BR',
            'telefones' => [['ddd' => '11', 'numero' => '33334444', 'is_fax' => true], ['ddd' => '11', 'numero' => '988887777', 'is_fax' => false]],
            'porte_empresa' => 'Micro Empresa',
            'opcao_simples' => 'S',
            'opcao_mei' => 'N',
            'QSA' => [['nome_socio' => 'JOSE DA SILVA', 'qualificacao_socio' => 'Sócio-Administrador']],
        ];
    }

    public function test_consulta_o_cnpj_e_devolve_os_dados_do_cadastro(): void
    {
        Http::fake([ConsultaCnpj::URL.self::CNPJ => Http::response($this->respostaApi())]);

        $this->getJson('/api/admin/cnpj/11.222.333-0001-81')->assertOk()->assertJson([
            'documento' => self::CNPJ,
            'nome' => 'Barbearia Do Ze',
            'razao_social' => 'JOSE DA SILVA BARBEARIA LTDA',
            'situacao_cadastral' => 'Ativa',
            'data_abertura' => '2019-03-12',
            'cnae_principal' => '9602501 - Cabeleireiros, manicure e pedicure',
            'porte' => 'Micro Empresa',
            'optante_simples' => true,
            'optante_mei' => false,
            'email' => 'contato@barbeariadoze.com.br',
            'telefone' => '11988887777', // o primeiro que não é fax
            'cep' => '01001000',
            'logradouro' => 'Rua Das Flores',
            'numero' => '10',
            'complemento' => 'Sala 2',
            'bairro' => 'Centro',
            'cidade' => 'Sao Paulo',
            'uf' => 'SP',
            'dono_nome' => 'Jose Da Silva',
            'ja_cadastrada' => null,
        ]);
    }

    public function test_cnpj_invalido_nao_encontrado_ou_api_fora_do_ar(): void
    {
        Http::fake([
            ConsultaCnpj::URL.'00000000000191' => Http::response(['message' => 'not found'], 404),
            ConsultaCnpj::URL.self::CNPJ => Http::response('erro', 500),
        ]);

        // Dígito verificador errado: nem consulta a API.
        $this->getJson('/api/admin/cnpj/11222333000182')->assertUnprocessable()
            ->assertJsonValidationErrors(['documento' => 'CNPJ inválido']);
        $this->getJson('/api/admin/cnpj/00000000000191')->assertUnprocessable()
            ->assertJsonValidationErrors(['documento' => 'CNPJ não encontrado na Receita Federal.']);
        $this->getJson('/api/admin/cnpj/'.self::CNPJ)->assertStatus(503)
            ->assertJsonPath('message', 'Não foi possível consultar o CNPJ agora. Tente de novo em instantes.');
    }

    public function test_avisa_quando_o_cnpj_ja_tem_barbearia_e_so_admin_consulta(): void
    {
        Http::fake([ConsultaCnpj::URL.'*' => Http::response($this->respostaApi())]);
        Empresa::factory()->create(['nome' => 'Zé Antiga', 'documento' => self::CNPJ]);

        $this->getJson('/api/admin/cnpj/'.self::CNPJ)->assertOk()->assertJsonPath('ja_cadastrada', 'Zé Antiga');

        $this->logarComo('dono');
        $this->getJson('/api/admin/cnpj/'.self::CNPJ)->assertForbidden();
    }

    public function test_cadastro_grava_os_dados_da_empresa(): void
    {
        $this->postJson('/api/admin/barbearias', [
            'nome' => 'Barbearia do Zé',
            'documento' => '11.222.333/0001-81',
            'razao_social' => 'JOSE DA SILVA BARBEARIA LTDA',
            'email' => 'contato@barbeariadoze.com.br',
            'situacao_cadastral' => 'Ativa',
            'data_abertura' => '2019-03-12',
            'natureza_juridica' => 'Sociedade Empresária Limitada',
            'cnae_principal' => '9602501 - Cabeleireiros, manicure e pedicure',
            'porte' => 'Micro Empresa',
            'optante_simples' => '1',
            'optante_mei' => '0',
            'cep' => '01001-000',
            'logradouro' => 'Rua das Flores',
            'numero' => '10',
            'bairro' => 'Centro',
            'cidade' => 'São Paulo',
            'uf' => 'sp',
            'dono_nome' => 'José Silva',
            'dono_email' => 'ze@exemplo.test',
            'periodo' => '1m',
            'forma_pagamento' => 'pix',
            'valor' => 99.90,
        ])->assertCreated()
            ->assertJsonPath('barbearia.documento', self::CNPJ)
            ->assertJsonPath('barbearia.empresa.razao_social', 'JOSE DA SILVA BARBEARIA LTDA')
            ->assertJsonPath('barbearia.empresa.optante_simples', true);

        $empresa = Empresa::firstOrFail();
        $this->assertSame('01001000', $empresa->cep);
        $this->assertSame('SP', $empresa->uf);
        $this->assertSame('contato@barbeariadoze.com.br', $empresa->email); // e-mail da empresa, não o do dono
        $this->assertSame('2019-03-12', $empresa->data_abertura->toDateString());
        $this->assertFalse($empresa->optante_mei);
    }
}
