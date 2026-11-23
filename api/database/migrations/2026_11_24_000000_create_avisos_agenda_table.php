<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos do sino da agenda: cancelamentos e remarcações, venham de onde vierem.
 * Cada usuário guarda até qual aviso já viu (usuarios.avisos_vistos_ate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avisos_agenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('agendamento_id')->constrained('agendamentos')->cascadeOnDelete();
            $table->foreignId('funcionario_id')->nullable()->constrained('funcionarios')->nullOnDelete();
            // Remarcação para outro barbeiro: o anterior também é avisado.
            $table->foreignId('funcionario_anterior_id')->nullable()->constrained('funcionarios')->nullOnDelete();
            $table->string('tipo', 15); // cancelamento, remarcacao
            $table->string('origem', 15); // cliente (link), painel
            $table->foreignId('autor_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('cliente_nome', 120);
            $table->timestamp('inicio');
            $table->timestamp('inicio_anterior')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'id']);
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->unsignedBigInteger('avisos_vistos_ate')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('avisos_vistos_ate');
        });
        Schema::dropIfExists('avisos_agenda');
    }
};
