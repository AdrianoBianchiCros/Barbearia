<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->restrictOnDelete();
            $table->dateTime('inicio'); // UTC
            $table->dateTime('fim');    // UTC
            $table->enum('status', ['agendado', 'confirmado', 'em_atendimento', 'concluido', 'cancelado', 'faltou'])
                ->default('agendado');
            $table->enum('origem', ['recepcao', 'link_publico', 'whatsapp'])->default('recepcao');
            $table->decimal('valor_total', 10, 2)->default(0);
            $table->text('observacoes')->nullable();
            $table->timestamp('cancelado_em')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'inicio']);
            $table->index(['funcionario_id', 'inicio', 'fim']);
        });

        // Preço e duração são copiados do serviço no momento do agendamento.
        Schema::create('agendamento_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('agendamento_id')->constrained('agendamentos')->cascadeOnDelete();
            $table->foreignId('servico_id')->constrained('servicos')->restrictOnDelete();
            $table->string('nome');
            $table->decimal('preco', 10, 2);
            $table->unsignedSmallInteger('duracao_minutos');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agendamento_itens');
        Schema::dropIfExists('agendamentos');
    }
};
