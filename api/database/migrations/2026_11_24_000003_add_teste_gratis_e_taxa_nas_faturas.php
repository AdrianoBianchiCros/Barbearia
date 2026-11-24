<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teste grátis no início do contrato (assinaturas.gratis) e taxa de instalação cobrada na
 * primeira fatura, junto com a mensalidade (faturas.taxa_instalacao).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->boolean('gratis')->default(false)->after('taxa_instalacao');
        });
        Schema::table('faturas', function (Blueprint $table) {
            $table->decimal('taxa_instalacao', 10, 2)->nullable()->after('valor');
        });
    }

    public function down(): void
    {
        Schema::table('faturas', fn (Blueprint $table) => $table->dropColumn('taxa_instalacao'));
        Schema::table('assinaturas', fn (Blueprint $table) => $table->dropColumn('gratis'));
    }
};
