import { Component, computed, input, signal } from '@angular/core';

/** Formata valores do eixo de forma compacta: 950 · 1,2 mil · 3,4 mi. */
export function compacto(valor: number, moeda = false): string {
  const prefixo = moeda ? 'R$ ' : '';
  if (Math.abs(valor) >= 1_000_000) return `${prefixo}${(valor / 1_000_000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} mi`;
  if (Math.abs(valor) >= 1_000) return `${prefixo}${(valor / 1_000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} mil`;
  return `${prefixo}${valor.toLocaleString('pt-BR', { maximumFractionDigits: 0 })}`;
}

export function moeda(valor: number): string {
  return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

/** Teto "redondo" para o eixo (ex.: 437 → 500) e 4 divisões. */
function escala(maximo: number): number[] {
  if (maximo <= 0) return [0, 1];
  const bruto = maximo / 4;
  const potencia = 10 ** Math.floor(Math.log10(bruto));
  const passo = [1, 2, 2.5, 5, 10].map((m) => m * potencia).find((p) => p >= bruto) ?? bruto;
  return [0, 1, 2, 3, 4].map((i) => i * passo);
}

export interface PontoColuna {
  rotulo: string; // texto no eixo X
  detalhe: string; // título da dica (ex.: data completa)
  valor: number;
  extra?: string; // linha adicional da dica
}

/**
 * Colunas de uma única série (um tom), com eixo Y compacto,
 * linhas de grade discretas e dica ao passar o mouse.
 */
@Component({
  selector: 'app-grafico-colunas',
  template: `
    <div class="grafico" (mouseleave)="ativo.set(null)">
      <svg [attr.viewBox]="'0 0 ' + largura + ' ' + altura" role="img" [attr.aria-label]="descricao()">
        @for (t of ticks(); track t) {
          <line class="grade" [attr.x1]="margemEsq" [attr.x2]="largura" [attr.y1]="y(t)" [attr.y2]="y(t)" />
          <text class="eixo" [attr.x]="margemEsq - 6" [attr.y]="y(t) + 4" text-anchor="end">{{ formatarEixo(t) }}</text>
        }
        @for (p of pontos(); track $index) {
          <!-- Área de toque maior que a barra -->
          <rect class="alvo" [attr.x]="xBanda($index)" y="0" [attr.width]="banda()" [attr.height]="altura - margemInf"
            (mouseenter)="ativo.set($index)" (focus)="ativo.set($index)" tabindex="0"
            [attr.aria-label]="p.detalhe + ': ' + formatarValor(p.valor)" />
          @if (p.valor > 0) {
            <path class="barra" [class.apagada]="ativo() !== null && ativo() !== $index" [attr.d]="barra($index, p.valor)" />
          }
          @if (mostrarRotulo($index)) {
            <text class="eixo" [attr.x]="xBanda($index) + banda() / 2" [attr.y]="altura - 6" text-anchor="middle">{{ p.rotulo }}</text>
          }
        }
      </svg>
      @if (ativo() !== null) {
        @let p = pontos()[ativo()!];
        <div class="dica" [style.left.%]="(xBanda(ativo()!) + banda() / 2) / largura * 100">
          <strong>{{ p.detalhe }}</strong>
          <span>{{ formatarValor(p.valor) }}</span>
          @if (p.extra) {<small>{{ p.extra }}</small>}
        </div>
      }
    </div>
  `,
})
export class GraficoColunas {
  readonly pontos = input.required<PontoColuna[]>();
  readonly emMoeda = input(true);
  readonly descricao = input('Gráfico de colunas');

  protected readonly largura = 720;
  protected readonly altura = 240;
  protected readonly margemEsq = 64;
  protected readonly margemInf = 26;
  protected readonly ativo = signal<number | null>(null);

  protected readonly ticks = computed(() => escala(Math.max(...this.pontos().map((p) => p.valor), 0)));
  private readonly teto = computed(() => this.ticks()[this.ticks().length - 1]);
  protected readonly banda = computed(() => (this.largura - this.margemEsq) / Math.max(this.pontos().length, 1));

  protected y(v: number): number {
    const util = this.altura - this.margemInf - 10;
    return 10 + util - (v / this.teto()) * util;
  }

  protected xBanda(i: number): number {
    return this.margemEsq + i * this.banda();
  }

  /** Coluna com no máximo 24px, cantos arredondados no topo e base reta. */
  protected barra(i: number, v: number): string {
    const largura = Math.min(24, Math.max(this.banda() - 2, 2));
    const x = this.xBanda(i) + (this.banda() - largura) / 2;
    const base = this.y(0);
    const topo = this.y(v);
    const r = Math.min(4, largura / 2, base - topo);
    return `M${x},${base} V${topo + r} Q${x},${topo} ${x + r},${topo} H${x + largura - r} Q${x + largura},${topo} ${x + largura},${topo + r} V${base} Z`;
  }

  /** Mostra rótulos do eixo X espaçados para não encavalar. */
  protected mostrarRotulo(i: number): boolean {
    const n = this.pontos().length;
    const passo = Math.ceil(n / 12);
    return i % passo === 0 || i === n - 1;
  }

  protected formatarEixo(v: number): string {
    return compacto(v, this.emMoeda());
  }

  protected formatarValor(v: number): string {
    return this.emMoeda() ? moeda(v) : v.toLocaleString('pt-BR');
  }
}

export interface ItemBarra {
  nome: string;
  valor: number;
  detalhe?: string;
}

/** Barras horizontais de uma série, com valor na ponta (nomes longos cabem melhor). */
@Component({
  selector: 'app-barras-horizontais',
  template: `
    <ul class="barras-h">
      @for (item of itens(); track item.nome) {
        <li [title]="item.nome + ': ' + formatar(item.valor) + (item.detalhe ? ' · ' + item.detalhe : '')">
          <span class="barras-h-nome">{{ item.nome }}</span>
          <span class="barras-h-trilho">
            <span class="barras-h-barra" [style.width.%]="maximo() ? (item.valor / maximo()) * 100 : 0"></span>
          </span>
          <span class="barras-h-valor">
            {{ formatar(item.valor) }}
            @if (item.detalhe) {<small>{{ item.detalhe }}</small>}
          </span>
        </li>
      } @empty {
        <li class="texto-suave">Sem dados no período.</li>
      }
    </ul>
  `,
})
export class BarrasHorizontais {
  readonly itens = input.required<ItemBarra[]>();
  readonly emMoeda = input(true);
  protected readonly maximo = computed(() => Math.max(...this.itens().map((i) => i.valor), 0));

  protected formatar(v: number): string {
    return this.emMoeda() ? moeda(v) : v.toLocaleString('pt-BR');
  }
}

const DIAS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
// Semana começando na segunda, como a barbearia enxerga
const ORDEM_DIAS = [1, 2, 3, 4, 5, 6, 0];

/** Mapa de calor dia da semana × hora (sequencial, um tom: mais escuro = mais movimento). */
@Component({
  selector: 'app-mapa-calor',
  template: `
    @if (horas().length === 0) {
      <p class="texto-suave">Sem agendamentos no período.</p>
    } @else {
      <div class="mapa-calor" [style.grid-template-columns]="'44px repeat(' + horas().length + ', minmax(22px, 1fr))'" role="table"
        aria-label="Agendamentos por dia da semana e hora">
        <span></span>
        @for (h of horas(); track h) {<span class="mapa-hora" role="columnheader">{{ h }}h</span>}
        @for (d of ordemDias; track d) {
          <span class="mapa-dia" role="rowheader">{{ dias[d] }}</span>
          @for (h of horas(); track h) {
            @let q = valor(d, h);
            <span class="mapa-celula" role="cell" [style.--intensidade]="maximo() ? q / maximo() : 0" [class.vazia]="q === 0"
              [title]="dias[d] + ', ' + h + 'h: ' + q + ' agendamento(s)'" [attr.aria-label]="dias[d] + ' ' + h + 'h: ' + q"></span>
          }
        }
      </div>
      <div class="mapa-legenda" aria-hidden="true">
        <span>menos</span><span class="mapa-gradiente"></span><span>mais movimento</span>
      </div>
    }
  `,
})
export class MapaCalor {
  readonly dados = input.required<{ dia_semana: number; hora: number; quantidade: number }[]>();
  protected readonly dias = DIAS;
  protected readonly ordemDias = ORDEM_DIAS;

  protected readonly horas = computed(() => {
    const hs = this.dados().map((d) => d.hora);
    if (!hs.length) return [];
    const lista: number[] = [];
    for (let h = Math.min(...hs); h <= Math.max(...hs); h++) lista.push(h);
    return lista;
  });
  private readonly mapa = computed(() => new Map(this.dados().map((d) => [`${d.dia_semana}-${d.hora}`, d.quantidade])));
  protected readonly maximo = computed(() => Math.max(...this.dados().map((d) => d.quantidade), 0));

  protected valor(dia: number, hora: number): number {
    return this.mapa().get(`${dia}-${hora}`) ?? 0;
  }
}
