<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lote do fabricante em cada compra, para saber qual lote vence.
     */
    public function up(): void
    {
        Schema::table('produto_entradas', function (Blueprint $table) {
            $table->string('lote', 60)->nullable()->after('numero_nota');
        });
    }

    public function down(): void
    {
        Schema::table('produto_entradas', function (Blueprint $table) {
            $table->dropColumn('lote');
        });
    }
};
