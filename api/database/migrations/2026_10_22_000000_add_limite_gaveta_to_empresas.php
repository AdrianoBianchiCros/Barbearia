<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Valor máximo de dinheiro na gaveta do caixa. Acima dele, o sistema pede uma sangria.
     */
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->decimal('limite_gaveta', 10, 2)->nullable()->after('cor_primaria');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('limite_gaveta');
        });
    }
};
