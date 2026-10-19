<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * WhatsApp: confirmação e lembretes dos agendamentos, link para o cliente
     * confirmar/remarcar/cancelar e configuração por barbearia.
     */
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('wa_ativo')->default(false);
            $table->boolean('wa_lembrete_24h')->default(true);
            $table->boolean('wa_lembrete_2h')->default(false);
            $table->string('wa_phone_number_id', 40)->nullable()->unique(); // número da barbearia na Cloud API
            $table->text('wa_token')->nullable(); // criptografado
            $table->unsignedSmallInteger('cancelamento_antecedencia_horas')->default(2);
        });

        Schema::table('agendamentos', function (Blueprint $table) {
            $table->string('token', 40)->nullable()->unique()->after('id'); // link do cliente: /a/{token}
            $table->timestamp('confirmado_em')->nullable()->after('cancelado_em');
        });

        // Agendamentos que já existiam ganham o link também.
        DB::table('agendamentos')->whereNull('token')->orderBy('id')->each(function ($a) {
            DB::table('agendamentos')->where('id', $a->id)->update(['token' => Str::random(32)]);
        });

        Schema::create('mensagens_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('agendamento_id')->nullable()->constrained('agendamentos')->cascadeOnDelete();
            $table->string('tipo', 30); // confirmacao, lembrete_24h, lembrete_2h, remarcacao, cancelamento
            $table->dateTime('referencia_inicio')->nullable(); // início do agendamento quando a mensagem foi criada
            $table->string('telefone', 20);
            $table->string('template', 60);
            $table->json('parametros');
            $table->enum('status', ['pendente', 'enviado', 'entregue', 'lido', 'falhou', 'ignorado'])->default('pendente');
            $table->string('wamid', 120)->nullable()->unique(); // id da mensagem na Meta
            $table->unsignedTinyInteger('tentativas')->default(0);
            $table->string('erro', 500)->nullable();
            $table->string('resposta', 500)->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->timestamp('entregue_em')->nullable();
            $table->timestamp('lido_em')->nullable();
            $table->timestamp('respondido_em')->nullable();
            $table->timestamps();

            // A mesma mensagem não é criada duas vezes para o mesmo horário do agendamento.
            $table->unique(['agendamento_id', 'tipo', 'referencia_inicio']);
            $table->index(['empresa_id', 'created_at']);
        });

        // Eventos de webhook já processados (a Meta pode reenviar o mesmo evento).
        Schema::create('webhook_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('origem', 30);
            $table->string('chave', 191);
            $table->timestamps();

            $table->unique(['origem', 'chave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_eventos');
        Schema::dropIfExists('mensagens_whatsapp');

        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->dropColumn(['token', 'confirmado_em']);
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropUnique(['wa_phone_number_id']);
            $table->dropColumn(['wa_ativo', 'wa_lembrete_24h', 'wa_lembrete_2h', 'wa_phone_number_id', 'wa_token', 'cancelamento_antecedencia_horas']);
        });
    }
};
