<?php

namespace Tests\Feature;

use App\Http\Controllers\GaleriaController;
use App\Models\Empresa;
use App\Models\GaleriaFoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GaleriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_dono_envia_legenda_e_remove_fotos(): void
    {
        $this->logarComo('dono');

        $foto = $this->postJson('/api/galeria', ['imagem' => UploadedFile::fake()->image('corte.jpg')])
            ->assertCreated()
            ->assertJsonPath('ordem', 1)
            ->json();
        $caminho = GaleriaFoto::findOrFail($foto['id'])->caminho;
        Storage::disk('public')->assertExists($caminho);

        $this->putJson("/api/galeria/{$foto['id']}", ['legenda' => 'Degradê navalhado'])
            ->assertOk()->assertJsonPath('legenda', 'Degradê navalhado');
        $this->getJson('/api/galeria')->assertOk()->assertJsonCount(1)->assertJsonMissingPath('0.caminho');

        $this->deleteJson("/api/galeria/{$foto['id']}")->assertNoContent();
        Storage::disk('public')->assertMissing($caminho);
        $this->assertSame(0, GaleriaFoto::count());
    }

    public function test_limite_de_fotos_e_tipo_de_arquivo(): void
    {
        $usuario = $this->logarComo('dono');
        $this->naEmpresa($usuario->empresa_id, function () {
            foreach (range(1, GaleriaController::MAX_FOTOS) as $i) {
                GaleriaFoto::create(['caminho' => "x/{$i}.jpg", 'ordem' => $i]);
            }
        });

        $this->postJson('/api/galeria', ['imagem' => UploadedFile::fake()->image('a.jpg')])
            ->assertUnprocessable()->assertJsonValidationErrors('imagem');
        $this->postJson('/api/galeria', ['imagem' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
            ->assertUnprocessable()->assertJsonValidationErrors('imagem');
    }

    public function test_so_o_dono_gerencia_e_cada_barbearia_ve_a_sua(): void
    {
        $outra = Empresa::factory()->create();
        $alheia = $this->naEmpresa($outra, fn () => GaleriaFoto::create(['caminho' => 'x/1.jpg']));

        $this->logarComo('recepcao');
        $this->postJson('/api/galeria', ['imagem' => UploadedFile::fake()->image('a.jpg')])->assertForbidden();

        $this->logarComo('dono');
        $this->getJson('/api/galeria')->assertOk()->assertJsonCount(0);
        $this->deleteJson("/api/galeria/{$alheia->id}")->assertNotFound();
    }

    public function test_pagina_usa_fotos_da_galeria_ou_ilustrativas(): void
    {
        $empresa = Empresa::factory()->create(['slug' => 'minha']);

        $this->get('/minha')->assertOk()->assertSee('img/pagina/corte.jpg');

        $this->naEmpresa($empresa, fn () => GaleriaFoto::create(['caminho' => "empresas/{$empresa->id}/galeria/f.jpg", 'legenda' => 'Fade clássico']));
        $this->get('/minha')->assertOk()
            ->assertSee("empresas/{$empresa->id}/galeria/f.jpg")
            ->assertSee('Fade clássico')
            ->assertDontSee('img/pagina/corte.jpg');
    }
}
