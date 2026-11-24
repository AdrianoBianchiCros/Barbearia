import { CurrencyPipe, DatePipe, PercentPipe } from '@angular/common';
import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { STATUS_ROTULO } from '../../core/agenda.service';
import { AuthService } from '../../core/auth.service';
import { AgendaBarbeiro, BarbeiroService } from '../../core/barbeiro.service';
import { mensagemDeErro } from '../../core/erros';
import { ComissaoBarbeiro } from '../../core/financeiro.service';
import { SinoAvisos } from '../../layout/sino-avisos';

type Aba = 'agenda' | 'comissao';
type Periodo = 'mes' | 'mes_passado' | 'outro';

/** "AAAA-MM-DD" no fuso do aparelho (o barbeiro está na barbearia). */
function iso(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function somarDias(data: string, dias: number): string {
  const [a, m, d] = data.split('-').map(Number);
  return iso(new Date(a, m - 1, d + dias));
}

/**
 * Painel do barbeiro (/barbeiro): só a própria agenda do dia e a comissão a receber.
 * Pensado para o celular; não altera nada, só consulta.
 */
@Component({
  selector: 'app-painel-barbeiro',
  imports: [FormsModule, CurrencyPipe, DatePipe, PercentPipe, SinoAvisos],
  template: `
    <div class="painel">
      <header class="topo">
        @if (agenda()?.barbeiro?.foto_url) {
          <img class="avatar" [src]="agenda()!.barbeiro.foto_url" alt="" />
        } @else {
          <span class="avatar avatar-inicial">{{ nome().charAt(0) }}</span>
        }
        <div class="topo-textos">
          <strong>{{ nome() }}</strong>
          <span class="texto-suave pequeno">{{ auth.sessao()?.empresa?.nome }}</span>
        </div>
        <button class="botao-link" type="button" (click)="auth.logout('/barbeiro/login')">Sair</button>
      </header>

      <nav class="abas">
        <button type="button" [class.ativa]="aba() === 'agenda'" (click)="aba.set('agenda')">Minha agenda</button>
        <button type="button" [class.ativa]="aba() === 'comissao'" (click)="abrirComissao()">Minha comissão</button>
      </nav>

      @if (erro()) {
        <div class="alerta" role="alert">{{ erro() }}</div>
      }

      @if (aba() === 'agenda') {
        <div class="navegacao-dia">
          <button class="botao botao-secundario botao-auto" type="button" (click)="irPara(somar(-1))" aria-label="Dia anterior">‹</button>
          <input type="date" [ngModel]="data()" (ngModelChange)="irPara($event)" aria-label="Dia" />
          <button class="botao botao-secundario botao-auto" type="button" (click)="irPara(somar(1))" aria-label="Próximo dia">›</button>
          @if (data() !== hoje) {
            <button class="botao-link" type="button" (click)="irPara(hoje)">Hoje</button>
          }
          <app-sino-avisos (abrir)="irPara($event.inicio.slice(0, 10))" />
        </div>

        @if (agenda(); as a) {
          <p class="resumo-dia">
            <strong>{{ a.data + 'T12:00' | date: "EEEE, d 'de' MMMM" }}</strong><br />
            <span class="texto-suave pequeno">
              @if (a.expediente.length) {
                Expediente:
                @for (e of a.expediente; track $index) {{{ e.inicio }} às {{ e.fim }}{{ $last ? '' : ' · ' }}}
              } @else {
                Sem expediente neste dia
              }
              · {{ a.agendamentos.length }} atendimento(s) · {{ totalDia() | currency: 'BRL' }}
            </span>
          </p>

          @for (b of a.bloqueios; track $index) {
            <div class="bloqueio">
              Bloqueado {{ b.inicio | date: 'HH:mm' }}–{{ b.fim | date: 'HH:mm' }}{{ b.motivo ? ' · ' + b.motivo : '' }}
            </div>
          }

          @if (a.agendamentos.length === 0) {
            <div class="vazio">Nenhum atendimento marcado para este dia.</div>
          }
          <ul class="atendimentos">
            @for (ag of a.agendamentos; track ag.id) {
              <li class="atendimento" [class.encerrado]="ag.status === 'concluido' || ag.status === 'faltou'">
                <div class="hora-atendimento">
                  <strong>{{ ag.inicio | date: 'HH:mm' }}</strong>
                  <span class="texto-suave pequeno">{{ ag.fim | date: 'HH:mm' }}</span>
                </div>
                <div class="detalhes">
                  <strong>{{ ag.cliente?.nome || 'Cliente' }}</strong>
                  <span class="pequeno">{{ ag.servicos.join(', ') }}</span>
                  @if (ag.observacoes) {<span class="texto-suave pequeno">Obs.: {{ ag.observacoes }}</span>}
                  @if (ag.cliente?.observacoes) {<span class="texto-suave pequeno">Cliente: {{ ag.cliente!.observacoes }}</span>}
                </div>
                <div class="valor">
                  <span class="etiqueta">{{ rotuloStatus[ag.status] }}</span>
                  <span class="pequeno">{{ +ag.valor_total | currency: 'BRL' }}</span>
                </div>
              </li>
            }
          </ul>
        } @else if (!erro()) {
          <div class="vazio">Carregando…</div>
        }
      }

      @if (aba() === 'comissao') {
        <div class="filtro-periodo">
          <select [ngModel]="periodo()" (ngModelChange)="escolherPeriodo($event)" aria-label="Período">
            <option value="mes">Este mês</option>
            <option value="mes_passado">Mês passado</option>
            <option value="outro">Outro período</option>
          </select>
          @if (periodo() === 'outro') {
            <input type="date" [(ngModel)]="inicio" aria-label="De" />
            <input type="date" [(ngModel)]="fim" aria-label="Até" />
            <button class="botao botao-auto" type="button" (click)="carregarComissao()">Ver</button>
          }
        </div>

        @if (comissao(); as c) {
          <div class="destaque">
            <span class="texto-suave pequeno">A receber no período</span>
            <strong class="valor-grande">{{ c.saldo | currency: 'BRL' }}</strong>
            <span class="texto-suave pequeno">{{ inicio + 'T12:00' | date: 'dd/MM' }} a {{ fim + 'T12:00' | date: 'dd/MM/yyyy' }}</span>
          </div>
          <dl class="numeros">
            <div><dt>Atendimentos</dt><dd>{{ c.atendimentos }}</dd></div>
            <div><dt>Faturado em serviços</dt><dd>{{ c.faturado_servicos | currency: 'BRL' }}</dd></div>
            <div><dt>Faturado em produtos</dt><dd>{{ c.faturado_produtos | currency: 'BRL' }}</dd></div>
            <div><dt>Comissão ({{ +c.comissao_percentual / 100 | percent: '1.0-1' }})</dt><dd>{{ c.comissao | currency: 'BRL' }}</dd></div>
            <div><dt>Vales</dt><dd>− {{ c.vales | currency: 'BRL' }}</dd></div>
            <div><dt>Já repassado</dt><dd>− {{ c.repasses | currency: 'BRL' }}</dd></div>
          </dl>
          <p class="texto-suave pequeno">A comissão conta os atendimentos com a comanda paga no período.</p>
        } @else if (!erro()) {
          <div class="vazio">Carregando…</div>
        }
      }
    </div>
  `,
  styles: `
    .painel { max-width: 720px; margin: 0 auto; padding: 16px; min-height: 100dvh; }
    .topo { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
    .topo-textos { display: flex; flex-direction: column; flex: 1; min-width: 0; }
    .avatar { width: 44px; height: 44px; font-size: 18px; }
    .navegacao-dia, .filtro-periodo { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
    .navegacao-dia input, .filtro-periodo input, .filtro-periodo select {
      font: inherit; padding: 8px 10px; border: 1px solid var(--cor-borda-forte); border-radius: var(--radius-inputs);
      background: var(--cor-superficie); color: var(--cor-texto);
    }
    .resumo-dia { margin: 0 0 12px; }
    .resumo-dia strong::first-letter { text-transform: uppercase; }
    .bloqueio { padding: 8px 12px; margin-bottom: 8px; border-radius: var(--radius-inputs); background: var(--cor-superficie-2); color: var(--cor-texto-suave); font-size: 14px; }
    .atendimentos { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
    .atendimento {
      display: grid; grid-template-columns: auto 1fr auto; gap: 12px; align-items: start; padding: 12px 16px;
      background: var(--cor-superficie); border: 1px solid var(--cor-borda); border-radius: var(--radius-cards);
    }
    .atendimento.encerrado { opacity: 0.65; }
    .hora-atendimento, .detalhes, .valor { display: flex; flex-direction: column; gap: 2px; }
    .detalhes { min-width: 0; }
    .valor { align-items: flex-end; }
    .destaque {
      display: flex; flex-direction: column; gap: 4px; padding: 20px; margin-bottom: 12px;
      background: var(--cor-superficie); border: 1px solid var(--cor-borda); border-radius: var(--radius-cards);
    }
    .valor-grande { font-size: 32px; font-weight: 600; color: var(--cor-texto); }
    .numeros { display: grid; gap: 1px; margin: 0 0 12px; border: 1px solid var(--cor-borda); border-radius: var(--radius-cards); overflow: hidden; background: var(--cor-borda); }
    .numeros div { display: flex; justify-content: space-between; gap: 12px; padding: 12px 16px; background: var(--cor-superficie); }
    .numeros dt { color: var(--cor-texto-suave); }
    .numeros dd { margin: 0; font-weight: 500; }
  `,
})
export class PainelBarbeiro implements OnInit {
  protected readonly auth = inject(AuthService);
  private readonly api = inject(BarbeiroService);

  protected readonly rotuloStatus = STATUS_ROTULO;
  protected readonly hoje = iso(new Date());
  protected readonly aba = signal<Aba>('agenda');
  protected readonly data = signal(this.hoje);
  protected readonly agenda = signal<AgendaBarbeiro | null>(null);
  protected readonly comissao = signal<ComissaoBarbeiro | null>(null);
  protected readonly periodo = signal<Periodo>('mes');
  protected readonly erro = signal('');
  protected inicio = this.hoje.slice(0, 8) + '01';
  protected fim = this.hoje;

  protected readonly nome = computed(() => this.agenda()?.barbeiro.nome ?? this.auth.sessao()?.usuario.nome ?? '');
  protected readonly totalDia = computed(() =>
    (this.agenda()?.agendamentos ?? []).reduce((soma, a) => soma + Number(a.valor_total), 0),
  );

  ngOnInit(): void {
    this.carregarAgenda();
  }

  somar(dias: number): string {
    return somarDias(this.data(), dias);
  }

  irPara(data: string): void {
    if (!data) return;
    this.data.set(data);
    this.carregarAgenda();
  }

  abrirComissao(): void {
    this.aba.set('comissao');
    if (!this.comissao()) this.carregarComissao();
  }

  escolherPeriodo(p: Periodo): void {
    this.periodo.set(p);
    const [a, m] = this.hoje.split('-').map(Number);
    if (p === 'mes') {
      [this.inicio, this.fim] = [this.hoje.slice(0, 8) + '01', this.hoje];
    } else if (p === 'mes_passado') {
      [this.inicio, this.fim] = [iso(new Date(a, m - 2, 1)), iso(new Date(a, m - 1, 0))];
    }
    if (p !== 'outro') this.carregarComissao();
  }

  carregarComissao(): void {
    this.erro.set('');
    this.api.comissoes(this.inicio, this.fim).subscribe({
      next: (r) => this.comissao.set(r.barbeiros[0] ?? this.semMovimento()),
      error: (e) => this.erro.set(mensagemDeErro(e)),
    });
  }

  private carregarAgenda(): void {
    this.erro.set('');
    this.api.agenda(this.data()).subscribe({
      next: (a) => this.agenda.set(a),
      error: (e) => this.erro.set(mensagemDeErro(e)),
    });
  }

  /** Barbeiro sem nenhum movimento no período: tudo zerado. */
  private semMovimento(): ComissaoBarbeiro {
    return {
      funcionario_id: 0, nome: this.nome(), comissao_percentual: '0', atendimentos: 0, faturado_servicos: 0,
      faturado_produtos: 0, comissao: 0, vales: 0, repasses: 0, saldo: 0,
    };
  }
}
