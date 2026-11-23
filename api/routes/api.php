<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AgendamentoController;
use App\Http\Controllers\AssinaturaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvisoAgendaController;
use App\Http\Controllers\BarbeiroController;
use App\Http\Controllers\BloqueioAgendaController;
use App\Http\Controllers\CaixaController;
use App\Http\Controllers\ClienteAgendamentoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ComandaController;
use App\Http\Controllers\ComissaoController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\FuncionarioController;
use App\Http\Controllers\GaleriaController;
use App\Http\Controllers\LancamentoController;
use App\Http\Controllers\PaginaPublicaController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\ServicoController;
use App\Http\Controllers\WebhookWhatsappController;
use App\Http\Controllers\WhatsappController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('esqueci-senha', [AuthController::class, 'esqueciSenha'])->middleware('throttle:senha');
    Route::post('redefinir-senha', [AuthController::class, 'redefinirSenha'])->middleware('throttle:senha');
});

// Agendamento online pela página da barbearia (sem login).
Route::prefix('publico/{slug}')->middleware(['empresa.publica', 'acesso.liberado'])->group(function () {
    Route::get('horarios', [PaginaPublicaController::class, 'horarios'])->middleware('throttle:60,1');
    Route::post('agendamentos', [PaginaPublicaController::class, 'agendar'])->middleware('throttle:agendamento-publico');
});

// Link do cliente recebido no WhatsApp: confirmar, remarcar ou cancelar o horário.
// Com o prazo da barbearia vencido, o cliente ainda confirma ou cancela, mas não remarca.
Route::prefix('a/{token}')->middleware('agendamento.token')->group(function () {
    Route::get('horarios', [ClienteAgendamentoController::class, 'horarios'])->middleware(['throttle:60,1', 'acesso.liberado']);
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('confirmar', [ClienteAgendamentoController::class, 'confirmar']);
        Route::post('cancelar', [ClienteAgendamentoController::class, 'cancelar']);
        Route::post('remarcar', [ClienteAgendamentoController::class, 'remarcar'])->middleware('acesso.liberado');
    });
});

// Área do administrador da plataforma: cadastro das barbearias, contrato e prazo de acesso.
Route::prefix('admin')->group(function () {
    Route::post('auth/login', [Admin\AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/esqueci-senha', [Admin\AuthController::class, 'esqueciSenha'])->middleware('throttle:senha');
    Route::post('auth/redefinir-senha', [Admin\AuthController::class, 'redefinirSenha'])->middleware('throttle:senha');

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('auth/eu', [Admin\AuthController::class, 'eu']);
        Route::post('auth/logout', [Admin\AuthController::class, 'logout']);

        Route::get('opcoes', [Admin\BarbeariaController::class, 'opcoes']);
        Route::get('barbearias', [Admin\BarbeariaController::class, 'index']);
        Route::post('barbearias', [Admin\BarbeariaController::class, 'store']);
        Route::get('barbearias/{empresa}', [Admin\BarbeariaController::class, 'show']);
        Route::post('barbearias/{empresa}/renovar', [Admin\BarbeariaController::class, 'renovar']);
        Route::post('barbearias/{empresa}/suspender', [Admin\BarbeariaController::class, 'suspender']);
        Route::post('barbearias/{empresa}/reativar', [Admin\BarbeariaController::class, 'reativar']);
        Route::post('barbearias/{empresa}/nova-senha', [Admin\BarbeariaController::class, 'novaSenha']);
        Route::delete('barbearias/{empresa}', [Admin\BarbeariaController::class, 'excluir']);
        Route::get('assinaturas/{assinatura}/contrato', [Admin\BarbeariaController::class, 'contrato']);
        Route::post('faturas/{fatura}/confirmar', [Admin\BarbeariaController::class, 'confirmarFatura']);
    });
});

// Webhook da WhatsApp Cloud API (status de entrega e respostas dos clientes).
Route::get('webhooks/whatsapp', [WebhookWhatsappController::class, 'verificar']);
Route::post('webhooks/whatsapp', [WebhookWhatsappController::class, 'receber']);

// Rotas da área logada: todas filtradas pela barbearia do usuário.
Route::middleware(['auth:sanctum', 'empresa'])->group(function () {
    Route::get('auth/eu', [AuthController::class, 'eu']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/trocar-senha', [AuthController::class, 'trocarSenha'])->middleware('throttle:senha');

    // Sino da agenda (todos os perfis; o barbeiro vê só a própria agenda).
    Route::get('avisos', [AvisoAgendaController::class, 'index']);
    Route::post('avisos/vistos', [AvisoAgendaController::class, 'vistos']);

    // Painel do barbeiro: só a própria agenda e a própria comissão.
    Route::middleware('perfil:barbeiro')->prefix('barbeiro')->group(function () {
        Route::get('agenda', [BarbeiroController::class, 'agenda']);
        Route::get('comissoes', [ComissaoController::class, 'index']);
    });

    // Painel da barbearia: dono e recepção (o barbeiro usa só as rotas acima).
    Route::middleware('perfil:dono,recepcao')->group(function () {
        // Agenda do dia
        Route::get('agenda/dia', [AgendaController::class, 'dia']);
        Route::get('agenda/busca', [AgendaController::class, 'busca']);
        Route::patch('agendamentos/{agendamento}/status', [AgendaController::class, 'status']);
        Route::get('agenda/horarios', [AgendamentoController::class, 'horarios']);
        Route::post('agendamentos', [AgendamentoController::class, 'store']);
        Route::put('agendamentos/{agendamento}', [AgendamentoController::class, 'update']);
        Route::post('agendamentos/{agendamento}/enviar-confirmacao', [AgendamentoController::class, 'enviarConfirmacao'])
            ->middleware('throttle:20,1');

        // Leitura: dono e recepção
        Route::get('empresa', [EmpresaController::class, 'show']);
        Route::get('servicos', [ServicoController::class, 'index']);
        Route::get('servicos/{servico}', [ServicoController::class, 'show']);
        Route::get('funcionarios', [FuncionarioController::class, 'index']);
        Route::get('funcionarios/{funcionario}', [FuncionarioController::class, 'show']);
        Route::get('bloqueios', [BloqueioAgendaController::class, 'index']);
        Route::get('clientes', [ClienteController::class, 'index']);
        Route::get('clientes/{cliente}', [ClienteController::class, 'show']);
        Route::get('produtos', [ProdutoController::class, 'index']);
        Route::get('comissoes', [ComissaoController::class, 'index']);

        // Dono: configurações da barbearia, preços, equipe e comissões
        Route::middleware('perfil:dono')->group(function () {
            Route::put('empresa', [EmpresaController::class, 'update']);

            // Mensalidade: faturas pagas, a próxima e o Pix
            Route::get('assinatura', [AssinaturaController::class, 'show']);
            Route::post('assinatura/faturas/{fatura}/pix', [AssinaturaController::class, 'pix']);
            Route::post('assinatura/faturas/{fatura}/informar-pagamento', [AssinaturaController::class, 'informarPagamento']);

            Route::post('empresa/imagens/{tipo}', [EmpresaController::class, 'enviarImagem']);
            Route::delete('empresa/imagens/{tipo}', [EmpresaController::class, 'removerImagem']);
            Route::get('whatsapp', [WhatsappController::class, 'show']);
            Route::put('whatsapp', [WhatsappController::class, 'update']);
            Route::get('whatsapp/mensagens', [WhatsappController::class, 'mensagens']);
            Route::get('galeria', [GaleriaController::class, 'index']);
            Route::post('galeria', [GaleriaController::class, 'store']);
            Route::put('galeria/{foto}', [GaleriaController::class, 'update']);
            Route::delete('galeria/{foto}', [GaleriaController::class, 'destroy']);

            Route::apiResource('servicos', ServicoController::class)->only(['store', 'update', 'destroy']);

            Route::apiResource('funcionarios', FuncionarioController::class)->only(['store', 'update', 'destroy']);
            Route::put('funcionarios/{funcionario}/servicos', [FuncionarioController::class, 'servicos']);
            Route::put('funcionarios/{funcionario}/horarios', [FuncionarioController::class, 'horarios']);
            Route::post('funcionarios/{funcionario}/foto', [FuncionarioController::class, 'foto']);
            Route::post('funcionarios/{funcionario}/acesso', [FuncionarioController::class, 'acesso']);

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
});
