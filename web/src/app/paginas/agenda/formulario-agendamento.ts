import { CurrencyPipe, DatePipe } from '@angular/common';
import { Component, computed, DestroyRef, inject, input, OnInit, output, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { debounceTime, distinctUntilChanged, Subject, switchMap } from 'rxjs';
import { AgendamentoDoDia, AgendaService, DadosAgendamento, OpcaoBarbeiro } from '../../core/agenda.service';
import { AuthService } from '../../core/auth.service';
import { podeEditar } from '../../core/auth.guards';
import { CadastrosService } from '../../core/cadastros.service';
import { errosPorCampo, mensagemDeErro } from '../../core/erros';
import { Cliente, Servico } from '../../core/modelos';
import { TelefonePipe } from '../../core/telefone.pipe';

/** Valores iniciais do formulário (ex.: horário clicado na agenda). */
export interface InicioFormulario {
  data: string;
  funcionarioId?: number | null;
  hora?: string | null;
  /** Preenchido ao remarcar. */
  agendamento?: AgendamentoDoDia;
}

/** Novo agendamento ou remarcação, aberto em painel lateral sobre a agenda. */
@Component({
  selector: 'app-formulario-agendamento',
  imports: [FormsModule, CurrencyPipe, DatePipe, TelefonePipe],
  template: `
    <div class="fundo-modal" (click)="fechar.emit()"></div>
    <aside class="painel-lateral painel-largo formulario-agendamento" role="dialog" aria-label="Agendamento">
      <h2>{{ remarcando() ? 'Remarcar agendamento' : 'Novo agendamento' }}</h2>
      @if (erro()) {<div class="alerta">{{ erro() }}</div>}

      <!-- 1. Cliente -->
      <section class="passo">
        <h3><span class="passo-numero">1</span> Cliente</h3>
        @if (remarcando()) {
          <div class="cliente-escolhido"><strong>{{ inicio().agendamento!.cliente?.nome }}</strong></div>
        } @else if (cliente(); as c) {
          <div class="cliente-escolhido">
            <div><strong>{{ c.nome }}</strong> <span class="texto-suave">{{ c.telefone | telefone }}</span></div>
            <button class="botao-link" type="button" (click)="cliente.set(null)">Trocar</button>
          </div>
        } @else if (novoCliente()) {
          <div class="grade-2">
            <label class="campo"><span>Nome</span><input [(ngModel)]="novoNome" name="novoNome" /></label>
            <label class="campo"><span>WhatsApp</span><input type="tel" [(ngModel)]="novoTelefone" name="novoTelefone" placeholder="(11) 99999-0000" /></label>
          </div>
          @if (errosApi()['novo_cliente.nome'] || errosApi()['novo_cliente.telefone']) {
            <small class="erro-campo">{{ errosApi()['novo_cliente.nome'] || errosApi()['novo_cliente.telefone'] }}</small>
          }
          <label class="campo-check"><input type="checkbox" [(ngModel)]="novoAceita" name="novoAceita" /> Autorizou receber lembretes pelo WhatsApp</label>
          <button class="botao-link" type="button" (click)="novoCliente.set(false)">← Buscar cliente cadastrado</button>
        } @else {
          <input class="campo-busca campo-busca-cheio" type="search" placeholder="Buscar por nome ou telefone…"
            (input)="busca$.next($any($event.target).value)" aria-label="Buscar cliente" />
          @if (resultados().length) {
            <ul class="lista-resultados">
              @for (c of resultados(); track c.id) {
                <li><button type="button" (click)="cliente.set(c)"><strong>{{ c.nome }}</strong> <span class="texto-suave">{{ c.telefone | telefone }}</span></button></li>
              }
            </ul>
          } @else if (buscou()) {
            <p class="texto-suave pequeno">Nenhum cliente encontrado.</p>
          }
          <button class="botao-link" type="button" (click)="iniciarNovoCliente()">+ Cadastrar novo cliente</button>
          @if (errosApi()['cliente_id']) {<small class="erro-campo">{{ errosApi()['cliente_id'] }}</small>}
        }
      </section>

      <!-- 2. Serviços -->
      <section class="passo">
        <h3><span class="passo-numero">2</span> Serviços</h3>
        <div class="chips">
          @for (s of servicos(); track s.id) {
            <button type="button" class="chip" [class.selecionado]="servicoIds().includes(s.id)" (click)="alternarServico(s.id)">
              {{ s.nome }} <small>{{ s.duracao_minutos }} min · {{ +s.preco | currency: 'BRL' }}</small>
            </button>
          }
        </div>
        @if (errosApi()['servico_ids']) {<small class="erro-campo">{{ errosApi()['servico_ids'] }}</small>}
      </section>

      <!-- 3. Dia, barbeiro e horário -->
      <section class="passo">
        <h3><span class="passo-numero">3</span> Dia, barbeiro e horário</h3>
        <div class="linha-data">
          <label class="campo">
            <span>Dia</span>
            <input type="date" [ngModel]="data()" (ngModelChange)="mudarData($event)" name="data" />
          </label>
          @if (podeEncaixar) {
            <label class="campo-check encaixe">
              <input type="checkbox" [ngModel]="encaixe()" (ngModelChange)="encaixe.set($event)" name="encaixe" />
              Encaixe <small class="texto-suave">(qualquer horário sem cliente)</small>
            </label>
          }
        </div>

        @if (servicoIds().length === 0) {
          <p class="texto-suave pequeno">Escolha ao menos um serviço para ver os horários livres.</p>
        } @else if (carregandoHorarios()) {
          <p class="texto-suave pequeno">Buscando horários…</p>
        } @else if (opcoes().length === 0) {
          <p class="texto-suave pequeno">Nenhum barbeiro realiza todos os serviços escolhidos.</p>
        } @else {
          <div class="chips">
            <button type="button" class="chip" [class.selecionado]="filtroBarbeiro() === null" (click)="filtroBarbeiro.set(null)">Qualquer barbeiro</button>
            @for (o of opcoes(); track o.funcionario_id) {
              <button type="button" class="chip" [class.selecionado]="filtroBarbeiro() === o.funcionario_id" (click)="escolherBarbeiro(o.funcionario_id)">{{ o.nome }}</button>
            }
          </div>

          @if (encaixe()) {
            <div class="grade-2 encaixe-campos">
              <label class="campo">
                <span>Barbeiro</span>
                <select [ngModel]="funcionarioId()" (ngModelChange)="escolherBarbeiro(+$event)" name="barbeiroEncaixe">
                  <option [ngValue]="null" disabled>Escolha</option>
                  @for (o of opcoes(); track o.funcionario_id) {<option [ngValue]="o.funcionario_id">{{ o.nome }}</option>}
                </select>
              </label>
              <label class="campo">
                <span>Horário</span>
                <input type="time" step="300" [ngModel]="horaEscolhida()" (ngModelChange)="horaEscolhida.set($event)" name="horaEncaixe" />
              </label>
            </div>
          } @else {
            @for (o of opcoesVisiveis(); track o.funcionario_id) {
              <div class="horarios-barbeiro">
                <div class="horarios-titulo">
                  <strong>{{ o.nome }}</strong>
                  <span class="texto-suave pequeno">{{ o.duracao_minutos }} min · {{ +o.valor_total | currency: 'BRL' }}</span>
                </div>
                @if (o.horarios.length) {
                  <div class="grade-horarios">
                    @for (h of o.horarios; track h) {
                      <button type="button" class="horario"
                        [class.selecionado]="funcionarioId() === o.funcionario_id && horaEscolhida() === h"
                        (click)="escolherHorario(o.funcionario_id, h)">{{ h }}</button>
                    }
                  </div>
                } @else {
                  <p class="texto-suave pequeno">Sem horários livres neste dia.</p>
                }
              </div>
            }
          }
        }
        @if (errosApi()['hora'] || errosApi()['funcionario_id']) {
          <small class="erro-campo">{{ errosApi()['hora'] || errosApi()['funcionario_id'] }}</small>
        }
      </section>

      <!-- 4. Observações -->
      <section class="passo">
        <h3><span class="passo-numero">4</span> Observações <small class="texto-suave">(opcional)</small></h3>
        <textarea class="campo-texto" rows="2" [(ngModel)]="observacoes" name="observacoes" placeholder="Ex.: prefere máquina 2 nas laterais"></textarea>
      </section>

      <div class="resumo-agendamento">
        @if (escolha(); as e) {
          <div>
            <strong>{{ data() + 'T12:00:00' | date: "EEE, d 'de' MMM" }} · {{ horaEscolhida() }}</strong>
            <span class="texto-suave">{{ e.nome }} · {{ e.duracao_minutos }} min · {{ +e.valor_total | currency: 'BRL' }}</span>
          </div>
        } @else {
          <span class="texto-suave">Escolha o horário para concluir.</span>
        }
        <div class="resumo-botoes">
          <button class="botao botao-secundario botao-auto" type="button" (click)="fechar.emit()">Cancelar</button>
          <button class="botao botao-auto" type="button" [disabled]="!podeSalvar() || salvando()" (click)="salvar()">
            {{ salvando() ? 'Salvando…' : remarcando() ? 'Salvar remarcação' : 'Agendar' }}
          </button>
        </div>
      </div>
    </aside>
  `,
})
export class FormularioAgendamento implements OnInit {
  readonly inicio = input.required<InicioFormulario>();
  readonly salvo = output<void>();
  readonly fechar = output<void>();

  private readonly agendaApi = inject(AgendaService);
  private readonly cadastros = inject(CadastrosService);
  private readonly destroyRef = inject(DestroyRef);
  protected readonly podeEncaixar = podeEditar(inject(AuthService), 'dono', 'recepcao');

  protected readonly remarcando = computed(() => !!this.inicio().agendamento);

  // Cliente
  protected readonly busca$ = new Subject<string>();
  protected readonly resultados = signal<Cliente[]>([]);
  protected readonly buscou = signal(false);
  protected readonly cliente = signal<Cliente | null>(null);
  protected readonly novoCliente = signal(false);
  protected novoNome = '';
  protected novoTelefone = '';
  protected novoAceita = true;

  // Serviços, dia e horário
  protected readonly servicos = signal<Servico[]>([]);
  protected readonly servicoIds = signal<number[]>([]);
  protected readonly data = signal('');
  protected readonly opcoes = signal<OpcaoBarbeiro[]>([]);
  protected readonly carregandoHorarios = signal(false);
  protected readonly filtroBarbeiro = signal<number | null>(null);
  protected readonly funcionarioId = signal<number | null>(null);
  protected readonly horaEscolhida = signal<string | null>(null);
  protected readonly encaixe = signal(false);
  protected observacoes = '';

  protected readonly salvando = signal(false);
  protected readonly erro = signal('');
  protected readonly errosApi = signal<Record<string, string>>({});

  protected readonly opcoesVisiveis = computed(() => {
    const f = this.filtroBarbeiro();
    return f === null ? this.opcoes() : this.opcoes().filter((o) => o.funcionario_id === f);
  });

  protected readonly escolha = computed(() =>
    this.horaEscolhida() ? this.opcoes().find((o) => o.funcionario_id === this.funcionarioId()) ?? null : null,
  );

  protected readonly podeSalvar = computed(
    () => this.servicoIds().length > 0 && !!this.escolha() && (this.remarcando() || !!this.cliente() || this.novoCliente()),
  );

  ngOnInit(): void {
    const ini = this.inicio();
    this.data.set(ini.data);
    this.funcionarioId.set(ini.funcionarioId ?? null);
    this.filtroBarbeiro.set(ini.funcionarioId ?? null);
    this.horaEscolhida.set(ini.hora ?? null);
    if (ini.agendamento) {
      this.servicoIds.set([...ini.agendamento.servico_ids]);
      this.observacoes = ini.agendamento.observacoes ?? '';
    }

    this.cadastros.listarServicos().subscribe((s) => {
      // Ao remarcar, mantém visíveis os serviços do agendamento mesmo se desativados depois
      this.servicos.set(s.filter((x) => x.ativo || this.servicoIds().includes(x.id)));
    });
    if (this.servicoIds().length) this.buscarHorarios(true);

    this.busca$
      .pipe(
        debounceTime(250),
        distinctUntilChanged(),
        switchMap((termo) => this.cadastros.listarClientes(termo.trim(), 1)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe((p) => {
        this.resultados.set(p.data.slice(0, 6));
        this.buscou.set(true);
      });
  }

  iniciarNovoCliente(): void {
    this.novoCliente.set(true);
    this.cliente.set(null);
  }

  alternarServico(id: number): void {
    this.servicoIds.update((ids) => (ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id]));
    this.buscarHorarios(true);
  }

  mudarData(data: string): void {
    if (!data) return;
    this.data.set(data);
    this.horaEscolhida.set(null);
    this.buscarHorarios(false);
  }

  escolherBarbeiro(id: number): void {
    this.filtroBarbeiro.set(id);
    if (this.funcionarioId() !== id) {
      this.funcionarioId.set(id);
      if (!this.encaixe()) this.horaEscolhida.set(null);
    }
  }

  escolherHorario(funcionarioId: number, h: string): void {
    this.funcionarioId.set(funcionarioId);
    this.horaEscolhida.set(h);
    this.errosApi.set({});
  }

  /** Atualiza os horários; mantém o horário pré-escolhido se ele continuar livre. */
  private buscarHorarios(manterHora: boolean): void {
    const ids = this.servicoIds();
    if (!ids.length) {
      this.opcoes.set([]);
      return;
    }
    this.carregandoHorarios.set(true);
    this.agendaApi.horarios(this.data(), ids, this.inicio().agendamento?.id).subscribe({
      next: (opcoes) => {
        this.opcoes.set(opcoes);
        this.carregandoHorarios.set(false);
        const atual = opcoes.find((o) => o.funcionario_id === this.funcionarioId());
        const h = this.horaEscolhida();
        if (!manterHora || !atual || (h && !this.encaixe() && !atual.horarios.includes(h))) {
          if (!this.encaixe()) this.horaEscolhida.set(null);
        }
      },
      error: (e) => {
        this.carregandoHorarios.set(false);
        this.erro.set(mensagemDeErro(e));
      },
    });
  }

  salvar(): void {
    const funcionarioId = this.funcionarioId();
    const horaEscolhida = this.horaEscolhida();
    if (!funcionarioId || !horaEscolhida) return;

    const dados: DadosAgendamento = {
      funcionario_id: funcionarioId,
      servico_ids: this.servicoIds(),
      data: this.data(),
      hora: horaEscolhida.slice(0, 5),
      observacoes: this.observacoes || null,
      encaixe: this.encaixe(),
    };
    if (!this.remarcando()) {
      if (this.novoCliente()) {
        dados.novo_cliente = { nome: this.novoNome, telefone: this.novoTelefone, aceita_whatsapp: this.novoAceita };
      } else {
        dados.cliente_id = this.cliente()?.id ?? null;
      }
    }

    this.salvando.set(true);
    this.erro.set('');
    this.errosApi.set({});
    const pedido = this.remarcando()
      ? this.agendaApi.remarcar(this.inicio().agendamento!.id, dados)
      : this.agendaApi.criar(dados);

    pedido.subscribe({
      next: () => {
        this.salvando.set(false);
        this.salvo.emit();
      },
      error: (e) => {
        this.salvando.set(false);
        const campos = errosPorCampo(e);
        this.errosApi.set(campos);
        this.erro.set(mensagemDeErro(e));
        // Alguém pode ter ocupado o horário enquanto o formulário estava aberto
        if (campos['hora']) this.buscarHorarios(true);
      },
    });
  }

}
