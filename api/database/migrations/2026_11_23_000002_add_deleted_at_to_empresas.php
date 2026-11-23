<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Barbearia excluída pelo administrador: sai do ar, mas os dados ficam guardados
 * pelos prazos da Política de privacidade (ex.: 5 anos para pagamentos e comissões).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
