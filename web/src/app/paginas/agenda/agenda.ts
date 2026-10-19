import { CurrencyPipe, DatePipe } from '@angular/common';
import { Component, computed, ElementRef, inject, OnDestroy, OnInit, signal, viewChild } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import {
  AgendaDia,
  AgendamentoDoDia,
  AgendaService,
  ColunaBarbeiro,
  hora,
  minutosDeHora,
  StatusAgendamento,
  STATUS_ROTULO,
} from '../../core/agenda.service';
import { AuthService } from '../../core/auth.service';
import { AvisoService } from '../../core/aviso.service';
import { mensagemDeErro } from '../../core/erros';
import { TelefonePipe } from '../../core/telefone.pipe';
import { FormularioAgendamento, InicioFormulario } from './formulario-agendamento';

const PX_POR_HORA = 76;
const PX_POR_MINUTO = PX_POR_HORA / 60;
const ATUALIZAR_A_CADA_MS = 60_000;

interface Faixa {
  topo: number;
  altura: number;
}

interface Cartao extends Faixa {
  ag: AgendamentoDoDia;
  coluna: number; // posição lado a lado quando há horários sobrepostos
  colunas: number;
  compacto: boolean;
}

interface ColunaVisivel {
  barbeiro: ColunaBarbeiro;
  indisponivel: Faixa[];
  bloqueios: (Faixa & { motivo: string | null })[];
  cartoes: Cartao[];
  destaque: { rotulo: string; ag: AgendamentoDoDia } | null;
  meu: boolean;
}

interface Acao {
  status: StatusAgendamento;
  rotulo: string;
  estilo: 'primario' | 'secundario' | 'perigo';
}

const ACOES: Partial<Record<StatusAgendamento, Acao[]>> = {
  agendado: [
    { status: 'confirmado', rotulo: 'Confirmar presença', estilo: 'secundario' },
    { status: 'em_atendimento', rotulo: 'Iniciar atendimento', estilo: 'primario' },
    { status: 'faltou', rotulo: 'Cliente faltou', estilo: 'secundario' },
    { status: 'cancelado', rotulo: 'Cancelar', estilo: 'perigo' },
  ],
  confirmado: [
    { status: 'em_atendimento', rotulo: 'Iniciar atendimento', estilo: 'primario' },
    { status: 'faltou', rotulo: 'Cliente faltou', estilo: 'secundario' },
    { status: 'cancelado', rotulo: 'Cancelar', estilo: 'perigo' },
  ],
  em_atendimento: [{ status: 'concluido', rotulo: 'Concluir atendimento', estilo: 'primario' }],
  concluido: [{ status: 'confirmado', rotulo: 'Reabrir', estilo: 'secundario' }],
  faltou: [{ status: 'confirmado', rotulo: 'Desfazer falta', estilo: 'secundario' }],
};

@Component({
  selector: 'app-agenda',
  imports: [FormsModule, RouterLink, CurrencyPipe, DatePipe, TelefonePipe, FormularioAgendamento],
  template: `
    <div class="agenda-barra">
      <div class="agenda-navegacao">
        <button class="botao-icone-borda" type="button" (click)="mudarDia(-1)" aria-label="Dia anterior">‹</button>
        <label class="agenda-data">
          <span>{{ dataExibida() | date: "EEEE, d 'de' MMMM" }}</span>
          <input type="date" [ngModel]="data()" (ngModelChange)="irPara($event)" aria-label="Escolher data" />
        </label>
        <button class="botao-icone-borda" type="button" (click)="mudarDia(1)" aria-label="Próximo dia">›</button>
        @if (!ehHoje()) {
          <button class="botao botao-secundario botao-auto" type="button" (click)="irPara(hoje())">Hoje</button>
        }
      </div>

      <div class="agenda-filtros">
        <select [ngModel]="filtro()" (ngModelChange)="filtro.set($event)" aria-label="Filtrar barbeiro">
          <option value="todos">Todos os barbeiros</option>
          @for (b of agenda()?.barbeiros ?? []; track b.id) {
            <option [value]="'' + b.id">{{ b.nome }}{{ b.id === agenda()?.meu_funcionario_id ? ' (eu)' : '' }}</option>
          }
        </select>
        <button class="botao-icone-borda" type="button" (click)="carregar()" title="Atualizar" aria-label="Atualizar">↻</button>
        <button class="botao botao-auto" type="button" (click)="novo()">+ Agendamento</button>
      </div>
    </div>

    @if (agenda(); as a) {
      <div class="agenda-resumo">
        <span class="resumo-item"><strong>{{ resumo().total }}</strong> agendamentos</span>
        <span class="resumo-item"><span class="bolinha st-em_atendimento"></span><strong>{{ resumo().emAtendimento }}</strong> em atendimento</span>
        <span class="resumo-item"><span class="bolinha st-concluido"></span><strong>{{ resumo().concluidos }}</strong> concluídos</span>
        <span class="resumo-item"><span class="bolinha st-faltou"></span><strong>{{ resumo().faltas }}</strong> faltas</span>
        <span class="resumo-item resumo-valor">Previsto: <strong>{{ resumo().previsto | currency: 'BRL' }}</strong></span>
      </div>

      @if (a.barbeiros.length === 0) {
        <div class="cartao-secao agenda-vazia">
          <h2>Vamos montar sua agenda</h2>
          <p class="texto-suave">A agenda mostra uma coluna para cada barbeiro. Para começar:</p>
          <ol class="passos">
            <li><a routerLink="/configuracoes/servicos">Cadastre os serviços</a> com duração e preço.</li>
            <li><a routerLink="/configuracoes/barbeiros">Cadastre os barbeiros</a>, os serviços que cada um faz e o expediente.</li>
            <li><a routerLink="/clientes">Cadastre seus clientes</a>.</li>
          </ol>
        </div>
      } @else {
        <div class="agenda-grade" #grade>
          <div class="agenda-cabecalho" [style.grid-template-columns]="colunasCss()">
            <div class="agenda-canto"></div>
            @for (c of colunas(); track c.barbeiro.id) {
              <div class="agenda-barbeiro" [class.meu]="c.meu">
                @if (c.barbeiro.foto_url) {
                  <img class="avatar avatar-pequeno" [src]="c.barbeiro.foto_url" alt="" />
                } @else {
                  <span class="avatar avatar-pequeno avatar-inicial">{{ c.barbeiro.nome.charAt(0) }}</span>
                }
                <div class="agenda-barbeiro-textos">
                  <strong>{{ c.barbeiro.nome }}{{ c.meu ? ' (eu)' : '' }}</strong>
                  @if (c.destaque; as d) {
                    <button type="button" class="proximo" [class.agora]="d.ag.status === 'em_atendimento'" (click)="abrir(d.ag, c.barbeiro)">
                      {{ d.rotulo }}: <b>{{ d.ag.cliente?.nome }}</b> · {{ hora(d.ag.inicio) }}
                    </button>
                  } @else if (c.barbeiro.expediente.length === 0) {
                    <small class="texto-suave">Folga</small>
                  } @else {
                    <small class="texto-suave">Sem próximos clientes</small>
                  }
                </div>
              </div>
            }
          </div>

          <div class="agenda-corpo" [style.grid-template-columns]="colunasCss()" [style.height.px]="alturaTotal()">
            <div class="agenda-horas">
              @for (h of horas(); track h) {
                <span class="agenda-hora" [style.top.px]="(h - inicioGrade()) * pxMin">{{ rotuloHora(h) }}</span>
              }
            </div>

            @for (c of colunas(); track c.barbeiro.id) {
              <div class="agenda-coluna" (click)="clicarHorario($event, c.barbeiro)">
                @for (h of horas(); track h) {
                  <span class="linha-hora" [style.top.px]="(h - inicioGrade()) * pxMin"></span>
                  <span class="linha-meia" [style.top.px]="(h + 30 - inicioGrade()) * pxMin"></span>
                }
                @for (f of c.indisponivel; track $index) {
                  <div class="faixa-indisponivel" [style.top.px]="f.topo" [style.height.px]="f.altura"></div>
                }
                @for (b of c.bloqueios; track $index) {
                  <div class="faixa-bloqueio" [style.top.px]="b.topo" [style.height.px]="b.altura" (click)="$event.stopPropagation()">
                    <span>{{ b.motivo || 'Bloqueado' }}</span>
                  </div>
                }
                @for (k of c.cartoes; track k.ag.id) {
                  <button type="button" class="cartao-agendamento st-{{ k.ag.status }}" [class.compacto]="k.compacto"
                    [style.top.px]="k.topo" [style.height.px]="k.altura"
                    [style.left]="'calc(' + (100 / k.colunas) * k.coluna + '% + 3px)'"
                    [style.width]="'calc(' + 100 / k.colunas + '% - 6px)'"
                    [attr.aria-label]="(k.ag.cliente?.nome ?? 'Cliente') + ', ' + k.ag.servicos.join(' + ') + ', ' + hora(k.ag.inicio) + ' às ' + hora(k.ag.fim) + ', ' + statusRotulo[k.ag.status]"
                    (click)="$event.stopPropagation(); abrir(k.ag, c.barbeiro)">
                    <span class="cartao-cliente">{{ k.ag.cliente?.nome ?? 'Cliente' }}</span>
                    <span class="cartao-servico">{{ k.ag.servicos.join(' + ') }}</span>
                    <span class="cartao-hora">{{ hora(k.ag.inicio) }} – {{ hora(k.ag.fim) }}</span>
                    @if (k.ag.origem === 'link_publico') {
                      <span class="cartao-origem" title="Agendado pela página da barbearia">online</span>
                    }
                  </button>
                }
                @if (agoraMin() !== null && agoraMin()! >= inicioGrade() && agoraMin()! <= fimGrade()) {
                  <div class="linha-agora" [style.top.px]="(agoraMin()! - inicioGrade()) * pxMin"></div>
                }
              </div>
            }
          </div>
        </div>

        <div class="agenda-legenda">
          @for (s of legenda; track s) {
            <span><span class="bolinha st-{{ s }}"></span>{{ statusRotulo[s] }}</span>
          }
          <span><span class="bolinha bolinha-bloqueio"></span>Bloqueio / folga</span>
        </div>
      }
    } @else {
      <p class="texto-suave">Carregando agenda…</p>
    }

    @if (selecionado(); as sel) {
      <div class="fundo-modal" (click)="selecionado.set(null)"></div>
      <aside class="painel-lateral detalhe-agendamento">
        <span class="etiqueta-status st-{{ sel.ag.status }}">{{ statusRotulo[sel.ag.status] }}</span>
        <h2>{{ sel.ag.cliente?.nome }}</h2>
        <p class="texto-suave">{{ sel.ag.servicos.join(' + ') }}</p>

        <dl class="detalhes">
          <dt>Horário</dt><dd>{{ hora(sel.ag.inicio) }} – {{ hora(sel.ag.fim) }}</dd>
          <dt>Barbeiro</dt><dd>{{ sel.barbeiro.nome }}</dd>
          <dt>Valor</dt><dd>{{ +sel.ag.valor_total | currency: 'BRL' }}</dd>
          <dt>Origem</dt><dd>{{ sel.ag.origem === 'link_publico' ? 'Página da barbearia' : sel.ag.origem === 'whatsapp' ? 'WhatsApp' : 'Recepção' }}</dd>
          @if (sel.ag.cliente?.telefone) {
            <dt>WhatsApp</dt>
            <dd><a [href]="'https://wa.me/55' + sel.ag.cliente!.telefone" target="_blank" rel="noopener">{{ sel.ag.cliente!.telefone | telefone }}</a></dd>
          }
          @if (sel.ag.cliente?.observacoes) {
            <dt>Preferências</dt><dd>{{ sel.ag.cliente!.observacoes }}</dd>
          }
          @if (sel.ag.observacoes) {
            <dt>Observações</dt><dd>{{ sel.ag.observacoes }}</dd>
          }
        </dl>

        @if (podeAlterar(sel.barbeiro)) {
          <div class="acoes-agendamento">
            @if (sel.ag.status === 'agendado' || sel.ag.status === 'confirmado') {
              <button type="button" class="botao botao-secundario" (click)="remarcar(sel.ag, sel.barbeiro)">Remarcar</button>
            }
            @for (acao of acoes(sel.ag.status); track acao.status) {
              <button type="button" [disabled]="alterando()"
                [class]="acao.estilo === 'primario' ? 'botao' : acao.estilo === 'perigo' ? 'botao botao-perigo' : 'botao botao-secundario'"
                (click)="alterarStatus(sel.ag, acao.status)">{{ acao.rotulo }}</button>
            }
          </div>
        } @else {
          <p class="texto-suave pequeno">Somente o barbeiro responsável, a recepção ou o dono alteram este atendimento.</p>
        }
        <div class="rodape-painel">
          <button class="botao botao-secundario botao-auto" type="button" (click)="selecionado.set(null)">Fechar</button>
        </div>
      </aside>
    }

    @if (formulario(); as f) {
      <app-formulario-agendamento [inicio]="f" (fechar)="formulario.set(null)" (salvo)="agendamentoSalvo()" />
    }
  `,
})
export class Agenda implements OnInit, OnDestroy {
  private readonly api = inject(AgendaService);
  private readonly aviso = inject(AvisoService);
  private readonly auth = inject(AuthService);
  private readonly grade = viewChild<ElementRef<HTMLElement>>('grade');

  protected readonly pxMin = PX_POR_MINUTO;
  protected readonly hora = hora;
  protected readonly statusRotulo = STATUS_ROTULO;
  protected readonly legenda: StatusAgendamento[] = ['agendado', 'confirmado', 'em_atendimento', 'concluido', 'faltou'];

  protected readonly agenda = signal<AgendaDia | null>(null);
  protected readonly data = signal('');
  protected readonly filtro = signal('todos');
  protected readonly selecionado = signal<{ ag: AgendamentoDoDia; barbeiro: ColunaBarbeiro } | null>(null);
  protected readonly alterando = signal(false);
  protected readonly formulario = signal<InicioFormulario | null>(null);
  /** Minutos do relógio da barbearia, avançados localmente entre as atualizações. */
  private readonly relogio = signal<{ min: number; em: number } | null>(null);
  private readonly tique = signal(Date.now());
  private timer?: ReturnType<typeof setInterval>;
  private rolouParaAgora = false;

  protected readonly hoje = computed(() => this.agenda()?.agora.slice(0, 10) ?? '');
  protected readonly ehHoje = computed(() => !!this.agenda() && this.data() === this.hoje());
  protected readonly dataExibida = computed(() => (this.data() ? new Date(this.data() + 'T12:00:00') : new Date()));

  protected readonly agoraMin = computed(() => {
    const r = this.relogio();
    if (!r || !this.ehHoje()) return null;
    return r.min + Math.floor((this.tique() - r.em) / 60000);
  });

  private readonly barbeirosVisiveis = computed(() => {
    const a = this.agenda();
    if (!a) return [];
    const f = this.filtro();
    return f === 'todos' ? a.barbeiros : a.barbeiros.filter((b) => String(b.id) === f);
  });

  /** Primeira e última hora da grade (expediente e agendamentos do dia). */
  protected readonly inicioGrade = computed(() => {
    const mins = this.barbeirosVisiveis().flatMap((b) => [
      ...b.expediente.map((e) => minutosDeHora(e.inicio)),
      ...b.agendamentos.map((ag) => this.minNoDia(ag.inicio)),
    ]);
    return mins.length ? Math.floor(Math.min(...mins) / 60) * 60 : 8 * 60;
  });

  protected readonly fimGrade = computed(() => {
    const mins = this.barbeirosVisiveis().flatMap((b) => [
      ...b.expediente.map((e) => minutosDeHora(e.fim)),
      ...b.agendamentos.map((ag) => this.minNoDia(ag.fim)),
    ]);
    return mins.length ? Math.ceil(Math.max(...mins) / 60) * 60 : 20 * 60;
  });

  protected readonly alturaTotal = computed(() => (this.fimGrade() - this.inicioGrade()) * PX_POR_MINUTO);

  protected readonly horas = computed(() => {
    const lista: number[] = [];
    for (let m = this.inicioGrade(); m < this.fimGrade(); m += 60) lista.push(m);
    return lista;
  });

  protected readonly colunasCss = computed(
    () => `64px repeat(${this.barbeirosVisiveis().length}, minmax(${this.barbeirosVisiveis().length > 1 ? 210 : 280}px, 1fr))`,
  );

  protected readonly colunas = computed<ColunaVisivel[]>(() => {
    const ini = this.inicioGrade();
    const fim = this.fimGrade();
    const agora = this.agoraMin();
    const meuId = this.agenda()?.meu_funcionario_id;
    const faixa = (a: number, b: number): Faixa => ({
      topo: (Math.max(a, ini) - ini) * PX_POR_MINUTO,
      altura: (Math.min(b, fim) - Math.max(a, ini)) * PX_POR_MINUTO,
    });

    return this.barbeirosVisiveis().map((barbeiro) => {
      // Fora do expediente fica sombreado
      const indisponivel: Faixa[] = [];
      let cursor = ini;
      const expediente = [...barbeiro.expediente].sort((x, y) => x.inicio.localeCompare(y.inicio));
      for (const e of expediente) {
        const a = minutosDeHora(e.inicio);
        if (a > cursor) indisponivel.push(faixa(cursor, a));
        cursor = Math.max(cursor, minutosDeHora(e.fim));
      }
      if (cursor < fim) indisponivel.push(faixa(cursor, fim));

      const bloqueios = barbeiro.bloqueios
        .map((b) => ({ ...faixa(this.minNoDia(b.inicio), this.minNoDia(b.fim)), motivo: b.motivo }))
        .filter((b) => b.altura > 0);

      return {
        barbeiro,
        indisponivel: indisponivel.filter((f) => f.altura > 0),
        bloqueios,
        cartoes: this.posicionar(barbeiro.agendamentos, ini),
        destaque: this.destaque(barbeiro.agendamentos, agora),
        meu: barbeiro.id === meuId,
      };
    });
  });

  protected readonly resumo = computed(() => {
    const todos = this.barbeirosVisiveis().flatMap((b) => b.agendamentos);
    return {
      total: todos.length,
      emAtendimento: todos.filter((a) => a.status === 'em_atendimento').length,
      concluidos: todos.filter((a) => a.status === 'concluido').length,
      faltas: todos.filter((a) => a.status === 'faltou').length,
      previsto: todos.filter((a) => a.status !== 'faltou').reduce((soma, a) => soma + Number(a.valor_total), 0),
    };
  });

  ngOnInit(): void {
    this.carregar();
    this.timer = setInterval(() => {
      this.tique.set(Date.now());
      if (this.ehHoje() && !this.selecionado() && !this.formulario()) this.carregar(true);
    }, ATUALIZAR_A_CADA_MS);
  }

  ngOnDestroy(): void {
    clearInterval(this.timer);
  }

  carregar(silencioso = false): void {
    this.api.dia(this.data() || undefined).subscribe({
      next: (a) => {
        const primeiraCarga = !this.agenda();
        this.agenda.set(a);
        this.data.set(a.data);
        this.relogio.set({ min: this.minutosRelogio(a.agora, a.data), em: Date.now() });
        this.tique.set(Date.now());
        // Barbeiro abre a agenda já filtrada na própria coluna
        if (primeiraCarga && a.meu_funcionario_id && this.auth.sessao()?.usuario.perfil === 'barbeiro') {
          this.filtro.set(String(a.meu_funcionario_id));
        }
        if (!this.rolouParaAgora) setTimeout(() => this.rolarParaAgora());
      },
      error: (e) => {
        if (!silencioso) this.aviso.erro(mensagemDeErro(e));
      },
    });
  }

  irPara(data: string): void {
    if (!data || data === this.data()) return;
    this.data.set(data);
    this.rolouParaAgora = false;
    this.carregar();
  }

  mudarDia(dias: number): void {
    const d = new Date(this.data() + 'T12:00:00Z');
    d.setUTCDate(d.getUTCDate() + dias);
    this.irPara(d.toISOString().slice(0, 10));
  }

  rotuloHora(min: number): string {
    return `${String(Math.floor(min / 60)).padStart(2, '0')}:00`;
  }

  abrir(ag: AgendamentoDoDia, barbeiro: ColunaBarbeiro): void {
    this.selecionado.set({ ag, barbeiro });
  }

  novo(): void {
    const meu = this.agenda()?.meu_funcionario_id;
    const filtro = this.filtro() === 'todos' ? null : Number(this.filtro());
    this.formulario.set({ data: this.data(), funcionarioId: filtro ?? (this.ehBarbeiro() ? meu : null) });
  }

  /** Clique numa área vazia da coluna: abre o formulário com barbeiro e horário. */
  clicarHorario(evento: MouseEvent, barbeiro: ColunaBarbeiro): void {
    if (this.ehBarbeiro() && barbeiro.id !== this.agenda()?.meu_funcionario_id) return;
    const alvo = evento.currentTarget as HTMLElement;
    const y = evento.clientY - alvo.getBoundingClientRect().top;
    const minuto = this.inicioGrade() + Math.floor(y / PX_POR_MINUTO / 15) * 15;
    const h = `${String(Math.floor(minuto / 60)).padStart(2, '0')}:${String(minuto % 60).padStart(2, '0')}`;
    this.formulario.set({ data: this.data(), funcionarioId: barbeiro.id, hora: h });
  }

  remarcar(ag: AgendamentoDoDia, barbeiro: ColunaBarbeiro): void {
    this.selecionado.set(null);
    this.formulario.set({ data: this.data(), funcionarioId: barbeiro.id, hora: hora(ag.inicio), agendamento: ag });
  }

  agendamentoSalvo(): void {
    const remarcou = !!this.formulario()?.agendamento;
    this.formulario.set(null);
    this.aviso.sucesso(remarcou ? 'Agendamento remarcado.' : 'Agendamento criado.');
    this.carregar(true);
  }

  private ehBarbeiro(): boolean {
    return this.auth.sessao()?.usuario.perfil === 'barbeiro';
  }

  acoes(status: StatusAgendamento): Acao[] {
    return ACOES[status] ?? [];
  }

  podeAlterar(barbeiro: ColunaBarbeiro): boolean {
    const perfil = this.auth.sessao()?.usuario.perfil;
    return perfil !== 'barbeiro' || barbeiro.id === this.agenda()?.meu_funcionario_id;
  }

  alterarStatus(ag: AgendamentoDoDia, status: StatusAgendamento): void {
    if (status === 'cancelado' && !confirm(`Cancelar o horário de ${ag.cliente?.nome}?`)) return;
    this.alterando.set(true);
    this.api.alterarStatus(ag.id, status).subscribe({
      next: () => {
        this.alterando.set(false);
        this.selecionado.set(null);
        this.aviso.sucesso(status === 'cancelado' ? 'Agendamento cancelado.' : `Situação: ${STATUS_ROTULO[status]}.`);
        this.carregar(true);
      },
      error: (e) => {
        this.alterando.set(false);
        this.aviso.erro(mensagemDeErro(e));
      },
    });
  }

  /** Converte horário ISO em minutos dentro do dia exibido (antes = 0, depois = 24h). */
  private minNoDia(iso: string): number {
    const dia = iso.slice(0, 10);
    if (dia < this.data()) return 0;
    if (dia > this.data()) return 24 * 60;
    return minutosDeHora(iso.slice(11, 16));
  }

  private minutosRelogio(agoraIso: string, data: string): number {
    return agoraIso.slice(0, 10) === data ? minutosDeHora(agoraIso.slice(11, 16)) : -1;
  }

  /** Posiciona os cartões; horários sobrepostos ficam lado a lado. */
  private posicionar(agendamentos: AgendamentoDoDia[], ini: number): Cartao[] {
    const itens = [...agendamentos]
      .map((ag) => ({ ag, a: this.minNoDia(ag.inicio), b: this.minNoDia(ag.fim) }))
      .sort((x, y) => x.a - y.a || y.b - x.b);
    const cartoes: Cartao[] = [];
    let grupo: { c: Cartao; fim: number }[] = [];
    let fimGrupo = -1;
    const fecharGrupo = () => {
      const total = Math.max(...grupo.map((g) => g.c.coluna)) + 1;
      grupo.forEach((g) => (g.c.colunas = total));
      grupo = [];
    };

    for (const it of itens) {
      if (grupo.length && it.a >= fimGrupo) fecharGrupo();
      const ocupadas = new Set(grupo.filter((g) => g.fim > it.a).map((g) => g.c.coluna));
      let coluna = 0;
      while (ocupadas.has(coluna)) coluna++;
      const altura = Math.max((it.b - it.a) * PX_POR_MINUTO, 22);
      const c: Cartao = {
        ag: it.ag,
        topo: (it.a - ini) * PX_POR_MINUTO,
        altura: altura - 2,
        coluna,
        colunas: 1,
        compacto: altura < 44,
      };
      cartoes.push(c);
      grupo.push({ c, fim: it.b });
      fimGrupo = Math.max(fimGrupo, it.b);
    }
    if (grupo.length) fecharGrupo();
    return cartoes;
  }

  /** Quem está na cadeira agora ou quem é o próximo cliente do barbeiro. */
  private destaque(agendamentos: AgendamentoDoDia[], agora: number | null): ColunaVisivel['destaque'] {
    const emAtendimento = agendamentos.find((a) => a.status === 'em_atendimento');
    if (emAtendimento) return { rotulo: 'Na cadeira', ag: emAtendimento };
    const pendentes = agendamentos.filter((a) => a.status === 'agendado' || a.status === 'confirmado');
    const proximo = agora === null ? pendentes[0] : pendentes.find((a) => this.minNoDia(a.fim) > agora);
    return proximo ? { rotulo: 'Próximo', ag: proximo } : null;
  }

  private rolarParaAgora(): void {
    const el = this.grade()?.nativeElement;
    if (!el) return;
    this.rolouParaAgora = true;
    const agora = this.agoraMin();
    const alvo = agora === null ? 0 : Math.max((agora - this.inicioGrade() - 60) * PX_POR_MINUTO, 0);
    el.scrollTo({ top: alvo });
  }
}
