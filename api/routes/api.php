<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BloqueioAgendaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\FuncionarioController;
use App\Http\Controllers\ServicoController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('registrar', [AuthController::class, 'registrar'])->middleware('throttle:registro');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
});

// Rotas da área logada: todas filtradas pela barbearia do usuário.
Route::middleware(['auth:sanctum', 'empresa'])->group(function () {
    Route::get('auth/eu', [AuthController::class, 'eu']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // Agenda do dia: qualquer perfil (barbeiro altera só os próprios atendimentos)
    Route::get('agenda/dia', [AgendaController::class, 'dia']);
    Route::patch('agendamentos/{agendamento}/status', [AgendaController::class, 'status']);

    // Leitura: qualquer perfil
    Route::get('empresa', [EmpresaController::class, 'show']);
    Route::get('servicos', [ServicoController::class, 'index']);
    Route::get('servicos/{servico}', [ServicoController::class, 'show']);
    Route::get('funcionarios', [FuncionarioController::class, 'index']);
    Route::get('funcionarios/{funcionario}', [FuncionarioController::class, 'show']);
    Route::get('bloqueios', [BloqueioAgendaController::class, 'index']);
    Route::get('clientes', [ClienteController::class, 'index']);
    Route::get('clientes/{cliente}', [ClienteController::class, 'show']);

    // Dono: configurações da barbearia, preços, equipe e comissões
    Route::middleware('perfil:dono')->group(function () {
        Route::put('empresa', [EmpresaController::class, 'update']);
        Route::post('empresa/imagens/{tipo}', [EmpresaController::class, 'enviarImagem']);
        Route::delete('empresa/imagens/{tipo}', [EmpresaController::class, 'removerImagem']);

        Route::apiResource('servicos', ServicoController::class)->only(['store', 'update', 'destroy']);

        Route::apiResource('funcionarios', FuncionarioController::class)->only(['store', 'update', 'destroy']);
        Route::put('funcionarios/{funcionario}/servicos', [FuncionarioController::class, 'servicos']);
        Route::put('funcionarios/{funcionario}/horarios', [FuncionarioController::class, 'horarios']);
        Route::post('funcionarios/{funcionario}/foto', [FuncionarioController::class, 'foto']);

        Route::delete('clientes/{cliente}', [ClienteController::class, 'destroy']);
    });

    // Dono e recepção: operação do dia a dia
    Route::middleware('perfil:dono,recepcao')->group(function () {
        Route::post('clientes', [ClienteController::class, 'store']);
        Route::put('clientes/{cliente}', [ClienteController::class, 'update']);

        Route::post('bloqueios', [BloqueioAgendaController::class, 'store']);
        Route::delete('bloqueios/{bloqueio}', [BloqueioAgendaController::class, 'destroy']);
    });
});
