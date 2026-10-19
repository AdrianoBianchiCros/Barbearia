<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Financeiro: produtos, comandas (o que foi consumido e pago), caixa do dia,
     * pagamentos e lançamentos (despesas, vales e repasses de comissão).
     */
    public function up(): void
    {
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome');
            $table->decimal('preco', 10, 2);
            $table->decimal('custo', 10, 2)->nullable();
            $table->integer('estoque')->nullable(); // null = não controla estoque
            $table->decimal('comissao_percentual', 5, 2)->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('caixas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('aberto_por')->constrained('usuarios');
            $table->timestamp('aberto_em');
            $table->decimal('valor_inicial', 10, 2)->default(0);
            $table->foreignId('fechado_por')->nullable()->constrained('usuarios');
            $table->timestamp('fechado_em')->nullable();
            $table->decimal('valor_contado', 10, 2)->nullable(); // dinheiro conferido no fechamento
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'fechado_em']);
        });

        // Suprimento (entrada de troco) e sangria (retirada de dinheiro do caixa)
        Schema::create('caixa_movimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('caixa_id')->constrained('caixas')->cascadeOnDelete();
            $table->enum('tipo', ['suprimento', 'sangria']);
            $table->decimal('valor', 10, 2);
            $table->string('descricao')->nullable();
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->timestamps();
        });

        Schema::create('comandas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('agendamento_id')->nullable()->unique()->constrained('agendamentos')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('funcionario_id')->nullable()->constrained('funcionarios')->nullOnDelete();
            $table->enum('status', ['aberta', 'paga', 'cancelada'])->default('aberta');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('desconto', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->foreignId('caixa_id')->nullable()->constrained('caixas')->nullOnDelete();
            $table->timestamp('pago_em')->nullable();
            $table->foreignId('fechado_por')->nullable()->constrained('usuarios');
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'status']);
            $table->index(['empresa_id', 'pago_em']);
        });

        // Comissão gravada no fechamento (percentual da época), para não mudar o passado
        Schema::create('comanda_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('comanda_id')->constrained('comandas')->cascadeOnDelete();
            $table->enum('tipo', ['servico', 'produto']);
            $table->foreignId('servico_id')->nullable()->constrained('servicos')->nullOnDelete();
            $table->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
            $table->foreignId('funcionario_id')->nullable()->constrained('funcionarios')->nullOnDelete();
            $table->string('descricao');
            $table->unsignedSmallInteger('quantidade')->default(1);
            $table->decimal('preco_unitario', 10, 2);
            $table->decimal('total', 10, 2);
            $table->decimal('comissao_percentual', 5, 2)->default(0);
            $table->decimal('comissao_valor', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('comanda_id')->constrained('comandas')->cascadeOnDelete();
            $table->foreignId('caixa_id')->constrained('caixas');
            $table->enum('forma', ['dinheiro', 'pix', 'debito', 'credito']);
            $table->decimal('valor', 10, 2);
            $table->timestamps();

            $table->index(['caixa_id', 'forma']);
        });

        // Despesas (contas a pagar), vales/adiantamentos e repasses de comissão
        Schema::create('lancamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->enum('tipo', ['despesa', 'vale', 'repasse_comissao']);
            $table->string('categoria', 40)->nullable();
            $table->string('descricao');
            $table->decimal('valor', 10, 2);
            $table->foreignId('funcionario_id')->nullable()->constrained('funcionarios')->nullOnDelete();
            $table->date('vencimento')->nullable();
            $table->timestamp('pago_em')->nullable();
            $table->foreignId('caixa_id')->nullable()->constrained('caixas')->nullOnDelete(); // pago com dinheiro do caixa
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'tipo', 'vencimento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lancamentos');
        Schema::dropIfExists('pagamentos');
        Schema::dropIfExists('comanda_itens');
        Schema::dropIfExists('comandas');
        Schema::dropIfExists('caixa_movimentos');
        Schema::dropIfExists('caixas');
        Schema::dropIfExists('produtos');
    }
};
