import { CurrencyPipe, DatePipe } from '@angular/common';
import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../core/auth.service';
import { podeEditar } from '../../core/auth.guards';
import { AvisoService } from '../../core/aviso.service';
import { mensagemDeErro } from '../../core/erros';
import { ComissaoBarbeiro, FinanceiroService, RelatorioComissoes } from '../../core/financeiro.service';

type Lancar = { tipo: 'vale' | 'repasse_comissao'; barbeiro: ComissaoBarbeiro } | null;

const hojeLocal = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

/** Comissões do período: geradas pelas comandas pagas, menos vales e repasses. */
@Component({
  selector: 'app-comissoes',
  imports: [FormsModule, CurrencyPipe, DatePipe],
  template: `
    <div class="cabecalho-pagina">
      <div>
        <h1>{{ dono ? 'Comissões' : 'Minhas comissões' }}</h1>
        <p class="texto-suave">Calculadas sobre os atendimentos e vendas pagos no período.</p>
      </div>
      <form class="filtro-periodo" (ngSubmit)="carregar()">
        <label>De <input type="date" name="inicio" [(ngModel)]="inicio" /></label>
        <label>até <input type="date" name="fim" [(ngModel)]="fim" /></label>
        <button class="botao botao-secundario botao-auto" type="submit">Ver</button>
      </form>
    </div>

    @if (relatorio(); as r) {
      <div class="indicadores">
        <div class="indicador destaque"><span>Comissões geradas</span><strong>{{ r.totais.comissao | currency: 'BRL' }}</strong></div>
        <div class="indicador"><span>Vales</span><strong>{{ r.totais.vales | currency: 'BRL' }}</strong></div>
        <div class="indicador"><span>Já repassado</span><strong>{{ r.totais.repasses | currency: 'BRL' }}</strong></div>
        <div class="indicador"><span>Saldo a pagar</span><strong [class.negativo]="r.totais.saldo < 0">{{ r.totais.saldo | currency: 'BRL' }}</strong></div>
      </div>

      @if (r.barbeiros.length === 0) {
        <div class="vazio">Nenhum barbeiro encontrado.</div>
      } @else {
        <div class="tabela-container">
          <table class="tabela">
            <thead>
              <tr>
                <th>Barbeiro</th><th>Atendimentos</th><th>Serviços</th><th>Produtos</th>
                <th>Comissão</th><th>Vales</th><th>Repasses</th><th>Saldo</th>
                @if (dono) {<th></th>}
              </tr>
            </thead>
            <tbody>
              @for (b of r.barbeiros; track b.funcionario_id) {
                <tr>
                  <td><strong>{{ b.nome }}</strong><div class="texto-suave pequeno">{{ +b.comissao_percentual }}% nos serviços</div></td>
                  <td>{{ b.atendimentos }}</td>
                  <td>{{ b.faturado_servicos | currency: 'BRL' }}</td>
                  <td>{{ b.faturado_produtos | currency: 'BRL' }}</td>
                  <td>{{ b.comissao | currency: 'BRL' }}</td>
                  <td>{{ b.vales | currency: 'BRL' }}</td>
                  <td>{{ b.repasses | currency: 'BRL' }}</td>
                  <td><strong [class.negativo]="b.saldo < 0">{{ b.saldo | currency: 'BRL' }}</strong></td>
                  @if (dono) {
                    <td class="acoes">
                      <button class="botao-link" type="button" (click)="abrirLancamento('vale', b)">Vale</button>
                      <button class="botao-link" type="button" (click)="abrirLancamento('repasse_comissao', b)">Pagar</button>
                    </td>
                  }
                </tr>
              }
            </tbody>
          </table>
        </div>
        <p class="texto-suave pequeno">
          Período de {{ r.inicio + 'T12:00:00' | date: 'dd/MM/yyyy' }} a {{ r.fim + 'T12:00:00' | date: 'dd/MM/yyyy' }}.
          A comissão usa o percentual vigente quando cada comanda foi paga.
        </p>
      }
    } @else {
      <p class="texto-suave">Carregando…</p>
    }

    @if (lancar(); as l) {
      <div class="fundo-modal" (click)="lancar.set(null)"></div>
      <form class="painel-lateral" (ngSubmit)="salvarLancamento(l)">
        <h2>{{ l.tipo === 'vale' ? 'Vale / adiantamento' : 'Pagamento de comissão' }}</h2>
        <p class="texto-suave">{{ l.barbeiro.nome }} · saldo atual {{ l.barbeiro.saldo | currency: 'BRL' }}</p>
        <label class="campo">
          <span>Valor (R$)</span>
          <input type="number" min="0.01" step="0.01" name="valor" [(ngModel)]="valor" required />
        </label>
        <label class="campo">
          <span>Descrição</span>
          <input name="descricao" [(ngModel)]="descricao" />
        </label>
        <label class="campo-check">
          <input type="checkbox" name="caixa" [(ngModel)]="comCaixa" /> Pago com dinheiro do caixa aberto
        </label>
        <div class="rodape-painel">
          <button class="botao botao-secundario botao-auto" type="button" (click)="lancar.set(null)">Cancelar</button>
          <button class="botao botao-auto" type="submit" [disabled]="!valor || salvando()">Registrar</button>
        </div>
      </form>
    }
  `,
})
export class Comissoes implements OnInit {
  private readonly api = inject(FinanceiroService);
  private readonly aviso = inject(AvisoService);
  protected readonly dono = podeEditar(inject(AuthService), 'dono');

  protected inicio = hojeLocal().slice(0, 8) + '01';
  protected fim = hojeLocal();
  protected readonly relatorio = signal<RelatorioComissoes | null>(null);
  protected readonly lancar = signal<Lancar>(null);
  protected readonly salvando = signal(false);
  protected valor: number | null = null;
  protected descricao = '';
  protected comCaixa = false;

  ngOnInit(): void {
    this.carregar();
  }

  carregar(): void {
    this.api.comissoes(this.inicio, this.fim).subscribe({
      next: (r) => this.relatorio.set(r),
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }

  abrirLancamento(tipo: 'vale' | 'repasse_comissao', barbeiro: ComissaoBarbeiro): void {
    this.valor = tipo === 'repasse_comissao' && barbeiro.saldo > 0 ? barbeiro.saldo : null;
    this.descricao = tipo === 'vale' ? 'Adiantamento' : 'Repasse de comissão';
    this.comCaixa = false;
    this.lancar.set({ tipo, barbeiro });
  }

  salvarLancamento(l: Exclude<Lancar, null>): void {
    if (!this.valor) return;
    this.salvando.set(true);
    this.api
      .criarLancamento({
        tipo: l.tipo,
        funcionario_id: l.barbeiro.funcionario_id,
        valor: this.valor,
        descricao: this.descricao || (l.tipo === 'vale' ? 'Adiantamento' : 'Repasse de comissão'),
        pagar_com_caixa: this.comCaixa,
      })
      .subscribe({
        next: () => {
          this.salvando.set(false);
          this.lancar.set(null);
          this.aviso.sucesso(l.tipo === 'vale' ? 'Vale registrado.' : 'Pagamento de comissão registrado.');
          this.carregar();
        },
        error: (e) => {
          this.salvando.set(false);
          this.aviso.erro(mensagemDeErro(e));
        },
      });
  }
}
