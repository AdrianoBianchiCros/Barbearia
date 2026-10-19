import { CurrencyPipe, DatePipe } from '@angular/common';
import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AvisoService } from '../../core/aviso.service';
import { errosPorCampo, mensagemDeErro } from '../../core/erros';
import { CATEGORIAS_DESPESA, FinanceiroService, Lancamento } from '../../core/financeiro.service';

const mesAtual = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
};

/** Contas a pagar da barbearia, por mês de vencimento. */
@Component({
  selector: 'app-despesas',
  imports: [FormsModule, CurrencyPipe, DatePipe],
  template: `
    <div class="cabecalho-pagina">
      <div>
        <h1>Despesas</h1>
        <p class="texto-suave">Aluguel, contas, produtos e demais saídas da barbearia.</p>
      </div>
      <div class="botoes-linha">
        <div class="navegacao-mes">
          <button class="botao-icone-borda" type="button" (click)="mudarMes(-1)" aria-label="Mês anterior">‹</button>
          <strong>{{ mes() + '-15T12:00:00' | date: "MMMM 'de' yyyy" }}</strong>
          <button class="botao-icone-borda" type="button" (click)="mudarMes(1)" aria-label="Próximo mês">›</button>
        </div>
        <button class="botao botao-auto" type="button" (click)="abrirNova()">+ Nova despesa</button>
      </div>
    </div>

    <div class="indicadores">
      <div class="indicador"><span>Total do mês</span><strong>{{ total() | currency: 'BRL' }}</strong></div>
      <div class="indicador"><span>Pago</span><strong>{{ totalPago() | currency: 'BRL' }}</strong></div>
      <div class="indicador destaque"><span>A pagar</span><strong>{{ total() - totalPago() | currency: 'BRL' }}</strong></div>
      @if (atrasadas() > 0) {
        <div class="indicador alerta-indicador"><span>Atrasadas</span><strong>{{ atrasadas() }}</strong></div>
      }
    </div>

    @if (lista().length === 0) {
      <div class="vazio">Nenhuma despesa neste mês.</div>
    } @else {
      <div class="tabela-container">
        <table class="tabela">
          <thead><tr><th>Vencimento</th><th>Descrição</th><th>Categoria</th><th>Valor</th><th>Situação</th><th></th></tr></thead>
          <tbody>
            @for (l of lista(); track l.id) {
              <tr>
                <td>{{ l.vencimento + 'T12:00:00' | date: 'dd/MM' }}</td>
                <td><strong>{{ l.descricao }}</strong></td>
                <td>{{ categorias[l.categoria ?? 'outros'] }}</td>
                <td>{{ +l.valor | currency: 'BRL' }}</td>
                <td>
                  @if (l.pago_em) {
                    <span class="etiqueta etiqueta-ok">Pago {{ l.pago_em | date: 'dd/MM' }}{{ l.do_caixa ? ' (caixa)' : '' }}</span>
                  } @else if (l.atrasada) {
                    <span class="etiqueta etiqueta-erro">Atrasada</span>
                  } @else {
                    <span class="etiqueta">A pagar</span>
                  }
                </td>
                <td class="acoes">
                  @if (!l.pago_em) {
                    <button class="botao-link" type="button" (click)="pagar(l, false)">Marcar como paga</button>
                    <button class="botao-link" type="button" (click)="pagar(l, true)" title="Retira o valor do dinheiro do caixa aberto">Pagar com caixa</button>
                  }
                  <button class="botao-link perigo" type="button" (click)="excluir(l)">Excluir</button>
                </td>
              </tr>
            }
          </tbody>
        </table>
      </div>
    }

    @if (nova()) {
      <div class="fundo-modal" (click)="nova.set(false)"></div>
      <form class="painel-lateral" (ngSubmit)="salvar()">
        <h2>Nova despesa</h2>
        @if (erro()) {<div class="alerta">{{ erro() }}</div>}
        <label class="campo"><span>Descrição</span><input name="descricao" [(ngModel)]="form.descricao" placeholder="Ex.: Conta de luz" required /></label>
        <div class="grade-2">
          <label class="campo">
            <span>Categoria</span>
            <select name="categoria" [(ngModel)]="form.categoria">
              @for (c of listaCategorias; track c[0]) {<option [value]="c[0]">{{ c[1] }}</option>}
            </select>
          </label>
          <label class="campo"><span>Valor (R$)</span><input type="number" min="0.01" step="0.01" name="valor" [(ngModel)]="form.valor" required /></label>
          <label class="campo"><span>Vencimento</span><input type="date" name="vencimento" [(ngModel)]="form.vencimento" /></label>
        </div>
        <label class="campo-check"><input type="checkbox" name="pago" [(ngModel)]="form.pago" /> Já está paga</label>
        @if (form.pago) {
          <label class="campo-check"><input type="checkbox" name="caixa" [(ngModel)]="form.pagar_com_caixa" /> Paga com dinheiro do caixa aberto</label>
        }
        <div class="rodape-painel">
          <button class="botao botao-secundario botao-auto" type="button" (click)="nova.set(false)">Cancelar</button>
          <button class="botao botao-auto" type="submit" [disabled]="salvando()">Salvar</button>
        </div>
      </form>
    }
  `,
})
export class Despesas implements OnInit {
  private readonly api = inject(FinanceiroService);
  private readonly aviso = inject(AvisoService);
  protected readonly categorias = CATEGORIAS_DESPESA;
  protected readonly listaCategorias = Object.entries(CATEGORIAS_DESPESA);

  protected readonly mes = signal(mesAtual());
  protected readonly lista = signal<Lancamento[]>([]);
  protected readonly total = signal(0);
  protected readonly totalPago = signal(0);
  protected readonly atrasadas = computed(() => this.lista().filter((l) => l.atrasada).length);
  protected readonly nova = signal(false);
  protected readonly salvando = signal(false);
  protected readonly erro = signal('');
  protected form = this.formVazio();

  ngOnInit(): void {
    this.carregar();
  }

  carregar(): void {
    this.api.lancamentos('despesa', this.mes()).subscribe({
      next: (r) => {
        this.lista.set(r.lancamentos);
        this.total.set(r.total);
        this.totalPago.set(r.total_pago);
      },
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }

  mudarMes(delta: number): void {
    const [a, m] = this.mes().split('-').map(Number);
    const d = new Date(a, m - 1 + delta, 1);
    this.mes.set(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
    this.carregar();
  }

  abrirNova(): void {
    this.form = this.formVazio();
    this.erro.set('');
    this.nova.set(true);
  }

  salvar(): void {
    this.salvando.set(true);
    this.api.criarLancamento({ tipo: 'despesa', ...this.form }).subscribe({
      next: () => {
        this.salvando.set(false);
        this.nova.set(false);
        this.aviso.sucesso('Despesa registrada.');
        this.carregar();
      },
      error: (e) => {
        this.salvando.set(false);
        this.erro.set(Object.values(errosPorCampo(e))[0] ?? mensagemDeErro(e));
      },
    });
  }

  pagar(l: Lancamento, comCaixa: boolean): void {
    this.api.pagarLancamento(l.id, comCaixa).subscribe({
      next: () => {
        this.aviso.sucesso('Despesa paga.');
        this.carregar();
      },
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }

  excluir(l: Lancamento): void {
    if (!confirm(`Excluir a despesa "${l.descricao}"?`)) return;
    this.api.excluirLancamento(l.id).subscribe({
      next: () => this.carregar(),
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }

  private formVazio() {
    const hoje = new Date();
    const venc = `${this.mes?.() ?? mesAtual()}-${String(Math.min(hoje.getDate(), 28)).padStart(2, '0')}`;
    return { descricao: '', categoria: 'outros', valor: null as number | null, vencimento: venc, pago: false, pagar_com_caixa: false };
  }
}
