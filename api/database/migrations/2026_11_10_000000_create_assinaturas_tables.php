<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Venda do SaaS: administradores da plataforma (quem cadastra as barbearias),
 * contratações/renovações de cada barbearia e a data até quando o acesso está pago.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administradores', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->string('email', 180)->unique();
            $table->string('password');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Último dia de acesso pago. Vazio = sem prazo (barbearias anteriores à venda, como o piloto).
        Schema::table('empresas', function (Blueprint $table) {
            $table->date('acesso_ate')->nullable()->after('ativo');
        });

        Schema::create('assinaturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('administrador_id')->nullable()->constrained('administradores')->nullOnDelete();
            $table->string('periodo', 10); // teste, 1m, 3m, 6m, 12m
            $table->date('inicio');
            $table->date('fim');
            $table->string('forma_pagamento', 20); // pix, boleto, cartao, dinheiro, transferencia, cortesia
            $table->decimal('valor', 10, 2)->default(0);
            $table->string('contrato_path')->nullable();
            $table->string('contrato_nome', 180)->nullable();
            $table->string('observacao', 500)->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'fim']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assinaturas');
        Schema::table('empresas', fn (Blueprint $table) => $table->dropColumn('acesso_ate'));
        Schema::dropIfExists('administradores');
    }
};
