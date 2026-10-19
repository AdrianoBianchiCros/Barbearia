<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compras (entradas de estoque) dos produtos: cada entrada soma ao estoque atual.
     */
    public function up(): void
    {
        Schema::create('produto_entradas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->date('data_compra');
            $table->string('fornecedor', 120)->nullable();
            $table->string('numero_nota', 60)->nullable();
            $table->unsignedInteger('quantidade');
            $table->decimal('custo_unitario', 10, 2)->nullable();
            $table->date('validade')->nullable();
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->timestamps();

            $table->index(['produto_id', 'data_compra']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_entradas');
    }
};
