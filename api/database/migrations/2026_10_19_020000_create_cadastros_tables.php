<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cadastros da Etapa 1: funcionários, clientes, serviços e expediente.
     */
    public function up(): void
    {
        Schema::create('funcionarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('nome');
            $table->string('telefone', 20)->nullable();
            $table->string('email')->nullable();
            $table->enum('tipo_vinculo', ['clt', 'parceiro', 'autonomo'])->nullable();
            $table->decimal('comissao_percentual', 5, 2)->default(0);
            $table->boolean('atende_clientes')->default(true); // aparece na agenda como barbeiro
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome');
            $table->string('telefone', 20);
            $table->string('email')->nullable();
            $table->date('data_nascimento')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'telefone']);
        });

        Schema::create('consentimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('canal', 20)->default('whatsapp');
            $table->boolean('aceito');
            $table->string('origem', 30); // link_publico, recepcao, resposta_whatsapp
            $table->timestamp('registrado_em');
            $table->timestamps();

            $table->index(['cliente_id', 'canal']);
        });

        Schema::create('servicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->unsignedSmallInteger('duracao_minutos');
            $table->decimal('preco', 10, 2);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Serviços que cada barbeiro faz, com preço/duração próprios opcionais.
        Schema::create('funcionario_servicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->cascadeOnDelete();
            $table->foreignId('servico_id')->constrained('servicos')->cascadeOnDelete();
            $table->decimal('preco', 10, 2)->nullable();
            $table->unsignedSmallInteger('duracao_minutos')->nullable();
            $table->timestamps();

            $table->unique(['funcionario_id', 'servico_id']);
        });

        Schema::create('horarios_trabalho', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana'); // 0 = domingo ... 6 = sábado
            $table->time('inicio');
            $table->time('fim');
            $table->timestamps();

            $table->index(['funcionario_id', 'dia_semana']);
        });

        // Folgas, férias, intervalos e feriados. Sem funcionário = bloqueia a barbearia toda.
        Schema::create('bloqueios_agenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('funcionario_id')->nullable()->constrained('funcionarios')->cascadeOnDelete();
            $table->dateTime('inicio'); // UTC
            $table->dateTime('fim');    // UTC
            $table->string('motivo')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'inicio', 'fim']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloqueios_agenda');
        Schema::dropIfExists('horarios_trabalho');
        Schema::dropIfExists('funcionario_servicos');
        Schema::dropIfExists('servicos');
        Schema::dropIfExists('consentimentos');
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('funcionarios');
    }
};
