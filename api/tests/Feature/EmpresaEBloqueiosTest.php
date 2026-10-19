<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Funcionario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmpresaEBloqueiosTest extends TestCase
{
    use RefreshDatabase;

    public function test_dono_atualiza_dados_da_pagina(): void
    {
        $this->logarComo('dono');

        $this->putJson('/api/empresa', [
            'slogan' => 'Estilo e tradição',
            'whatsapp' => '(11) 99999-0000',
            'instagram' => '@barbeariadoze',
            'cidade' => 'São Paulo',
            'uf' => 'sp',
            'cor_primaria' => '#aa3300',
        ])->assertOk()
            ->assertJsonPath('whatsapp', '11999990000')
            ->assertJsonPath('instagram', 'barbeariadoze')
            ->assertJsonPath('uf', 'SP')
            ->assertJsonPath('cor_primaria', '#aa3300');
    }

    public function test_slug_deve_ser_unico_e_valido(): void
    {
        Empresa::factory()->create(['slug' => 'ocupado']);
        $this->logarComo('dono');

        $this->putJson('/api/empresa', ['slug' => 'ocupado'])->assertUnprocessable()->assertJsonValidationErrors(['slug']);
        $this->putJson('/api/empresa', ['slug' => 'com espaço'])->assertUnprocessable()->assertJsonValidationErrors(['slug']);
        $this->putJson('/api/empresa', ['slug' => 'barbearia-nova'])->assertOk()->assertJsonPath('slug', 'barbearia-nova');
    }

    public function test_envia_e_remove_logo(): void
    {
        Storage::fake('public');
        $this->logarComo('dono');

        $url = $this->postJson('/api/empresa/imagens/logo', ['imagem' => UploadedFile::fake()->image('logo.png', 200, 200)])
            ->assertOk()
            ->json('logo_url');
        $this->assertNotNull($url);

        $this->deleteJson('/api/empresa/imagens/logo')->assertOk()->assertJsonPath('logo_url', null);
        $this->postJson('/api/empresa/imagens/outra', ['imagem' => UploadedFile::fake()->image('x.png')])->assertNotFound();
    }

    public function test_bloqueios_de_agenda(): void
    {
        $usuario = $this->logarComo('recepcao');
        $barbeiro = $this->naEmpresa($usuario->empresa_id, fn () => Funcionario::create(['nome' => 'Carlos']));

        $id = $this->postJson('/api/bloqueios', [
            'funcionario_id' => $barbeiro->id,
            'inicio' => '2030-01-10T12:00:00-03:00',
            'fim' => '2030-01-10T18:00:00-03:00',
            'motivo' => 'Consulta médica',
        ])->assertCreated()->json('id');

        // Gravado em UTC
        $this->assertDatabaseHas('bloqueios_agenda', ['id' => $id, 'inicio' => '2030-01-10 15:00:00']);

        // Bloqueio da barbearia inteira (feriado)
        $this->postJson('/api/bloqueios', ['inicio' => '2030-01-01T00:00:00-03:00', 'fim' => '2030-01-02T00:00:00-03:00', 'motivo' => 'Feriado'])
            ->assertCreated();

        $this->getJson("/api/bloqueios?funcionario_id={$barbeiro->id}")->assertOk()->assertJsonCount(2);

        $this->postJson('/api/bloqueios', ['inicio' => '2030-01-10T18:00:00Z', 'fim' => '2030-01-10T12:00:00Z'])
            ->assertUnprocessable()->assertJsonValidationErrors(['fim']);

        $this->deleteJson("/api/bloqueios/{$id}")->assertNoContent();
    }
}
