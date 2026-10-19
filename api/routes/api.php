<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AgendamentoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BloqueioAgendaController;
use App\Http\Controllers\CaixaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ComandaController;
use App\Http\Controllers\ComissaoController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\FuncionarioController;
use App\Http\Controllers\LancamentoController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\RelatorioController;
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
    Route::get('agenda/busca', [AgendaController::class, 'busca']);
    Route::patch('agendamentos/{agendamento}/status', [AgendaController::class, 'status']);
    Route::get('agenda/horarios', [AgendamentoController::class, 'horarios']);
    Route::post('agendamentos', [AgendamentoController::class, 'store']);
    Route::put('agendamentos/{agendamento}', [AgendamentoController::class, 'update']);

    // Leitura: qualquer perfil
    Route::get('empresa', [EmpresaController::class, 'show']);
    Route::get('servicos', [ServicoController::class, 'index']);
    Route::get('servicos/{servico}', [ServicoController::class, 'show']);
    Route::get('funcionarios', [FuncionarioController::class, 'index']);
    Route::get('funcionarios/{funcionario}', [FuncionarioController::class, 'show']);
    Route::get('bloqueios', [BloqueioAgendaController::class, 'index']);
    Route::get('clientes', [ClienteController::class, 'index']);
    Route::get('clientes/{cliente}', [ClienteController::class, 'show']);
    Route::get('produtos', [ProdutoController::class, 'index']);
    Route::get('comissoes', [ComissaoController::class, 'index']); // barbeiro vê só a própria

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

        Route::apiResource('produtos', ProdutoController::class)->only(['store', 'update', 'destroy']);
        Route::get('produtos/{produto}/entradas', [ProdutoController::class, 'entradas']);
        Route::post('produtos/{produto}/entradas', [ProdutoController::class, 'novaEntrada']);
        Route::put('produtos/{produto}/entradas/{entrada}', [ProdutoController::class, 'editarEntrada']);
        Route::delete('produtos/{produto}/entradas/{entrada}', [ProdutoController::class, 'excluirEntrada']);

        Route::get('relatorios/resumo', [RelatorioController::class, 'resumo']);
        Route::get('relatorios/clientes', [RelatorioController::class, 'clientes']);

        // Financeiro restrito: estorno, despesas, vales e repasses
        Route::post('comandas/{comanda}/estornar', [ComandaController::class, 'estornar']);
        Route::get('lancamentos', [LancamentoController::class, 'index']);
        Route::post('lancamentos', [LancamentoController::class, 'store']);
        Route::post('lancamentos/{lancamento}/pagar', [LancamentoController::class, 'pagar']);
        Route::delete('lancamentos/{lancamento}', [LancamentoController::class, 'destroy']);
    });

    // Dono e recepção: operação do dia a dia
    Route::middleware('perfil:dono,recepcao')->group(function () {
        Route::post('clientes', [ClienteController::class, 'store']);
        Route::put('clientes/{cliente}', [ClienteController::class, 'update']);

        Route::post('bloqueios', [BloqueioAgendaController::class, 'store']);
        Route::delete('bloqueios/{bloqueio}', [BloqueioAgendaController::class, 'destroy']);

        Route::get('agenda/encaixe', [AgendamentoController::class, 'encaixe']);

        // Comandas e caixa
        Route::get('comandas', [ComandaController::class, 'index']);
        Route::post('comandas', [ComandaController::class, 'store']);
        Route::get('comandas/{comanda}', [ComandaController::class, 'show']);
        Route::post('comandas/{comanda}/fechar', [ComandaController::class, 'fechar']);
        Route::post('comandas/{comanda}/cancelar', [ComandaController::class, 'cancelar']);
        Route::post('agendamentos/{agendamento}/comanda', [ComandaController::class, 'doAgendamento']);

        Route::get('caixa/atual', [CaixaController::class, 'atual']);
        Route::post('caixa/abrir', [CaixaController::class, 'abrir']);
        Route::post('caixa/movimentos', [CaixaController::class, 'movimento']);
        Route::post('caixa/fechar', [CaixaController::class, 'fechar']);
        Route::get('caixas', [CaixaController::class, 'index']);
        Route::get('caixas/{caixa}', [CaixaController::class, 'show']);
    });
});
