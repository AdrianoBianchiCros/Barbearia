<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\MensagemWhatsapp;
use App\Support\EmpresaAtual;
use App\Support\Telefone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Webhook da WhatsApp Cloud API: status de entrega das mensagens e respostas dos clientes.
 * A Meta pode reenviar o mesmo evento, então cada um é processado uma única vez.
 */
class WebhookWhatsappController extends Controller
{
    /** Respostas que confirmam o próximo horário. */
    private const CONFIRMAR = ['SIM', 'S', 'CONFIRMAR', 'CONFIRMO', 'CONFIRMADO', 'OK', '1'];

    /** Respostas que pedem para não receber mais mensagens. */
    private const PARAR = ['PARAR', 'SAIR', 'STOP', 'CANCELAR MENSAGENS', 'NAO QUERO RECEBER', 'DESCADASTRAR'];

    public function __construct(private EmpresaAtual $empresaAtual) {}

    /** Verificação feita pela Meta ao cadastrar o webhook. */
    public function verificar(Request $request): Response
    {
        $token = config('whatsapp.verify_token');
        $ok = $request->query('hub_mode') === 'subscribe'
            && filled($token)
            && hash_equals($token, (string) $request->query('hub_verify_token'));

        return $ok
            ? response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain')
            : response('Token inválido', 403);
    }

    public function receber(Request $request): JsonResponse
    {
        if (! $this->assinaturaValida($request)) {
            return response()->json(['message' => 'Assinatura inválida.'], 401);
        }

        foreach ($request->input('entry', []) as $entrada) {
            foreach ($entrada['changes'] ?? [] as $mudanca) {
                $valor = $mudanca['value'] ?? [];
                $empresa = Empresa::where('wa_phone_number_id', $valor['metadata']['phone_number_id'] ?? '-')->first();
                if (! $empresa) {
                    continue;
                }

                $this->empresaAtual->definir($empresa->id);
                foreach ($valor['statuses'] ?? [] as $status) {
                    $this->status($status);
                }
                foreach ($valor['messages'] ?? [] as $mensagem) {
                    $this->respostaDoCliente($mensagem);
                }
            }
        }
        $this->empresaAtual->definir(null);

        // A Meta só precisa de 200; erros de um evento não devem gerar reenvio de todos.
        return response()->json(['ok' => true]);
    }

    /** Confere o cabeçalho X-Hub-Signature-256 com o segredo do aplicativo. */
    private function assinaturaValida(Request $request): bool
    {
        $segredo = config('whatsapp.app_secret');
        if (blank($segredo)) {
            // Sem segredo configurado, só aceita fora de produção (testes locais).
            return ! app()->isProduction();
        }

        $esperado = 'sha256='.hash_hmac('sha256', $request->getContent(), $segredo);

        return hash_equals($esperado, (string) $request->header('X-Hub-Signature-256'));
    }

    /** Evento novo? (grava a chave; se já existia, é repetição da Meta) */
    private function primeiraVez(string $chave): bool
    {
        try {
            DB::table('webhook_eventos')->insert([
                'origem' => 'whatsapp',
                'chave' => Str::limit($chave, 191, ''),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    private function status(array $evento): void
    {
        $wamid = $evento['id'] ?? null;
        $status = match ($evento['status'] ?? null) {
            'sent' => 'enviado',
            'delivered' => 'entregue',
            'read' => 'lido',
            'failed' => 'falhou',
            default => null,
        };
        if (! $wamid || ! $status || ! $this->primeiraVez("status:{$wamid}:{$status}")) {
            return;
        }

        $mensagem = MensagemWhatsapp::where('wamid', $wamid)->first();
        if (! $mensagem) {
            return;
        }

        $quando = isset($evento['timestamp']) ? Carbon::createFromTimestamp((int) $evento['timestamp']) : now();
        $dados = match ($status) {
            'entregue' => ['entregue_em' => $quando],
            'lido' => ['lido_em' => $quando, 'entregue_em' => $mensagem->entregue_em ?? $quando],
            'falhou' => ['erro' => mb_substr($evento['errors'][0]['title'] ?? $evento['errors'][0]['message'] ?? 'Falha na entrega', 0, 500)],
            default => [],
        };

        // O status só avança (um "entregue" atrasado não desfaz o "lido").
        $atual = MensagemWhatsapp::ORDEM_STATUS[$mensagem->status] ?? -1;
        if ($status === 'falhou' || (MensagemWhatsapp::ORDEM_STATUS[$status] ?? -1) > $atual) {
            $dados['status'] = $status;
        }

        $mensagem->update($dados);
    }

    private function respostaDoCliente(array $evento): void
    {
        if (empty($evento['id']) || ! $this->primeiraVez("msg:{$evento['id']}")) {
            return;
        }

        $texto = trim($evento['text']['body'] ?? $evento['button']['text'] ?? $evento['interactive']['button_reply']['title'] ?? '');
        $cliente = $this->clientePeloWhatsapp((string) ($evento['from'] ?? ''));
        if (! $cliente || $texto === '') {
            return;
        }

        MensagemWhatsapp::where('cliente_id', $cliente->id)->latest('id')->first()
            ?->update(['resposta' => mb_substr($texto, 0, 500), 'respondido_em' => now()]);

        $comando = Str::upper(Str::ascii($texto));
        $comando = trim(preg_replace('/[^A-Z0-9 ]/', '', $comando));

        if (in_array($comando, self::PARAR, true)) {
            $cliente->consentimentos()->create([
                'canal' => 'whatsapp',
                'aceito' => false,
                'origem' => 'resposta_whatsapp',
                'registrado_em' => now(),
            ]);

            return;
        }

        if (in_array($comando, self::CONFIRMAR, true)) {
            Agendamento::where('cliente_id', $cliente->id)
                ->where('status', 'agendado')
                ->where('inicio', '>', now())
                ->orderBy('inicio')
                ->first()
                ?->update(['status' => 'confirmado', 'confirmado_em' => now()]);
        }
    }

    /**
     * O WhatsApp manda o número com DDI (55…) e, em celulares antigos, sem o 9 inicial.
     * Procura o cliente nas duas formas.
     */
    private function clientePeloWhatsapp(string $numero): ?Cliente
    {
        $telefone = Telefone::normalizar($numero);
        if (! $telefone) {
            return null;
        }

        $formas = [$telefone];
        if (strlen($telefone) === 10) {
            $formas[] = substr($telefone, 0, 2).'9'.substr($telefone, 2);
        }

        return Cliente::whereIn('telefone', $formas)->first();
    }
}
