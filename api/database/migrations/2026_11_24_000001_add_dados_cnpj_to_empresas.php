<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dados da Receita Federal da barbearia, preenchidos pelo administrador a partir do
 * CNPJ (consulta à API pública do OpenCNPJ) no cadastro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('razao_social', 160)->nullable()->after('nome');
            $table->string('situacao_cadastral', 30)->nullable()->after('documento');
            $table->date('data_abertura')->nullable()->after('situacao_cadastral');
            $table->string('natureza_juridica', 120)->nullable()->after('data_abertura');
            $table->string('cnae_principal', 200)->nullable()->after('natureza_juridica');
            $table->string('porte', 40)->nullable()->after('cnae_principal');
            $table->boolean('optante_simples')->nullable()->after('porte');
            $table->boolean('optante_mei')->nullable()->after('optante_simples');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'razao_social', 'situacao_cadastral', 'data_abertura', 'natureza_juridica',
                'cnae_principal', 'porte', 'optante_simples', 'optante_mei',
            ]);
        });
    }
};
