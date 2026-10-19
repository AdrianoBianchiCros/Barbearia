<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos usados na página pública da barbearia (landing page com agendamento).
     */
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('slogan', 160)->nullable()->after('nome');
            $table->text('descricao')->nullable()->after('slogan');
            $table->string('whatsapp', 20)->nullable()->after('telefone');
            $table->string('instagram', 60)->nullable()->after('email');
            $table->string('cep', 9)->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 80)->nullable();
            $table->string('bairro', 80)->nullable();
            $table->string('cidade', 80)->nullable();
            $table->char('uf', 2)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('capa_path')->nullable();
            $table->string('cor_primaria', 7)->default('#1f3a5f');
        });

        Schema::table('funcionarios', function (Blueprint $table) {
            $table->string('foto_path')->nullable()->after('email');
            $table->string('especialidades', 160)->nullable()->after('foto_path');
        });

        Schema::create('galeria_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('caminho');
            $table->string('legenda', 120)->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galeria_fotos');

        Schema::table('funcionarios', function (Blueprint $table) {
            $table->dropColumn(['foto_path', 'especialidades']);
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'slogan', 'descricao', 'whatsapp', 'instagram', 'cep', 'logradouro', 'numero',
                'complemento', 'bairro', 'cidade', 'uf', 'logo_path', 'capa_path', 'cor_primaria',
            ]);
        });
    }
};
