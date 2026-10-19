import { CurrencyPipe, DatePipe, DecimalPipe } from '@angular/common';
import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { forkJoin } from 'rxjs';
import { AvisoService } from '../../core/aviso.service';
import { mensagemDeErro } from '../../core/erros';
import { ROTULO_FORMA } from '../../core/financeiro.service';
import { Indicadores, RelatorioClientes, RelatoriosService, ResumoRelatorio } from '../../core/relatorios.service';
import { TelefonePipe } from '../../core/telefone.pipe';
import { BarrasHorizontais, GraficoColunas, ItemBarra, MapaCalor, PontoColuna } from './graficos';

type Atalho = 'hoje' | '7dias' | 'mes' | 'mes_passado' | '3meses' | 'personalizado';

const iso = (d: Date) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

interface Tile {
  rotulo: string;
  valor: string;
  variacao: number | null; // % em relação ao período anterior
  /** true quando subir é bom (faturamento); false quando subir é ruim (faltas). */
  subirEhBom: boolean;
  detalhe?: string;
  destaque?: boolean;
}

/** Relatórios gerenciais da barbearia (somente dono). */
@Component({
  selector: 'app-relatorios',
  imports: [FormsModule, CurrencyPipe, DatePipe, DecimalPipe, TelefonePipe, GraficoColunas, BarrasHorizontais, MapaCalor],
  template: `
    <div class="cabecalho-pagina">
      <div>
        <h1>Relatórios</h1>
        @if (resumo(); as r) {
          <p class="texto-suave">
            {{ r.inicio + 'T12:00:00' | date: 'dd/MM/yyyy' }} a {{ r.fim + 'T12:00:00' | date: 'dd/MM/yyyy' }}
            · comparado com {{ r.periodo_anterior.inicio + 'T12:00:00' | date: 'dd/MM' }} a {{ r.periodo_anterior.fim + 'T12:00:00' | date: 'dd/MM' }}
          </p>
        }
      </div>
    </div>

    <div class="filtros-relatorio">
      <div class="chips" role="group" aria-label="Período">
        @for (a of atalhos; track a.id) {
          <button type="button" class="chip" [class.selecionado]="atalho() === a.id" (click)="escolher(a.id)">{{ a.rotulo }}</button>
        }
      </div>
      <form class="filtro-periodo" (ngSubmit)="aplicarPersonalizado()">
        <label>De <input type="date" name="inicio" [(ngModel)]="inicio" /></label>
        <label>até <input type="date" name="fim" [(ngModel)]="fim" /></label>
        <button class="botao botao-secundario botao-auto" type="submit">Aplicar</button>
      </form>
    </div>

    @if (resumo(); as r) {
      <!-- Indicadores principais -->
      <div class="indicadores indicadores-relatorio">
        @for (t of tiles(); track t.rotulo) {
          <div class="indicador" [class.destaque]="t.destaque">
            <span>{{ t.rotulo }}</span>
            <strong>{{ t.valor }}</strong>
            @if (t.variacao !== null) {
              <small class="variacao" [class.boa]="(t.variacao > 0) === t.subirEhBom && t.variacao !== 0"
                [class.ruim]="(t.variacao > 0) !== t.subirEhBom && t.variacao !== 0">
                {{ t.variacao > 0 ? '▲' : t.variacao < 0 ? '▼' : '•' }} {{ t.variacao | number: '1.0-1' }}% vs anterior
              </small>
            } @else if (t.detalhe) {
              <small>{{ t.detalhe }}</small>
            }
          </div>
        }
      </div>

      <!-- Faturamento ao longo do tempo -->
      <section class="cartao-secao">
        <div class="titulo-secao">
          <h2>Faturamento por {{ r.serie.agrupamento === 'mes' ? 'mês' : 'dia' }}</h2>
          <button class="botao-link" type="button" (click)="tabelaSerie.set(!tabelaSerie())">{{ tabelaSerie() ? 'Ver gráfico' : 'Ver tabela' }}</button>
        </div>
        @if (tabelaSerie()) {
          <div class="tabela-container tabela-rolavel">
            <table class="tabela">
              <thead><tr><th>{{ r.serie.agrupamento === 'mes' ? 'Mês' : 'Dia' }}</th><th>Faturamento</th><th>Atendimentos</th></tr></thead>
              <tbody>
                @for (p of r.serie.pontos; track p.data) {
                  <tr><td>{{ rotuloData(p.data, true) }}</td><td>{{ p.faturamento | currency: 'BRL' }}</td><td>{{ p.atendimentos }}</td></tr>
                }
              </tbody>
            </table>
          </div>
        } @else {
          <app-grafico-colunas [pontos]="pontosSerie()" descricao="Faturamento por período" />
        }
      </section>

      <div class="grade-relatorio">
        <section class="cartao-secao">
          <h2>Formas de pagamento</h2>
          <app-barras-horizontais [itens]="formas()" />
          <p class="texto-suave pequeno">
            Serviços {{ r.indicadores.faturamento_servicos | currency: 'BRL' }} · Produtos {{ r.indicadores.faturamento_produtos | currency: 'BRL' }}
            @if (r.indicadores.descontos > 0) { · Descontos {{ r.indicadores.descontos | currency: 'BRL' }} }
          </p>
        </section>

        <section class="cartao-secao">
          <h2>Resultado estimado</h2>
          <dl class="detalhes detalhes-resultado">
            <dt>Faturamento</dt><dd>{{ r.indicadores.faturamento | currency: 'BRL' }}</dd>
            <dt>Comissões</dt><dd>− {{ r.indicadores.comissoes | currency: 'BRL' }}</dd>
            <dt>Despesas pagas</dt><dd>− {{ r.indicadores.despesas_pagas | currency: 'BRL' }}</dd>
            <dt><strong>Resultado</strong></dt>
            <dd><strong [class.negativo]="r.indicadores.resultado < 0">{{ r.indicadores.resultado | currency: 'BRL' }}</strong></dd>
          </dl>
          <p class="texto-suave pequeno">Estimativa: não inclui despesas a pagar nem impostos.</p>
        </section>
      </div>

      <!-- Barbeiros -->
      <section class="cartao-secao">
        <h2>Desempenho por barbeiro</h2>
        @if (!r.barbeiros.length) {
          <p class="texto-suave">Sem atendimentos no período.</p>
        } @else {
          <div class="tabela-container">
            <table class="tabela">
              <thead>
                <tr><th>Barbeiro</th><th>Atendimentos</th><th>Faltas</th><th>Faturamento</th><th>Ticket médio</th><th>Comissão</th><th>Ocupação da agenda</th></tr>
              </thead>
              <tbody>
                @for (b of r.barbeiros; track b.funcionario_id) {
                  <tr>
                    <td><strong>{{ b.nome }}</strong></td>
                    <td>{{ b.concluidos }}</td>
                    <td>{{ b.faltas }} <span class="texto-suave pequeno">({{ b.taxa_faltas | number: '1.0-1' }}%)</span></td>
                    <td>{{ b.faturamento | currency: 'BRL' }}</td>
                    <td>{{ b.ticket_medio | currency: 'BRL' }}</td>
                    <td>{{ b.comissao | currency: 'BRL' }}</td>
                    <td>
                      <span class="medidor" [attr.aria-label]="'Ocupação ' + b.ocupacao + '%'">
                        <span class="medidor-trilho"><span class="medidor-barra" [style.width.%]="b.ocupacao"></span></span>
                        {{ b.ocupacao | number: '1.0-1' }}%
                      </span>
                    </td>
                  </tr>
                }
              </tbody>
            </table>
          </div>
          <p class="texto-suave pequeno">Ocupação = tempo agendado ÷ tempo de expediente no período.</p>
        }
      </section>

      <div class="grade-relatorio">
        <section class="cartao-secao">
          <h2>Serviços mais vendidos</h2>
          <app-barras-horizontais [itens]="servicos()" />
        </section>
        <section class="cartao-secao">
          <h2>Produtos mais vendidos</h2>
          <app-barras-horizontais [itens]="produtos()" />
        </section>
      </div>

      <section class="cartao-secao">
        <h2>Horários de maior movimento</h2>
        <p class="texto-suave pequeno">Agendamentos por dia da semana e hora — útil para escalar a equipe e criar promoções nos horários vazios.</p>
        <app-mapa-calor [dados]="r.horarios_pico" />
      </section>
    } @else {
      <p class="texto-suave">Carregando relatório…</p>
    }

    <!-- Clientes -->
    @if (clientes(); as c) {
      <section class="cartao-secao">
        <div class="titulo-secao">
          <h2>Clientes</h2>
        </div>
        <div class="indicadores">
          <div class="indicador"><span>Atendidos no período</span><strong>{{ c.atendidos }}</strong></div>
          <div class="indicador"><span>Novos</span><strong>{{ c.novos }}</strong></div>
          <div class="indicador"><span>Voltaram</span><strong>{{ c.recorrentes }}</strong></div>
          <div class="indicador destaque"><span>Sumidos (sem agendamento futuro)</span><strong>{{ c.sumidos.length }}</strong></div>
        </div>

        <div class="titulo-secao">
          <h3>Clientes que não voltam há mais de</h3>
          <select [ngModel]="diasSemVoltar()" (ngModelChange)="mudarDias(+$event)" aria-label="Dias sem voltar">
            @for (d of [30, 45, 60, 90]; track d) {<option [value]="d">{{ d }} dias</option>}
          </select>
        </div>
        @if (!c.sumidos.length) {
          <p class="texto-suave">Nenhum cliente sumido. Ótimo sinal!</p>
        } @else {
          <div class="tabela-container">
            <table class="tabela">
              <thead><tr><th>Cliente</th><th>Última visita</th><th>Visitas</th><th>Total gasto</th><th>Último barbeiro</th><th></th></tr></thead>
              <tbody>
                @for (s of c.sumidos; track s.id) {
                  <tr>
                    <td><strong>{{ s.nome }}</strong><div class="texto-suave pequeno">{{ s.telefone | telefone }}</div></td>
                    <td>{{ s.ultima_visita + 'T12:00:00' | date: 'dd/MM/yyyy' }}<div class="texto-suave pequeno">há {{ s.dias_sem_vir }} dias</div></td>
                    <td>{{ s.visitas }}</td>
                    <td>{{ s.total_gasto | currency: 'BRL' }}</td>
                    <td>{{ s.ultimo_barbeiro ?? '—' }}</td>
                    <td class="acoes">
                      <a class="botao-link" [href]="linkWhatsapp(s.nome, s.telefone)" target="_blank" rel="noopener"
                        [title]="s.aceita_whatsapp ? 'Abrir conversa no WhatsApp' : 'Cliente não autorizou mensagens automáticas'">
                        Chamar no WhatsApp
                      </a>
                    </td>
                  </tr>
                }
              </tbody>
            </table>
          </div>
        }
      </section>
    }
  `,
})
export class Relatorios implements OnInit {
  private readonly api = inject(RelatoriosService);
  private readonly aviso = inject(AvisoService);

  protected readonly atalhos: { id: Atalho; rotulo: string }[] = [
    { id: 'hoje', rotulo: 'Hoje' },
    { id: '7dias', rotulo: 'Últimos 7 dias' },
    { id: 'mes', rotulo: 'Este mês' },
    { id: 'mes_passado', rotulo: 'Mês passado' },
    { id: '3meses', rotulo: 'Últimos 3 meses' },
  ];
  protected readonly atalho = signal<Atalho>('mes');
  protected inicio = '';
  protected fim = '';
  protected readonly diasSemVoltar = signal(45);
  protected readonly resumo = signal<ResumoRelatorio | null>(null);
  protected readonly clientes = signal<RelatorioClientes | null>(null);
  protected readonly tabelaSerie = signal(false);

  protected readonly tiles = computed<Tile[]>(() => {
    const r = this.resumo();
    if (!r) return [];
    const a = r.indicadores;
    const b = r.anterior;
    const brl = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    return [
      { rotulo: 'Faturamento', valor: brl(a.faturamento), variacao: this.variacao(a, b, 'faturamento'), subirEhBom: true, destaque: true },
      { rotulo: 'Atendimentos concluídos', valor: String(a.concluidos), variacao: this.variacao(a, b, 'concluidos'), subirEhBom: true },
      { rotulo: 'Ticket médio', valor: brl(a.ticket_medio), variacao: this.variacao(a, b, 'ticket_medio'), subirEhBom: true },
      { rotulo: 'Taxa de faltas', valor: `${a.taxa_faltas.toLocaleString('pt-BR')}%`, variacao: this.variacao(a, b, 'taxa_faltas'), subirEhBom: false },
      {
        rotulo: 'Agendamentos', valor: String(a.agendamentos), variacao: null, subirEhBom: true,
        detalhe: `${a.cancelados} cancelado(s) · ${a.agendados_online} online`,
      },
      { rotulo: 'Resultado estimado', valor: brl(a.resultado), variacao: this.variacao(a, b, 'resultado'), subirEhBom: true },
    ];
  });

  protected readonly pontosSerie = computed<PontoColuna[]>(() =>
    (this.resumo()?.serie.pontos ?? []).map((p) => ({
      rotulo: this.rotuloData(p.data, false),
      detalhe: this.rotuloData(p.data, true),
      valor: p.faturamento,
      extra: `${p.atendimentos} atendimento(s)`,
    })),
  );

  protected readonly formas = computed<ItemBarra[]>(() =>
    (this.resumo()?.formas_pagamento ?? []).map((f) => ({ nome: ROTULO_FORMA[f.forma], valor: f.total })),
  );
  protected readonly servicos = computed<ItemBarra[]>(() =>
    (this.resumo()?.servicos ?? []).map((s) => ({ nome: s.nome, valor: s.receita, detalhe: `${s.quantidade}×` })),
  );
  protected readonly produtos = computed<ItemBarra[]>(() =>
    (this.resumo()?.produtos ?? []).map((s) => ({ nome: s.nome, valor: s.receita, detalhe: `${s.quantidade} un.` })),
  );

  ngOnInit(): void {
    this.escolher('mes');
  }

  escolher(atalho: Atalho): void {
    const hoje = new Date();
    let ini = new Date(hoje);
    let fim = new Date(hoje);
    if (atalho === '7dias') ini.setDate(hoje.getDate() - 6);
    if (atalho === 'mes') ini = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    if (atalho === 'mes_passado') {
      ini = new Date(hoje.getFullYear(), hoje.getMonth() - 1, 1);
      fim = new Date(hoje.getFullYear(), hoje.getMonth(), 0);
    }
    if (atalho === '3meses') ini = new Date(hoje.getFullYear(), hoje.getMonth() - 2, 1);
    this.atalho.set(atalho);
    this.inicio = iso(ini);
    this.fim = iso(fim);
    this.carregar();
  }

  aplicarPersonalizado(): void {
    if (!this.inicio || !this.fim) return;
    this.atalho.set('personalizado');
    this.carregar();
  }

  mudarDias(dias: number): void {
    this.diasSemVoltar.set(dias);
    this.api.clientes(this.inicio, this.fim, dias).subscribe((c) => this.clientes.set(c));
  }

  carregar(): void {
    forkJoin({
      resumo: this.api.resumo(this.inicio, this.fim),
      clientes: this.api.clientes(this.inicio, this.fim, this.diasSemVoltar()),
    }).subscribe({
      next: ({ resumo, clientes }) => {
        this.resumo.set(resumo);
        this.clientes.set(clientes);
      },
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }

  /** "15/10" ou "out/26" (eixo); "qua, 15 de out" ou "outubro de 2026" (dica e tabela). */
  rotuloData(data: string, completo: boolean): string {
    const porMes = data.length === 7;
    const d = new Date((porMes ? data + '-15' : data) + 'T12:00:00');
    if (porMes) {
      return completo
        ? d.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
        : d.toLocaleDateString('pt-BR', { month: 'short', year: '2-digit' });
    }
    return completo
      ? d.toLocaleDateString('pt-BR', { weekday: 'short', day: 'numeric', month: 'short' })
      : d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
  }

  linkWhatsapp(nome: string, telefone: string): string {
    const primeiro = nome.split(' ')[0];
    const texto = `Olá, ${primeiro}! Sentimos sua falta aqui na barbearia. Que tal agendar um horário esta semana?`;
    return `https://wa.me/55${telefone}?text=${encodeURIComponent(texto)}`;
  }

  private variacao(a: Indicadores, b: Indicadores, campo: keyof Indicadores): number | null {
    const atual = a[campo];
    const antes = b[campo];
    if (!antes) return null; // sem base de comparação
    return Math.round(((atual - antes) / Math.abs(antes)) * 1000) / 10;
  }
}
