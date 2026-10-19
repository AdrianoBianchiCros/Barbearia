<?php

use App\Http\Controllers\PaginaPublicaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Página pública de cada barbearia: seudominio.com.br/{slug}
Route::get('/{slug}', [PaginaPublicaController::class, 'show'])
    ->where('slug', '[A-Za-z0-9_-]+')
    ->middleware('empresa.publica')
    ->name('pagina');
