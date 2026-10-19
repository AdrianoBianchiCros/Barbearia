import { CurrencyPipe, DatePipe, NgTemplateOutlet } from '@angular/common';
import { Component, inject, OnInit, signal } from '@angular/core';
import { FormBuilder, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { AuthService } from '../../../core/auth.service';
import { podeEditar } from '../../../core/auth.guards';
import { AvisoService } from '../../../core/aviso.service';
import { errosPorCampo, mensagemDeErro } from '../../../core/erros';
import { EntradaProduto, FinanceiroService, Produto } from '../../../core/financeiro.service';

const hojeIso = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

/** Dias até a data (negativo = já passou). */
const diasAte = (data: string) =>
  Math.round((new Date(data + 'T12:00:00').getTime() - new Date(hojeIso() + 'T12:00:00').getTime()) / 86_400_000);

interface FormCompra {
  data_compra: string;
  fornecedor: string;
  numero_nota: string;
  quantidade: number | null;
  custo_unitario: number | null;
  validade: string;
}

const compraVazia = (): FormCompra => ({
  data_compra: hojeIso(), fornecedor: '', numero_nota: '', quantidade: null, custo_unitario: null, validade: '',
});

@Component({
  selector: 'app-produtos',
  imports: [ReactiveFormsModule, FormsModule, CurrencyPipe, DatePipe, NgTemplateOutlet],
  template: `
    <div class="cabecalho-pagina">
      <div>
        <h1>Produtos</h1>
        <p class="texto-suave">Itens vendidos na barbearia (pomadas, bebidas…), com compras, estoque e validade.</p>
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
            <tr><th>Produto</th><th>Preço</th><th>Custo</th><th>Estoque</th><th>Última compra</th><th>Validade</th>@if (editavel) {<th></th>}</tr>
          </thead>
          <tbody>
            @for (p of produtos(); track p.id) {
              <tr [class.inativo]="!p.ativo">
                <td>
                  <strong>{{ p.nome }}</strong>@if (!p.ativo) { <span class="etiqueta">Inativo</span>}
                  <div class="texto-suave pequeno">Comissão {{ +p.comissao_percentual }}%</div>
                </td>
                <td>{{ +p.preco | currency: 'BRL' }}</td>
                <td>{{ p.custo !== null ? (+p.custo | currency: 'BRL') : '—' }}</td>
                <td>
                  @if (p.estoque === null) {
                    <span class="texto-suave">não controla</span>
                  } @else {
                    <span [class.estoque-baixo]="p.estoque <= 3">{{ p.estoque }} un.</span>
                  }
                </td>
                <td>
                  @if (p.ultima_compra; as c) {
                    {{ c.data_compra + 'T12:00:00' | date: 'dd/MM/yyyy' }}
                    <div class="texto-suave pequeno">{{ c.fornecedor || 'Fornecedor não informado' }}{{ c.numero_nota ? ' · NF ' + c.numero_nota : '' }}</div>
                  } @else {
                    <span class="texto-suave">—</span>
                  }
                </td>
                <td>
                  @if (p.ultima_compra?.validade; as v) {
                    <span class="etiqueta" [class.etiqueta-erro]="diasAte(v) < 0" [class.etiqueta-alerta]="diasAte(v) >= 0 && diasAte(v) <= 30">
                      {{ v + 'T12:00:00' | date: 'dd/MM/yyyy' }}
                    </span>
                    @if (diasAte(v) < 0) {
                      <div class="pequeno negativo">vencido</div>
                    } @else if (diasAte(v) <= 30) {
                      <div class="pequeno texto-suave">vence em {{ diasAte(v) }} dia(s)</div>
                    }
                  } @else {
                    <span class="texto-suave">—</span>
                  }
                </td>
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
      <div class="painel-lateral painel-largo">
        <form [formGroup]="form" (ngSubmit)="salvar()" novalidate>
          <h2>{{ editando() ? editando()!.nome : 'Novo produto' }}</h2>
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
              <span>Custo (R$) <em>(atualiza com a compra)</em></span>
              <input type="number" formControlName="custo" min="0" step="0.01" />
            </label>
            <label class="campo">
              <span>{{ editando() ? 'Estoque atual (ajuste manual)' : 'Estoque inicial' }} <em>(vazio = não controla)</em></span>
              <input type="number" formControlName="estoque" step="1" />
            </label>
            <label class="campo">
              <span>Comissão do barbeiro (%)</span>
              <input type="number" formControlName="comissao_percentual" min="0" max="100" step="0.5" />
            </label>
          </div>
          <label class="campo-check"><input type="checkbox" formControlName="ativo" /> Produto ativo</label>

          @if (!editando()) {
            <section class="passo">
              <h3>Compra <small class="texto-suave">(opcional — soma ao estoque)</small></h3>
              <ng-container *ngTemplateOutlet="camposCompra" />
            </section>
          }

          <div class="rodape-painel">
            <button class="botao botao-secundario" type="button" (click)="aberto.set(false)">Fechar</button>
            <button class="botao botao-auto" type="submit" [disabled]="salvando()">{{ salvando() ? 'Salvando…' : 'Salvar produto' }}</button>
          </div>
        </form>

        @if (editando(); as p) {
          <section class="passo secao-compras">
            <h3>Registrar compra</h3>
            <p class="texto-suave pequeno">A quantidade comprada soma ao estoque atual ({{ p.estoque ?? 0 }} un.).</p>
            <ng-container *ngTemplateOutlet="camposCompra" />
            <div class="rodape-secao">
              <button class="botao botao-auto" type="button" [disabled]="!compra.quantidade || salvando()" (click)="lancarCompra(p)">
                Lançar compra
              </button>
            </div>
          </section>

          <section class="passo">
            <h3>Histórico de compras</h3>
            @if (!entradas().length) {
              <p class="texto-suave pequeno">Nenhuma compra registrada.</p>
            }
            <ul class="lista-simples">
              @for (e of entradas(); track e.id) {
                <li>
                  <div>
                    <strong>{{ e.data_compra + 'T12:00:00' | date: 'dd/MM/yyyy' }} · {{ e.quantidade }} un.</strong>
                    <span class="texto-suave pequeno">
                      {{ e.fornecedor || 'Fornecedor não informado' }}{{ e.numero_nota ? ' · NF ' + e.numero_nota : '' }}
                      {{ e.custo_unitario ? ' · ' + (+e.custo_unitario | currency: 'BRL') + '/un.' : '' }}
                      {{ e.validade ? ' · validade ' + (e.validade + 'T12:00:00' | date: 'dd/MM/yyyy') : '' }}
                    </span>
                  </div>
                  <button class="botao-link perigo" type="button" (click)="excluirCompra(p, e)" title="Excluir compra lançada por engano">Excluir</button>
                </li>
              }
            </ul>
          </section>
        }
      </div>
    }

    <ng-template #camposCompra>
      <div class="grade-2">
        <label class="campo">
          <span>Data da compra</span>
          <input type="date" name="data_compra" [(ngModel)]="compra.data_compra" [ngModelOptions]="{ standalone: true }" [max]="hoje" />
        </label>
        <label class="campo">
          <span>Quantidade comprada</span>
          <input type="number" min="1" step="1" [(ngModel)]="compra.quantidade" [ngModelOptions]="{ standalone: true }" />
        </label>
        <label class="campo">
          <span>Fornecedor</span>
          <input [(ngModel)]="compra.fornecedor" [ngModelOptions]="{ standalone: true }" placeholder="Ex.: Distribuidora Barba Fina" />
        </label>
        <label class="campo">
          <span>Número da nota</span>
          <input [(ngModel)]="compra.numero_nota" [ngModelOptions]="{ standalone: true }" />
        </label>
        <label class="campo">
          <span>Custo unitário (R$) <em>(opcional)</em></span>
          <input type="number" min="0" step="0.01" [(ngModel)]="compra.custo_unitario" [ngModelOptions]="{ standalone: true }" />
        </label>
        <label class="campo">
          <span>Validade <em>(opcional)</em></span>
          <input type="date" [(ngModel)]="compra.validade" [ngModelOptions]="{ standalone: true }" />
        </label>
      </div>
      @for (campo of camposErroCompra; track campo) {
        @if (errosApi()[campo] || errosApi()['entrada.' + campo]) {
          <small class="erro-campo">{{ errosApi()[campo] || errosApi()['entrada.' + campo] }}</small>
        }
      }
    </ng-template>
  `,
})
export class Produtos implements OnInit {
  private readonly api = inject(FinanceiroService);
  private readonly aviso = inject(AvisoService);
  protected readonly editavel = podeEditar(inject(AuthService), 'dono');
  protected readonly hoje = hojeIso();
  protected readonly diasAte = diasAte;
  protected readonly camposErroCompra = ['data_compra', 'quantidade', 'fornecedor', 'numero_nota', 'custo_unitario', 'validade'];

  protected readonly produtos = signal<Produto[]>([]);
  protected readonly entradas = signal<EntradaProduto[]>([]);
  protected readonly carregando = signal(true);
  protected readonly aberto = signal(false);
  protected readonly editando = signal<Produto | null>(null);
  protected readonly salvando = signal(false);
  protected readonly erro = signal('');
  protected readonly errosApi = signal<Record<string, string>>({});
  protected compra: FormCompra = compraVazia();

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
        const atual = this.editando();
        if (atual) this.editando.set(p.find((x) => x.id === atual.id) ?? null);
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
    this.compra = compraVazia();
    this.entradas.set([]);
    this.form.reset({
      nome: produto?.nome ?? '',
      preco: produto ? +produto.preco : null,
      custo: produto?.custo != null ? +produto.custo : null,
      estoque: produto?.estoque ?? null,
      comissao_percentual: produto ? +produto.comissao_percentual : 0,
      ativo: produto?.ativo ?? true,
    });
    if (produto) this.api.entradasProduto(produto.id).subscribe((e) => this.entradas.set(e));
    this.aberto.set(true);
  }

  salvar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.erro.set('Preencha nome e preço.');
      return;
    }
    this.salvando.set(true);
    this.erro.set('');
    this.errosApi.set({});
    const dados: Record<string, unknown> = { ...this.form.getRawValue() };
    // No cadastro, a compra informada vira a primeira entrada de estoque
    if (!this.editando() && this.compra.quantidade) dados['entrada'] = this.dadosCompra();

    this.api.salvarProduto(dados, this.editando()?.id).subscribe({
      next: () => {
        this.salvando.set(false);
        this.aberto.set(false);
        this.aviso.sucesso('Produto salvo.');
        this.carregar();
      },
      error: (e) => this.falhou(e),
    });
  }

  lancarCompra(produto: Produto): void {
    this.salvando.set(true);
    this.erro.set('');
    this.errosApi.set({});
    this.api.registrarEntrada(produto.id, this.dadosCompra()).subscribe({
      next: (r) => {
        this.salvando.set(false);
        this.entradas.update((l) => [r.entrada, ...l]);
        this.editando.set(r.produto);
        this.form.patchValue({ estoque: r.produto.estoque, custo: r.produto.custo != null ? +r.produto.custo : null });
        this.compra = compraVazia();
        this.aviso.sucesso(`Compra lançada. Estoque: ${r.produto.estoque} un.`);
        this.carregar();
      },
      error: (e) => this.falhou(e),
    });
  }

  excluirCompra(produto: Produto, entrada: EntradaProduto): void {
    if (!confirm(`Excluir a compra de ${entrada.quantidade} un.? A quantidade será retirada do estoque.`)) return;
    this.api.excluirEntrada(produto.id, entrada.id).subscribe({
      next: (p) => {
        this.entradas.update((l) => l.filter((x) => x.id !== entrada.id));
        this.editando.set(p);
        this.form.patchValue({ estoque: p.estoque });
        this.carregar();
      },
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
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

  private dadosCompra(): object {
    const c = this.compra;
    return {
      data_compra: c.data_compra,
      fornecedor: c.fornecedor || null,
      numero_nota: c.numero_nota || null,
      quantidade: c.quantidade,
      custo_unitario: c.custo_unitario === null || (c.custo_unitario as unknown) === '' ? null : c.custo_unitario,
      validade: c.validade || null,
    };
  }

  private falhou(e: unknown): void {
    this.salvando.set(false);
    const campos = errosPorCampo(e);
    this.errosApi.set(campos);
    this.erro.set(Object.values(campos)[0] ?? mensagemDeErro(e));
  }
}
