import { CurrencyPipe } from '@angular/common';
import { Component, inject, OnInit, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { AuthService } from '../../../core/auth.service';
import { podeEditar } from '../../../core/auth.guards';
import { AvisoService } from '../../../core/aviso.service';
import { errosPorCampo, mensagemDeErro } from '../../../core/erros';
import { FinanceiroService, Produto } from '../../../core/financeiro.service';

@Component({
  selector: 'app-produtos',
  imports: [ReactiveFormsModule, CurrencyPipe],
  template: `
    <div class="cabecalho-pagina">
      <div>
        <h1>Produtos</h1>
        <p class="texto-suave">Itens vendidos na barbearia (pomadas, bebidas…), com estoque e comissão.</p>
      </div>
      @if (editavel) {
        <button class="botao botao-auto" type="button" (click)="abrir()">+ Novo produto</button>
      }
    </div>

    @if (carregando()) {
      <p class="texto-suave">Carregando…</p>
    } @else if (produtos().length === 0) {
      <div class="vazio">Nenhum produto cadastrado ainda.</div>
    } @else {
      <div class="tabela-container">
        <table class="tabela">
          <thead>
            <tr><th>Produto</th><th>Preço</th><th>Custo</th><th>Estoque</th><th>Comissão</th>@if (editavel) {<th></th>}</tr>
          </thead>
          <tbody>
            @for (p of produtos(); track p.id) {
              <tr [class.inativo]="!p.ativo">
                <td><strong>{{ p.nome }}</strong>@if (!p.ativo) {<span class="etiqueta">Inativo</span>}</td>
                <td>{{ +p.preco | currency: 'BRL' }}</td>
                <td>{{ p.custo !== null ? (+p.custo | currency: 'BRL') : '—' }}</td>
                <td>
                  @if (p.estoque === null) {
                    <span class="texto-suave">não controla</span>
                  } @else {
                    <span [class.estoque-baixo]="p.estoque <= 3">{{ p.estoque }} un.</span>
                  }
                </td>
                <td>{{ +p.comissao_percentual }}%</td>
                @if (editavel) {
                  <td class="acoes">
                    <button class="botao-link" type="button" (click)="abrir(p)">Editar</button>
                    <button class="botao-link perigo" type="button" (click)="excluir(p)">Excluir</button>
                  </td>
                }
              </tr>
            }
          </tbody>
        </table>
      </div>
    }

    @if (aberto()) {
      <div class="fundo-modal" (click)="aberto.set(false)"></div>
      <form class="painel-lateral" [formGroup]="form" (ngSubmit)="salvar()" novalidate>
        <h2>{{ editando() ? 'Editar produto' : 'Novo produto' }}</h2>
        @if (erro()) {<div class="alerta">{{ erro() }}</div>}

        <label class="campo">
          <span>Nome</span>
          <input formControlName="nome" placeholder="Ex.: Pomada modeladora" />
          @if (errosApi()['nome']) {<small class="erro-campo">{{ errosApi()['nome'] }}</small>}
        </label>
        <div class="grade-2">
          <label class="campo">
            <span>Preço de venda (R$)</span>
            <input type="number" formControlName="preco" min="0" step="0.01" />
            @if (errosApi()['preco']) {<small class="erro-campo">{{ errosApi()['preco'] }}</small>}
          </label>
          <label class="campo">
            <span>Custo (R$) <em>(opcional)</em></span>
            <input type="number" formControlName="custo" min="0" step="0.01" />
          </label>
          <label class="campo">
            <span>Estoque atual <em>(vazio = não controla)</em></span>
            <input type="number" formControlName="estoque" step="1" />
          </label>
          <label class="campo">
            <span>Comissão do barbeiro (%)</span>
            <input type="number" formControlName="comissao_percentual" min="0" max="100" step="0.5" />
          </label>
        </div>
        <label class="campo-check"><input type="checkbox" formControlName="ativo" /> Produto ativo</label>

        <div class="rodape-painel">
          <button class="botao botao-secundario" type="button" (click)="aberto.set(false)">Cancelar</button>
          <button class="botao botao-auto" type="submit" [disabled]="salvando()">{{ salvando() ? 'Salvando…' : 'Salvar' }}</button>
        </div>
      </form>
    }
  `,
})
export class Produtos implements OnInit {
  private readonly api = inject(FinanceiroService);
  private readonly aviso = inject(AvisoService);
  protected readonly editavel = podeEditar(inject(AuthService), 'dono');

  protected readonly produtos = signal<Produto[]>([]);
  protected readonly carregando = signal(true);
  protected readonly aberto = signal(false);
  protected readonly editando = signal<Produto | null>(null);
  protected readonly salvando = signal(false);
  protected readonly erro = signal('');
  protected readonly errosApi = signal<Record<string, string>>({});

  protected readonly form = inject(FormBuilder).group({
    nome: ['', [Validators.required, Validators.maxLength(120)]],
    preco: [null as number | null, [Validators.required, Validators.min(0)]],
    custo: [null as number | null],
    estoque: [null as number | null],
    comissao_percentual: [0 as number | null],
    ativo: [true],
  });

  ngOnInit(): void {
    this.carregar();
  }

  carregar(): void {
    this.api.listarProdutos().subscribe({
      next: (p) => {
        this.produtos.set(p);
        this.carregando.set(false);
      },
      error: (e) => {
        this.aviso.erro(mensagemDeErro(e));
        this.carregando.set(false);
      },
    });
  }

  abrir(produto?: Produto): void {
    this.editando.set(produto ?? null);
    this.erro.set('');
    this.errosApi.set({});
    this.form.reset({
      nome: produto?.nome ?? '',
      preco: produto ? +produto.preco : null,
      custo: produto?.custo != null ? +produto.custo : null,
      estoque: produto?.estoque ?? null,
      comissao_percentual: produto ? +produto.comissao_percentual : 0,
      ativo: produto?.ativo ?? true,
    });
    this.aberto.set(true);
  }

  salvar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.erro.set('Preencha nome e preço.');
      return;
    }
    this.salvando.set(true);
    this.api.salvarProduto(this.form.getRawValue(), this.editando()?.id).subscribe({
      next: () => {
        this.salvando.set(false);
        this.aberto.set(false);
        this.aviso.sucesso('Produto salvo.');
        this.carregar();
      },
      error: (e) => {
        this.salvando.set(false);
        this.errosApi.set(errosPorCampo(e));
        this.erro.set(mensagemDeErro(e));
      },
    });
  }

  excluir(produto: Produto): void {
    if (!confirm(`Excluir o produto "${produto.nome}"?`)) return;
    this.api.excluirProduto(produto.id).subscribe({
      next: () => {
        this.aviso.sucesso('Produto excluído.');
        this.carregar();
      },
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }
}
