import { CurrencyPipe, DatePipe } from '@angular/common';
import { Component, computed, inject, input, OnInit, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { forkJoin } from 'rxjs';
import { AuthService } from '../../core/auth.service';
import { podeEditar } from '../../core/auth.guards';
import { CadastrosService } from '../../core/cadastros.service';
import { errosPorCampo, mensagemDeErro } from '../../core/erros';
import { Comanda, FinanceiroService, FormaPagamento, FORMAS, ItemComanda, Produto, ROTULO_FORMA } from '../../core/financeiro.service';
import { Funcionario, Servico } from '../../core/modelos';

interface LinhaPagamento {
  forma: FormaPagamento;
  valor: number;
}

/** Origem da comanda: um atendimento da agenda, uma comanda existente ou venda avulsa. */
export interface AbrirComanda {
  agendamentoId?: number;
  comandaId?: number;
  avulsa?: boolean;
}

const arred = (v: number) => Math.round(v * 100) / 100;

/** Fechamento da comanda: itens, desconto e formas de pagamento. */
@Component({
  selector: 'app-painel-comanda',
  imports: [FormsModule, CurrencyPipe, DatePipe],
  template: `
    <div class="fundo-modal" (click)="fechar.emit()"></div>
    <aside class="painel-lateral painel-largo painel-comanda" role="dialog" aria-label="Comanda">
      @if (comanda(); as c) {
        <div class="comanda-cabecalho">
          <div>
            <h2>{{ c.cliente?.nome ?? 'Venda avulsa' }}</h2>
            <p class="texto-suave">
              Comanda #{{ c.id }}
              @if (c.barbeiro) { · {{ c.barbeiro.nome }} }
              @if (c.horario) { · {{ c.horario | date: "dd/MM 'às' HH:mm" }} }
            </p>
          </div>
          <span class="etiqueta-status" [class.st-confirmado]="c.status === 'paga'" [class.st-em_atendimento]="c.status === 'aberta'">
            {{ c.status === 'paga' ? 'Paga' : c.status === 'cancelada' ? 'Cancelada' : 'Aguardando pagamento' }}
          </span>
        </div>

        @if (erro()) {<div class="alerta">{{ erro() }}</div>}

        @if (c.status === 'aberta' && caixaAberto() === false) {
          <div class="aviso-caixa">
            <strong>O caixa está fechado.</strong> Abra o caixa para receber.
            <div class="linha-abrir-caixa">
              <label>Troco inicial (R$) <input type="number" min="0" step="0.01" [(ngModel)]="valorInicial" /></label>
              <button class="botao botao-auto" type="button" (click)="abrirCaixa()">Abrir caixa</button>
            </div>
          </div>
        }

        <!-- Itens -->
        <section class="passo">
          <h3>Itens</h3>
          <div class="itens-comanda">
            @for (item of itens(); track $index) {
              <div class="item-comanda">
                <div class="item-descricao">
                  <strong>{{ item.descricao }}</strong>
                  <select [(ngModel)]="item.funcionario_id" [disabled]="!editavel()" aria-label="Barbeiro do item">
                    <option [ngValue]="null">{{ item.tipo === 'produto' ? 'Sem comissão' : 'Barbeiro' }}</option>
                    @for (f of barbeiros(); track f.id) {<option [ngValue]="f.id">{{ f.nome }}</option>}
                  </select>
                </div>
                <input type="number" min="1" step="1" class="item-qtd" [(ngModel)]="item.quantidade" [disabled]="!editavel()" (ngModelChange)="tocar()" aria-label="Quantidade" />
                <input type="number" min="0" step="0.01" class="item-preco" [(ngModel)]="item.preco_unitario" [disabled]="!editavel()" (ngModelChange)="tocar()" aria-label="Preço unitário" />
                <span class="item-total">{{ +item.preco_unitario * item.quantidade | currency: 'BRL' }}</span>
                @if (editavel()) {
                  <button class="botao-link perigo" type="button" (click)="removerItem($index)" aria-label="Remover item">✕</button>
                }
              </div>
            } @empty {
              <p class="texto-suave pequeno">Nenhum item. Adicione um serviço ou produto.</p>
            }
          </div>

          @if (editavel()) {
            <div class="adicionar-itens">
              <select [ngModel]="null" (ngModelChange)="adicionarServico($event)" aria-label="Adicionar serviço">
                <option [ngValue]="null">+ Serviço…</option>
                @for (s of servicos(); track s.id) {<option [ngValue]="s.id">{{ s.nome }} — {{ +s.preco | currency: 'BRL' }}</option>}
              </select>
              <select [ngModel]="null" (ngModelChange)="adicionarProduto($event)" aria-label="Adicionar produto">
                <option [ngValue]="null">+ Produto…</option>
                @for (p of produtos(); track p.id) {
                  <option [ngValue]="p.id">{{ p.nome }} — {{ +p.preco | currency: 'BRL' }}{{ p.estoque !== null ? ' (' + p.estoque + ' em estoque)' : '' }}</option>
                }
              </select>
            </div>
          }
        </section>

        <!-- Totais -->
        <section class="passo totais-comanda">
          <div><span>Subtotal</span><span>{{ subtotal() | currency: 'BRL' }}</span></div>
          <div>
            <span>Desconto</span>
            @if (editavel()) {
              <input type="number" min="0" step="0.01" [(ngModel)]="desconto" (ngModelChange)="tocar()" aria-label="Desconto" />
            } @else {
              <span>{{ +c.desconto | currency: 'BRL' }}</span>
            }
          </div>
          <div class="total-geral"><span>Total</span><span>{{ total() | currency: 'BRL' }}</span></div>
        </section>

        <!-- Pagamento -->
        @if (c.status === 'aberta') {
          <section class="passo">
            <h3>Pagamento</h3>
            <div class="chips">
              @for (f of formas; track f.id) {
                <button type="button" class="chip" (click)="adicionarPagamento(f.id)">+ {{ f.rotulo }}</button>
              }
            </div>
            @for (p of pagamentos(); track $index) {
              <div class="linha-pagamento">
                <strong>{{ rotuloForma[p.forma] }}</strong>
                <input type="number" min="0" step="0.01" [(ngModel)]="p.valor" (ngModelChange)="tocar()" aria-label="Valor" />
                <button class="botao-link perigo" type="button" (click)="removerPagamento($index)" aria-label="Remover pagamento">✕</button>
              </div>
            }
            @if (temDinheiro()) {
              <div class="linha-troco">
                <label>Dinheiro recebido <input type="number" min="0" step="0.01" [(ngModel)]="dinheiroRecebido" (ngModelChange)="tocar()" /></label>
                @if (troco() > 0) {<span>Troco: <strong>{{ troco() | currency: 'BRL' }}</strong></span>}
              </div>
            }
            <p class="restante" [class.ok]="restante() === 0" [class.excesso]="restante() < 0">
              @if (restante() === 0) { Pagamento completo. }
              @else if (restante() > 0) { Falta receber {{ restante() | currency: 'BRL' }} }
              @else { Pagamentos passam do total em {{ -restante() | currency: 'BRL' }} }
            </p>
          </section>
        } @else if (c.pagamentos.length) {
          <section class="passo">
            <h3>Pagamento</h3>
            @for (p of c.pagamentos; track p.id) {
              <div class="linha-pagamento"><strong>{{ rotuloForma[p.forma] }}</strong><span>{{ +p.valor | currency: 'BRL' }}</span></div>
            }
            @if (c.pago_em) {<p class="texto-suave pequeno">Pago em {{ c.pago_em | date: "dd/MM/yyyy 'às' HH:mm" }}</p>}
          </section>
        }

        <div class="resumo-agendamento">
          <div>
            @if (c.status === 'aberta' && podeCancelar()) {
              <button class="botao-link perigo" type="button" (click)="cancelar()">Cancelar comanda</button>
            }
            @if (c.status === 'paga' && dono) {
              <button class="botao-link perigo" type="button" (click)="estornar()">Estornar pagamento</button>
            }
          </div>
          <div class="resumo-botoes">
            <button class="botao botao-secundario botao-auto" type="button" (click)="fechar.emit()">Fechar</button>
            @if (c.status === 'aberta') {
              <button class="botao botao-auto" type="button" [disabled]="!podeConfirmar() || salvando()" (click)="confirmar()">
                {{ salvando() ? 'Registrando…' : 'Receber ' + (total() | currency: 'BRL') }}
              </button>
            }
          </div>
        </div>
      } @else {
        <p class="texto-suave">Carregando comanda…</p>
      }
    </aside>
  `,
})
export class PainelComanda implements OnInit {
  readonly origem = input.required<AbrirComanda>();
  readonly pago = output<Comanda>();
  readonly fechar = output<void>();

  private readonly api = inject(FinanceiroService);
  private readonly cadastros = inject(CadastrosService);
  private readonly auth = inject(AuthService);
  protected readonly dono = podeEditar(this.auth, 'dono');
  protected readonly formas = FORMAS;
  protected readonly rotuloForma = ROTULO_FORMA;

  protected readonly comanda = signal<Comanda | null>(null);
  protected readonly itens = signal<ItemComanda[]>([]);
  protected readonly pagamentos = signal<LinhaPagamento[]>([]);
  protected readonly servicos = signal<Servico[]>([]);
  protected readonly produtos = signal<Produto[]>([]);
  protected readonly barbeiros = signal<Funcionario[]>([]);
  protected readonly caixaAberto = signal<boolean | null>(null);
  protected readonly salvando = signal(false);
  protected readonly erro = signal('');
  protected desconto = 0;
  protected dinheiroRecebido: number | null = null;
  protected valorInicial = 100;
  /** Contador para recalcular os totais após edições nos campos (ngModel). */
  private readonly versao = signal(0);

  protected readonly editavel = computed(() => this.comanda()?.status === 'aberta');
  protected readonly subtotal = computed(() => {
    this.versao();
    return arred(this.itens().reduce((s, i) => s + (+i.preco_unitario || 0) * (+i.quantidade || 0), 0));
  });
  protected readonly total = computed(() => {
    this.versao();
    return Math.max(arred(this.subtotal() - (+this.desconto || 0)), 0);
  });
  protected readonly pagoInformado = computed(() => {
    this.versao();
    return arred(this.pagamentos().reduce((s, p) => s + (+p.valor || 0), 0));
  });
  protected readonly restante = computed(() => arred(this.total() - this.pagoInformado()));
  protected readonly temDinheiro = computed(() => this.pagamentos().some((p) => p.forma === 'dinheiro'));
  protected readonly troco = computed(() => {
    this.versao();
    const emDinheiro = this.pagamentos().filter((p) => p.forma === 'dinheiro').reduce((s, p) => s + (+p.valor || 0), 0);
    return this.dinheiroRecebido ? Math.max(arred(this.dinheiroRecebido - emDinheiro), 0) : 0;
  });
  protected readonly podeConfirmar = computed(
    () => this.caixaAberto() === true && this.itens().length > 0 && this.pagamentos().length > 0 && this.restante() === 0,
  );
  protected readonly podeCancelar = computed(() => this.dono || !this.comanda()?.agendamento_id);

  ngOnInit(): void {
    const origem = this.origem();
    const comanda$ = origem.agendamentoId
      ? this.api.comandaDoAgendamento(origem.agendamentoId)
      : origem.comandaId
        ? this.api.comanda(origem.comandaId)
        : this.api.novaVenda();

    forkJoin({
      comanda: comanda$,
      servicos: this.cadastros.listarServicos(),
      produtos: this.api.listarProdutos(),
      barbeiros: this.cadastros.listarFuncionarios(),
      caixa: this.api.caixaAtual(),
    }).subscribe({
      next: (r) => {
        this.aplicar(r.comanda);
        this.servicos.set(r.servicos.filter((s) => s.ativo));
        this.produtos.set(r.produtos.filter((p) => p.ativo));
        this.barbeiros.set(r.barbeiros.filter((f) => f.ativo));
        this.caixaAberto.set(!!r.caixa.caixa);
      },
      error: (e) => this.erro.set(mensagemDeErro(e)),
    });
  }

  private aplicar(c: Comanda): void {
    this.comanda.set(c);
    this.itens.set(c.itens.map((i) => ({ ...i, preco_unitario: +i.preco_unitario })));
    this.desconto = +c.desconto;
    this.pagamentos.set([]);
    this.tocar();
  }

  tocar(): void {
    this.versao.update((v) => v + 1);
  }

  adicionarServico(id: number | null): void {
    const s = this.servicos().find((x) => x.id === id);
    if (!s) return;
    this.itens.update((l) => [
      ...l,
      {
        tipo: 'servico', servico_id: s.id, produto_id: null, funcionario_id: this.comanda()?.barbeiro?.id ?? null,
        descricao: s.nome, quantidade: 1, preco_unitario: +s.preco,
      },
    ]);
    this.tocar();
  }

  adicionarProduto(id: number | null): void {
    const p = this.produtos().find((x) => x.id === id);
    if (!p) return;
    const existente = this.itens().find((i) => i.produto_id === p.id);
    if (existente) {
      existente.quantidade += 1;
    } else {
      this.itens.update((l) => [
        ...l,
        {
          tipo: 'produto', servico_id: null, produto_id: p.id, funcionario_id: this.comanda()?.barbeiro?.id ?? null,
          descricao: p.nome, quantidade: 1, preco_unitario: +p.preco,
        },
      ]);
    }
    this.tocar();
  }

  removerItem(i: number): void {
    this.itens.update((l) => l.filter((_, idx) => idx !== i));
    this.tocar();
  }

  /** Nova forma de pagamento já com o valor que falta. */
  adicionarPagamento(forma: FormaPagamento): void {
    const falta = Math.max(this.restante(), 0);
    this.pagamentos.update((l) => [...l, { forma, valor: falta }]);
    this.tocar();
  }

  removerPagamento(i: number): void {
    this.pagamentos.update((l) => l.filter((_, idx) => idx !== i));
    this.tocar();
  }

  abrirCaixa(): void {
    this.api.abrirCaixa(+this.valorInicial || 0).subscribe({
      next: () => {
        this.caixaAberto.set(true);
        this.erro.set('');
      },
      error: (e) => this.erro.set(mensagemDeErro(e)),
    });
  }

  confirmar(): void {
    const c = this.comanda();
    if (!c || !this.podeConfirmar()) return;
    this.salvando.set(true);
    this.erro.set('');
    this.api
      .fecharComanda(c.id, {
        itens: this.itens().map((i) => ({
          tipo: i.tipo,
          servico_id: i.servico_id,
          produto_id: i.produto_id,
          funcionario_id: i.funcionario_id,
          quantidade: +i.quantidade,
          preco_unitario: +i.preco_unitario,
        })),
        desconto: +this.desconto || 0,
        pagamentos: this.pagamentos().filter((p) => +p.valor > 0).map((p) => ({ forma: p.forma, valor: +p.valor })),
      })
      .subscribe({
        next: (paga) => {
          this.salvando.set(false);
          this.pago.emit(paga);
        },
        error: (e) => {
          this.salvando.set(false);
          const campos = errosPorCampo(e);
          this.erro.set(Object.values(campos)[0] ?? mensagemDeErro(e));
        },
      });
  }

  cancelar(): void {
    const c = this.comanda();
    if (!c || !confirm('Cancelar esta comanda? Nada será cobrado.')) return;
    this.api.cancelarComanda(c.id).subscribe({
      next: () => this.fechar.emit(),
      error: (e) => this.erro.set(mensagemDeErro(e)),
    });
  }

  estornar(): void {
    const c = this.comanda();
    if (!c || !confirm('Estornar o pagamento? A comanda volta a ficar em aberto.')) return;
    this.api.estornarComanda(c.id).subscribe({
      next: (aberta) => this.aplicar(aberta),
      error: (e) => this.erro.set(mensagemDeErro(e)),
    });
  }
}
