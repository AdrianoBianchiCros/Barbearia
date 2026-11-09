<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro dos aceites (LGPD): quem aceitou qual documento, em que versão, quando e de onde.
 * Guarda tanto o dono que cria a conta (usuario_id) quanto o cliente que agenda pela página (cliente_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aceites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('documento', 20); // termos, privacidade, maioridade
            $table->string('versao', 20);
            $table->string('origem', 30); // cadastro_painel, link_publico
            $table->string('ip', 45)->nullable();
            $table->string('navegador', 255)->nullable();
            $table->timestamp('aceito_em');
            $table->timestamps();

            $table->index(['cliente_id', 'documento']);
            $table->index(['usuario_id', 'documento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aceites');
    }
};
