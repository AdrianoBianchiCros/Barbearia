<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fatura da mensalidade que o dono paga pelo painel (Pix com QR Code).
 * Ao confirmar o recebimento, o administrador gera a contratação em "assinaturas".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('periodo', 10);
            $table->decimal('valor', 10, 2);
            $table->date('vencimento');
            $table->date('inicio'); // período que a fatura paga
            $table->date('fim');
            $table->string('txid', 25)->unique(); // identificador que vai no Pix
            $table->string('status', 15)->default('aberta'); // aberta, informada (dono disse que pagou), paga, cancelada
            $table->timestamp('informada_em')->nullable();
            $table->timestamp('paga_em')->nullable();
            $table->foreignId('assinatura_id')->nullable()->constrained('assinaturas')->nullOnDelete();
            $table->foreignId('administrador_id')->nullable()->constrained('administradores')->nullOnDelete();
            $table->timestamps();

            $table->index(['empresa_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faturas');
    }
};
