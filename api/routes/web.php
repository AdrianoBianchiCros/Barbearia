<?php

use App\Http\Controllers\ClienteAgendamentoController;
use App\Http\Controllers\PaginaPublicaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Link do cliente para confirmar, remarcar ou cancelar: seudominio.com.br/a/{token}
Route::get('/a/{token}', [ClienteAgendamentoController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{32}')
    ->middleware('agendamento.token')
    ->name('agendamento.cliente');

// Página pública de cada barbearia: seudominio.com.br/{slug}
Route::get('/{slug}', [PaginaPublicaController::class, 'show'])
    ->where('slug', '[A-Za-z0-9_-]+')
    ->middleware('empresa.publica')
    ->name('pagina');
