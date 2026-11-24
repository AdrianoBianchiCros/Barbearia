<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Taxa única de instalação e configuração, cobrada com a primeira mensalidade (vai para o contrato). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->decimal('taxa_instalacao', 10, 2)->nullable()->after('valor');
        });
    }

    public function down(): void
    {
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->dropColumn('taxa_instalacao');
        });
    }
};
