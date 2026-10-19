import { CurrencyPipe, DatePipe } from '@angular/common';
import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { AuthService } from '../../core/auth.service';
import { podeEditar } from '../../core/auth.guards';
import { CadastrosService } from '../../core/cadastros.service';
import { FormsModule } from '@angular/forms';
import { AvisoService } from '../../core/aviso.service';
import { mensagemDeErro } from '../../core/erros';
import { Caixa as DadosCaixa, ComandaResumo, FinanceiroService, FORMAS, ROTULO_FORMA } from '../../core/financeiro.service';
import { Pagina } from '../../core/modelos';
import { AbrirComanda, PainelComanda } from './painel-comanda';

type Acao = 'suprimento' | 'sangria' | 'fechar' | null;

/** Caixa do dia: abertura, recebimentos, comandas a receber, sangria/suprimento e fechamento. */
@Component({
  selector: 'app-caixa',
  imports: [FormsModule, CurrencyPipe, DatePipe, PainelComanda],
  template: `
    @if (carregando()) {
      <p class="texto-suave">Carregando caixa…</p>
    } @else if (caixa(); as cx) {
      <div class="cabecalho-pagina">
        <div>
          <h1>Caixa aberto</h1>
          <p class="texto-suave">Desde {{ cx.aberto_em | date: "dd/MM 'às' HH:mm" }} · por {{ cx.aberto_por }}</p>
        </div>
        <div class="botoes-linha">
          <button class="botao botao-secundario botao-auto" type="button" (click)="comanda.set({ avulsa: true })">+ Venda avulsa</button>
          <button class="botao botao-secundario botao-auto" type="button" (click)="abrirAcao('suprimento')">Suprimento</button>
          <button class="botao botao-auto" type="button" [class.botao-secundario]="!excedente()" [class.botao-sangria]="excedente() > 0"
            (click)="abrirAcao('sangria')" [title]="excedente() > 0 ? 'Gaveta acima do limite: faça uma sangria' : 'Retirar dinheiro do caixa'">
            Sangria
          </button>
          <button class="botao botao-auto" type="button" (click)="abrirAcao('fechar')">Fechar caixa</button>
        </div>
      </div>

      @if (excedente() > 0) {
        <div class="alerta-gaveta" role="alert">
          <div>
            <strong>Gaveta acima do limite.</strong>
            Há {{ cx.resumo.dinheiro_esperado | currency: 'BRL' }} em dinheiro (limite {{ limite() | currency: 'BRL' }}).
            Faça uma sangria de pelo menos {{ excedente() | currency: 'BRL' }}.
          </div>
          <button class="botao botao-sangria botao-auto" type="button" (click)="abrirAcao('sangria')">Fazer sangria</button>
        </div>
      }

      <div class="limite-gaveta">
        @if (editandoLimite()) {
          <form class="linha-abrir-caixa" (ngSubmit)="salvarLimite()">
            <label>Limite de dinheiro na gaveta (R$) <input type="number" min="0" step="10" name="limite" [(ngModel)]="novoLimite" placeholder="Sem limite" /></label>
            <button class="botao botao-auto" type="submit">Salvar</button>
            <button class="botao botao-secundario botao-auto" type="button" (click)="editandoLimite.set(false)">Cancelar</button>
          </form>
        } @else {
          <span class="texto-suave pequeno">
            Limite da gaveta: <strong>{{ limite() !== null ? (limite() | currency: 'BRL') : 'não definido' }}</strong>
          </span>
          @if (dono) {
            <button class="botao-link" type="button" (click)="editarLimite()">{{ limite() !== null ? 'Alterar' : 'Definir limite' }}</button>
          }
        }
      </div>

      <div class="indicadores">
        <div class="indicador destaque">
          <span>Total recebido</span><strong>{{ cx.resumo.total_recebido | currency: 'BRL' }}</strong>
          <small>{{ cx.resumo.atendimentos }} comanda(s) paga(s)</small>
        </div>
        @for (f of formas; track f.id) {
          <div class="indicador"><span>{{ f.rotulo }}</span><strong>{{ cx.resumo.recebido_por_forma[f.id] | currency: 'BRL' }}</strong></div>
        }
        <div class="indicador" [class.alerta-indicador]="excedente() > 0">
          <span>Dinheiro na gaveta</span><strong>{{ cx.resumo.dinheiro_esperado | currency: 'BRL' }}</strong>
          <small>troco {{ cx.resumo.valor_inicial | currency: 'BRL' }} · sangrias {{ cx.resumo.sangrias | currency: 'BRL' }}</small>
        </div>
      </div>

      <section class="cartao-secao">
        <h2>Aguardando pagamento <span class="contador">{{ abertas().length }}</span></h2>
        @if (abertas().length === 0) {
          <p class="texto-suave">Nenhum atendimento aguardando pagamento.</p>
        } @else {
          <ul class="lista-simples">
            @for (c of abertas(); track c.id) {
              <li>
                <div>
                  <strong>{{ c.cliente?.nome ?? 'Venda avulsa' }}</strong>
                  <span class="texto-suave pequeno">
                    {{ c.barbeiro?.nome }}{{ c.horario ? ' · ' + (c.horario | date: "dd/MM HH:mm") : '' }}
                  </span>
                </div>
                <strong>{{ +c.total | currency: 'BRL' }}</strong>
                <button class="botao botao-auto" type="button" (click)="comanda.set({ comandaId: c.id })">Receber</button>
              </li>
            }
          </ul>
        }
      </section>

      <div class="grade-2 grade-caixa">
        <section class="cartao-secao">
          <h2>Recebimentos</h2>
          @if (!cx.recebimentos?.length) {
            <p class="texto-suave">Nenhum pagamento recebido ainda.</p>
          }
          <ul class="lista-simples">
            @for (r of cx.recebimentos; track r.comanda_id) {
              <li class="clicavel" (click)="comanda.set({ comandaId: r.comanda_id })">
                <div>
                  <strong>{{ r.cliente ?? 'Venda avulsa' }}</strong>
                  <span class="texto-suave pequeno">{{ r.pago_em | date: 'HH:mm' }} · {{ nomesFormas(r.formas) }}{{ r.barbeiro ? ' · ' + r.barbeiro : '' }}</span>
                </div>
                <strong>{{ +r.total | currency: 'BRL' }}</strong>
              </li>
            }
          </ul>
        </section>

        <section class="cartao-secao">
          <h2>Movimentações</h2>
          @if (!cx.movimentos?.length) {
            <p class="texto-suave">Sem suprimentos ou sangrias.</p>
          }
          <ul class="lista-simples">
            @for (m of cx.movimentos; track m.id) {
              <li>
                <div>
                  <strong>{{ m.tipo === 'sangria' ? 'Sangria' : 'Suprimento' }}</strong>
                  <span class="texto-suave pequeno">{{ m.em | date: 'HH:mm' }} · {{ m.usuario }}{{ m.descricao ? ' · ' + m.descricao : '' }}</span>
                </div>
                <strong [class.negativo]="m.tipo === 'sangria'">{{ m.tipo === 'sangria' ? '−' : '+' }} {{ +m.valor | currency: 'BRL' }}</strong>
              </li>
            }
          </ul>
          @if (cx.resumo.despesas_pagas > 0) {
            <p class="texto-suave pequeno">Despesas pagas com dinheiro do caixa: {{ cx.resumo.despesas_pagas | currency: 'BRL' }}</p>
          }
        </section>
      </div>

      @if (acao(); as a) {
        <div class="fundo-modal" (click)="acao.set(null)"></div>
        <form class="painel-lateral" (ngSubmit)="confirmarAcao(a)">
          @if (a === 'fechar') {
            <h2>Fechar caixa</h2>
            <dl class="detalhes">
              <dt>Troco inicial</dt><dd>{{ cx.resumo.valor_inicial | currency: 'BRL' }}</dd>
              <dt>Recebido em dinheiro</dt><dd>{{ cx.resumo.recebido_por_forma.dinheiro | currency: 'BRL' }}</dd>
              <dt>Suprimentos</dt><dd>+ {{ cx.resumo.suprimentos | currency: 'BRL' }}</dd>
              <dt>Sangrias</dt><dd>− {{ cx.resumo.sangrias | currency: 'BRL' }}</dd>
              <dt>Despesas</dt><dd>− {{ cx.resumo.despesas_pagas | currency: 'BRL' }}</dd>
              <dt><strong>Esperado</strong></dt><dd><strong>{{ cx.resumo.dinheiro_esperado | currency: 'BRL' }}</strong></dd>
            </dl>
            <label class="campo">
              <span>Dinheiro contado na gaveta (R$)</span>
              <input type="number" min="0" step="0.01" name="valor" [(ngModel)]="valor" required />
            </label>
            @if (valor !== null) {
              <p class="restante" [class.ok]="diferenca(cx) === 0" [class.excesso]="diferenca(cx) !== 0">
                {{ diferenca(cx) === 0 ? 'Caixa confere.' : (diferenca(cx) > 0 ? 'Sobra de ' : 'Falta de ') + (abs(diferenca(cx)) | currency: 'BRL') }}
              </p>
            }
            @if (abertas().length) {
              <p class="alerta">Há {{ abertas().length }} atendimento(s) aguardando pagamento. Eles continuam em aberto para o próximo caixa.</p>
            }
            <label class="campo">
              <span>Observações <em>(opcional)</em></span>
              <textarea name="obs" rows="2" [(ngModel)]="descricao"></textarea>
            </label>
          } @else {
            <h2>{{ a === 'sangria' ? 'Sangria (retirada)' : 'Suprimento (entrada de troco)' }}</h2>
            <label class="campo">
              <span>Valor (R$)</span>
              <input type="number" min="0.01" step="0.01" name="valor" [(ngModel)]="valor" required />
            </label>
            <label class="campo">
              <span>Descrição <em>(opcional)</em></span>
              <input name="descricao" [(ngModel)]="descricao" [placeholder]="a === 'sangria' ? 'Ex.: depósito no banco' : 'Ex.: troco extra'" />
            </label>
          }
          <div class="rodape-painel">
            <button class="botao botao-secundario botao-auto" type="button" (click)="acao.set(null)">Cancelar</button>
            <button class="botao botao-auto" type="submit" [disabled]="valor === null || salvando()">Confirmar</button>
          </div>
        </form>
      }
    } @else {
      <div class="cartao-secao abrir-caixa">
        <h2>Caixa fechado</h2>
        <p class="texto-suave">Abra o caixa no início do dia informando o dinheiro de troco na gaveta.</p>
        <form class="linha-abrir-caixa" (ngSubmit)="abrir()">
          <label>Troco inicial (R$) <input type="number" min="0" step="0.01" name="inicial" [(ngModel)]="valorInicial" /></label>
          <button class="botao botao-auto" type="submit" [disabled]="salvando()">Abrir caixa</button>
        </form>
        @if (abertas().length) {
          <p class="texto-suave pequeno">{{ abertas().length }} atendimento(s) aguardando pagamento.</p>
        }
      </div>
    }

    @if (!carregando()) {
      <section class="cartao-secao">
        <h2>Caixas anteriores</h2>
        @if (!historico()?.data?.length) {
          <p class="texto-suave">Nenhum caixa fechado ainda.</p>
        } @else {
          <div class="tabela-container">
            <table class="tabela">
              <thead><tr><th>Abertura</th><th>Fechamento</th><th>Recebido</th><th>Esperado</th><th>Contado</th><th>Diferença</th></tr></thead>
              <tbody>
                @for (h of historico()!.data; track h.id) {
                  @if (!h.aberto) {
                    <tr>
                      <td>{{ h.aberto_em | date: 'dd/MM HH:mm' }}<div class="texto-suave pequeno">{{ h.aberto_por }}</div></td>
                      <td>{{ h.fechado_em | date: 'dd/MM HH:mm' }}<div class="texto-suave pequeno">{{ h.fechado_por }}</div></td>
                      <td>{{ h.resumo.total_recebido | currency: 'BRL' }}</td>
                      <td>{{ h.resumo.dinheiro_esperado | currency: 'BRL' }}</td>
                      <td>{{ h.resumo.valor_contado | currency: 'BRL' }}</td>
                      <td [class.negativo]="(h.resumo.diferenca ?? 0) < 0" [class.positivo]="(h.resumo.diferenca ?? 0) > 0">
                        {{ h.resumo.diferenca | currency: 'BRL' }}
                      </td>
                    </tr>
                  }
                }
              </tbody>
            </table>
          </div>
        }
      </section>
    }

    @if (comanda(); as origem) {
      <app-painel-comanda [origem]="origem" (fechar)="fecharComanda(false)" (pago)="fecharComanda(true)" />
    }
  `,
})
export class Caixa implements OnInit {
  private readonly api = inject(FinanceiroService);
  private readonly cadastros = inject(CadastrosService);
  private readonly aviso = inject(AvisoService);
  protected readonly formas = FORMAS;

  protected readonly carregando = signal(true);
  protected readonly caixa = signal<DadosCaixa | null>(null);
  protected readonly abertas = signal<ComandaResumo[]>([]);
  protected readonly historico = signal<Pagina<DadosCaixa> | null>(null);
  protected readonly comanda = signal<AbrirComanda | null>(null);
  protected readonly acao = signal<Acao>(null);
  protected readonly salvando = signal(false);
  protected valorInicial = 100;
  protected readonly dono = podeEditar(inject(AuthService), 'dono');
  protected readonly limite = signal<number | null>(null);
  protected readonly editandoLimite = signal(false);
  protected novoLimite: number | null = null;
  protected readonly excedente = computed(() => this.caixa()?.excedente_gaveta ?? 0);
  /** Evita reabrir a sangria sozinha depois que o usuário já a viu neste caixa. */
  private avisouExcedente = false;
  protected valor: number | null = null;
  protected descricao = '';
  protected readonly abs = Math.abs;

  ngOnInit(): void {
    this.carregar();
  }

  carregar(): void {
    this.api.caixaAtual().subscribe({
      next: (r) => {
        this.caixa.set(r.caixa);
        this.limite.set(r.limite_gaveta);
        this.carregando.set(false);
        // Passou do limite: abre a sangria já com o valor sugerido (uma vez)
        if (this.excedente() > 0 && !this.avisouExcedente && !this.acao() && !this.comanda()) {
          this.avisouExcedente = true;
          this.abrirAcao('sangria');
        }
        if (this.excedente() === 0) this.avisouExcedente = false;
      },
      error: (e) => {
        this.carregando.set(false);
        this.aviso.erro(mensagemDeErro(e));
      },
    });
    this.api.comandasAbertas().subscribe((c) => this.abertas.set(c));
    this.api.historicoCaixas(1).subscribe((h) => this.historico.set(h));
  }

  abrir(): void {
    this.salvando.set(true);
    this.api.abrirCaixa(+this.valorInicial || 0).subscribe({
      next: () => {
        this.salvando.set(false);
        this.aviso.sucesso('Caixa aberto.');
        this.carregar();
      },
      error: (e) => {
        this.salvando.set(false);
        this.aviso.erro(mensagemDeErro(e));
      },
    });
  }

  diferenca(cx: DadosCaixa): number {
    return Math.round(((+(this.valor ?? 0)) - cx.resumo.dinheiro_esperado) * 100) / 100;
  }

  nomesFormas(formas: (keyof typeof ROTULO_FORMA)[]): string {
    return formas.map((f) => ROTULO_FORMA[f]).join(' + ');
  }

  abrirAcao(a: Exclude<Acao, null>): void {
    this.valor = a === 'sangria' && this.excedente() > 0 ? this.excedente() : null;
    this.descricao = '';
    this.acao.set(a);
  }

  editarLimite(): void {
    this.novoLimite = this.limite();
    this.editandoLimite.set(true);
  }

  salvarLimite(): void {
    const valor = this.novoLimite === null || (this.novoLimite as unknown) === '' ? null : +this.novoLimite;
    this.cadastros.salvarEmpresa({ limite_gaveta: valor }).subscribe({
      next: () => {
        this.editandoLimite.set(false);
        this.aviso.sucesso(valor === null ? 'Limite da gaveta removido.' : 'Limite da gaveta salvo.');
        this.avisouExcedente = false;
        this.carregar();
      },
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }

  confirmarAcao(a: Exclude<Acao, null>): void {
    if (this.valor === null) return;
    this.salvando.set(true);
    const pedido =
      a === 'fechar'
        ? this.api.fecharCaixa(+this.valor, this.descricao)
        : this.api.movimentarCaixa(a, +this.valor, this.descricao);
    pedido.subscribe({
      next: () => {
        this.salvando.set(false);
        this.acao.set(null);
        this.valor = null;
        this.descricao = '';
        this.aviso.sucesso(a === 'fechar' ? 'Caixa fechado.' : a === 'sangria' ? 'Sangria registrada.' : 'Suprimento registrado.');
        this.carregar();
      },
      error: (e) => {
        this.salvando.set(false);
        this.aviso.erro(mensagemDeErro(e));
      },
    });
  }

  fecharComanda(pago: boolean): void {
    this.comanda.set(null);
    if (pago) this.aviso.sucesso('Pagamento registrado.');
    this.carregar();
  }
}
