<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmpresaController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('registrar', [AuthController::class, 'registrar'])->middleware('throttle:registro');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
});

// Rotas da área logada: todas filtradas pela barbearia do usuário.
Route::middleware(['auth:sanctum', 'empresa'])->group(function () {
    Route::get('auth/eu', [AuthController::class, 'eu']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('empresa', [EmpresaController::class, 'show']);
    Route::put('empresa', [EmpresaController::class, 'update'])->middleware('perfil:dono');
});
