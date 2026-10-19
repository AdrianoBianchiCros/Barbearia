import { CurrencyPipe, DatePipe } from '@angular/common';
import { Component, computed, inject, input, OnInit, signal } from '@angular/core';
import { FormBuilder, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';
import { AuthService } from '../../core/auth.service';
import { podeEditar } from '../../core/auth.guards';
import { AvisoService } from '../../core/aviso.service';
import { CadastrosService } from '../../core/cadastros.service';
import { errosPorCampo, mensagemDeErro } from '../../core/erros';
import { BloqueioAgenda, DIAS_SEMANA, FaixaHorario, Funcionario, Servico, VINCULOS } from '../../core/modelos';

type Aba = 'dados' | 'servicos' | 'expediente' | 'folgas';

interface LinhaServico {
  servico: Servico;
  marcado: boolean;
  preco: number | null;
  duracao: number | null;
}

interface DiaExpediente {
  dia: number;
  nome: string;
  faixas: { inicio: string; fim: string }[];
}

@Component({
  selector: 'app-barbeiro',
  imports: [ReactiveFormsModule, FormsModule, RouterLink, CurrencyPipe, DatePipe],
  template: `
    <a class="voltar" routerLink="/barbeiros">← Barbeiros</a>

    <div class="cabecalho-pagina">
      <div class="cabecalho-pessoa">
        @if (funcionario()?.foto_url) {
          <img class="avatar avatar-grande" [src]="funcionario()!.foto_url" alt="" />
        } @else {
          <span class="avatar avatar-grande avatar-inicial">{{ (funcionario()?.nome || '+').charAt(0) }}</span>
        }
        <div>
          <h1>{{ novo() ? 'Novo barbeiro' : funcionario()?.nome }}</h1>
          @if (!novo() && editavel) {
            <label class="botao-link">
              Trocar foto
              <input type="file" accept="image/png,image/jpeg,image/webp" hidden (change)="enviarFoto($event)" />
            </label>
          }
        </div>
      </div>
    </div>

    @if (!novo()) {
      <nav class="abas">
        @for (a of abas; track a.id) {
          <button type="button" [class.ativa]="aba() === a.id" (click)="aba.set(a.id)">{{ a.rotulo }}</button>
        }
      </nav>
    }

    @switch (aba()) {
      @case ('dados') {
        <form class="cartao-secao" [formGroup]="form" (ngSubmit)="salvarDados()" novalidate>
          @if (erro()) {<div class="alerta">{{ erro() }}</div>}
          <fieldset [disabled]="!editavel">
            <div class="grade-2">
              <label class="campo">
                <span>Nome</span>
                <input formControlName="nome" />
                @if (erroCampo('nome')) {<small class="erro-campo">{{ erroCampo('nome') }}</small>}
              </label>
              <label class="campo">
                <span>Telefone / WhatsApp</span>
                <input type="tel" formControlName="telefone" placeholder="(11) 99999-0000" />
                @if (erroCampo('telefone')) {<small class="erro-campo">{{ erroCampo('telefone') }}</small>}
              </label>
              <label class="campo">
                <span>E-mail <em>(opcional)</em></span>
                <input type="email" formControlName="email" />
                @if (erroCampo('email')) {<small class="erro-campo">{{ erroCampo('email') }}</small>}
              </label>
              <label class="campo">
                <span>Especialidades <em>(aparece na página)</em></span>
                <input formControlName="especialidades" placeholder="Ex.: Degradê, barba, pigmentação" />
              </label>
              <label class="campo">
                <span>Tipo de vínculo</span>
                <select formControlName="tipo_vinculo">
                  <option [ngValue]="null">Não informado</option>
                  @for (v of vinculos; track v[0]) {<option [ngValue]="v[0]">{{ v[1] }}</option>}
                </select>
              </label>
              <label class="campo">
                <span>Comissão (%)</span>
                <input type="number" formControlName="comissao_percentual" min="0" max="100" step="0.5" />
                @if (erroCampo('comissao_percentual')) {<small class="erro-campo">{{ erroCampo('comissao_percentual') }}</small>}
              </label>
            </div>
            <label class="campo-check"><input type="checkbox" formControlName="atende_clientes" /> Atende clientes (aparece na agenda e na página)</label>
            <label class="campo-check"><input type="checkbox" formControlName="ativo" /> Ativo</label>
          </fieldset>

          @if (editavel) {
            <div class="rodape-secao">
              @if (!novo()) {
                <button class="botao-link perigo" type="button" (click)="excluir()">Excluir barbeiro</button>
              }
              <button class="botao botao-auto" type="submit" [disabled]="salvando()">{{ salvando() ? 'Salvando…' : 'Salvar' }}</button>
            </div>
          }
        </form>
      }

      @case ('servicos') {
        <div class="cartao-secao">
          <p class="texto-suave">Marque os serviços que {{ funcionario()?.nome }} realiza. Preço e duração próprios são opcionais: em branco, vale o padrão do serviço.</p>
          @if (linhasServico().length === 0) {
            <div class="vazio">Cadastre os serviços primeiro em <a routerLink="/servicos">Serviços</a>.</div>
          }
          @for (l of linhasServico(); track l.servico.id) {
            <div class="linha-servico" [class.desmarcada]="!l.marcado">
              <label class="campo-check">
                <input type="checkbox" [(ngModel)]="l.marcado" [disabled]="!editavel" />
                <strong>{{ l.servico.nome }}</strong>
              </label>
              <input type="number" class="campo-curto" [(ngModel)]="l.preco" [disabled]="!editavel || !l.marcado"
                [placeholder]="(+l.servico.preco | currency: 'BRL') ?? ''" min="0" step="0.01" aria-label="Preço próprio" />
              <input type="number" class="campo-curto" [(ngModel)]="l.duracao" [disabled]="!editavel || !l.marcado"
                [placeholder]="l.servico.duracao_minutos + ' min'" min="5" step="5" aria-label="Duração própria" />
            </div>
          }
          @if (editavel && linhasServico().length) {
            <div class="rodape-secao">
              <button class="botao botao-auto" type="button" (click)="salvarServicos()" [disabled]="salvando()">Salvar serviços</button>
            </div>
          }
        </div>
      }

      @case ('expediente') {
        <div class="cartao-secao">
          <p class="texto-suave">Horários em que {{ funcionario()?.nome }} atende. Use mais de uma faixa para o intervalo de almoço.</p>
          @if (erro()) {<div class="alerta">{{ erro() }}</div>}
          @for (d of expediente(); track d.dia) {
            <div class="linha-dia">
              <label class="campo-check dia-nome">
                <input type="checkbox" [checked]="d.faixas.length > 0" [disabled]="!editavel" (change)="alternarDia(d)" />
                {{ d.nome }}
              </label>
              <div class="faixas">
                @if (d.faixas.length === 0) {<span class="texto-suave">Folga</span>}
                @for (f of d.faixas; track $index) {
                  <span class="faixa">
                    <input type="time" [(ngModel)]="f.inicio" [disabled]="!editavel" aria-label="Início" />
                    <span>às</span>
                    <input type="time" [(ngModel)]="f.fim" [disabled]="!editavel" aria-label="Fim" />
                    @if (editavel) {
                      <button class="botao-link perigo" type="button" (click)="d.faixas.splice($index, 1)" aria-label="Remover faixa">✕</button>
                    }
                  </span>
                }
                @if (editavel && d.faixas.length > 0) {
                  <button class="botao-link" type="button" (click)="d.faixas.push({ inicio: '13:00', fim: '19:00' })">+ faixa</button>
                }
              </div>
            </div>
          }
          @if (editavel) {
            <div class="rodape-secao">
              <button class="botao-link" type="button" (click)="copiarParaTodos()">Copiar o 1º dia de trabalho para os outros dias marcados</button>
              <button class="botao botao-auto" type="button" (click)="salvarExpediente()" [disabled]="salvando()">Salvar expediente</button>
            </div>
          }
        </div>
      }

      @case ('folgas') {
        <div class="cartao-secao">
          <p class="texto-suave">Folgas, férias e compromissos bloqueiam a agenda. Bloqueios da barbearia inteira (feriados) também aparecem aqui.</p>
          @if (podeBloquear) {
            <form class="grade-bloqueio" [formGroup]="formBloqueio" (ngSubmit)="criarBloqueio()">
              <label class="campo"><span>Início</span><input type="datetime-local" formControlName="inicio" /></label>
              <label class="campo"><span>Fim</span><input type="datetime-local" formControlName="fim" /></label>
              <label class="campo"><span>Motivo</span><input formControlName="motivo" placeholder="Ex.: Férias" /></label>
              <label class="campo-check"><input type="checkbox" formControlName="todaBarbearia" /> Barbearia inteira (feriado)</label>
              <button class="botao botao-auto" type="submit" [disabled]="salvando()">Adicionar</button>
            </form>
          }
          @if (bloqueios().length === 0) {
            <div class="vazio">Nenhuma folga ou bloqueio futuro.</div>
          }
          <ul class="lista-bloqueios">
            @for (b of bloqueios(); track b.id) {
              <li>
                <div>
                  <strong>{{ b.inicio | date: 'dd/MM/yyyy HH:mm' }}</strong> até <strong>{{ b.fim | date: 'dd/MM/yyyy HH:mm' }}</strong>
                  <div class="texto-suave pequeno">{{ b.motivo || 'Sem motivo' }}{{ b.funcionario_id ? '' : ' · barbearia inteira' }}</div>
                </div>
                @if (podeBloquear) {
                  <button class="botao-link perigo" type="button" (click)="excluirBloqueio(b)">Remover</button>
                }
              </li>
            }
          </ul>
        </div>
      }
    }
  `,
})
export class Barbeiro implements OnInit {
  /** Parâmetro da rota: id numérico ou "novo". */
  readonly id = input.required<string>();

  private readonly api = inject(CadastrosService);
  private readonly aviso = inject(AvisoService);
  private readonly router = inject(Router);
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  protected readonly editavel = podeEditar(this.auth, 'dono');
  protected readonly podeBloquear = podeEditar(this.auth, 'dono', 'recepcao');
  protected readonly vinculos = Object.entries(VINCULOS);
  protected readonly abas: { id: Aba; rotulo: string }[] = [
    { id: 'dados', rotulo: 'Dados' },
    { id: 'servicos', rotulo: 'Serviços' },
    { id: 'expediente', rotulo: 'Expediente' },
    { id: 'folgas', rotulo: 'Folgas e bloqueios' },
  ];

  protected readonly novo = computed(() => this.id() === 'novo');
  protected readonly funcionario = signal<Funcionario | null>(null);
  protected readonly aba = signal<Aba>('dados');
  protected readonly salvando = signal(false);
  protected readonly erro = signal('');
  protected readonly errosApi = signal<Record<string, string>>({});
  protected readonly linhasServico = signal<LinhaServico[]>([]);
  protected readonly expediente = signal<DiaExpediente[]>([]);
  protected readonly bloqueios = signal<BloqueioAgenda[]>([]);

  protected readonly form = this.fb.group({
    nome: ['', [Validators.required, Validators.maxLength(120)]],
    telefone: [''],
    email: ['', Validators.email],
    especialidades: [''],
    tipo_vinculo: [null as string | null],
    comissao_percentual: [0 as number | null, [Validators.min(0), Validators.max(100)]],
    atende_clientes: [true],
    ativo: [true],
  });

  protected readonly formBloqueio = this.fb.nonNullable.group({
    inicio: ['', Validators.required],
    fim: ['', Validators.required],
    motivo: [''],
    todaBarbearia: [false],
  });

  ngOnInit(): void {
    if (this.novo()) return;
    const id = Number(this.id());
    forkJoin({
      funcionario: this.api.buscarFuncionario(id),
      servicos: this.api.listarServicos(),
      bloqueios: this.api.listarBloqueios(id),
    }).subscribe({
      next: ({ funcionario, servicos, bloqueios }) => {
        this.aplicar(funcionario, servicos);
        this.bloqueios.set(bloqueios);
      },
      error: (e) => {
        this.aviso.erro(mensagemDeErro(e));
        this.router.navigate(['/barbeiros']);
      },
    });
  }

  private aplicar(f: Funcionario, servicos?: Servico[]): void {
    this.funcionario.set(f);
    this.form.reset({
      nome: f.nome,
      telefone: f.telefone ?? '',
      email: f.email ?? '',
      especialidades: f.especialidades ?? '',
      tipo_vinculo: f.tipo_vinculo,
      comissao_percentual: +f.comissao_percentual,
      atende_clientes: f.atende_clientes,
      ativo: f.ativo,
    });

    const catalogo = servicos ?? this.linhasServico().map((l) => l.servico);
    this.linhasServico.set(
      catalogo
        .filter((s) => s.ativo || f.servicos.some((fs) => fs.id === s.id))
        .map((s) => {
          const vinculo = f.servicos.find((fs) => fs.id === s.id);
          return {
            servico: s,
            marcado: !!vinculo,
            preco: vinculo?.preco != null ? +vinculo.preco : null,
            duracao: vinculo?.duracao_minutos ?? null,
          };
        }),
    );

    this.expediente.set(
      DIAS_SEMANA.map((nome, dia) => ({
        dia,
        nome,
        faixas: f.horarios_trabalho.filter((h) => h.dia_semana === dia).map((h) => ({ inicio: h.inicio, fim: h.fim })),
      })),
    );
  }

  erroCampo(campo: string): string {
    if (this.errosApi()[campo]) return this.errosApi()[campo];
    const c = this.form.get(campo);
    return c?.touched && c.invalid ? 'Preencha este campo corretamente.' : '';
  }

  salvarDados(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.iniciarEnvio();
    const atual = this.funcionario();
    this.api.salvarFuncionario(this.form.getRawValue(), atual?.id).subscribe({
      next: (f) => {
        this.salvando.set(false);
        this.aviso.sucesso('Dados salvos.');
        if (!atual) {
          this.aviso.sucesso('Barbeiro cadastrado. Agora defina os serviços e o expediente.');
          this.router.navigate(['/barbeiros', f.id]);
        } else {
          this.aplicar(f);
        }
      },
      error: (e) => this.falhou(e),
    });
  }

  salvarServicos(): void {
    const f = this.funcionario();
    if (!f) return;
    this.iniciarEnvio();
    const servicos = this.linhasServico()
      .filter((l) => l.marcado)
      .map((l) => ({ id: l.servico.id, preco: l.preco ?? null, duracao_minutos: l.duracao ?? null }));
    this.api.salvarServicosDoFuncionario(f.id, servicos).subscribe({
      next: (atualizado) => {
        this.salvando.set(false);
        this.aplicar(atualizado);
        this.aviso.sucesso('Serviços salvos.');
      },
      error: (e) => this.falhou(e),
    });
  }

  alternarDia(d: DiaExpediente): void {
    d.faixas = d.faixas.length ? [] : [{ inicio: '09:00', fim: '18:00' }];
    this.expediente.update((dias) => [...dias]);
  }

  copiarParaTodos(): void {
    const dias = this.expediente();
    const modelo = dias.find((d) => d.faixas.length);
    if (!modelo) return;
    dias.forEach((d) => {
      if (d !== modelo && d.faixas.length) d.faixas = modelo.faixas.map((f) => ({ ...f }));
    });
    this.expediente.set([...dias]);
  }

  salvarExpediente(): void {
    const f = this.funcionario();
    if (!f) return;
    this.iniciarEnvio();
    const horarios: FaixaHorario[] = this.expediente().flatMap((d) =>
      d.faixas.map((x) => ({ dia_semana: d.dia, inicio: x.inicio, fim: x.fim })),
    );
    this.api.salvarHorarios(f.id, horarios).subscribe({
      next: (atualizado) => {
        this.salvando.set(false);
        this.aplicar(atualizado);
        this.aviso.sucesso('Expediente salvo.');
      },
      error: (e) => this.falhou(e),
    });
  }

  criarBloqueio(): void {
    const f = this.funcionario();
    const v = this.formBloqueio.getRawValue();
    if (!f || this.formBloqueio.invalid) {
      this.formBloqueio.markAllAsTouched();
      return;
    }
    this.iniciarEnvio();
    this.api
      .criarBloqueio({
        funcionario_id: v.todaBarbearia ? null : f.id,
        inicio: new Date(v.inicio).toISOString(),
        fim: new Date(v.fim).toISOString(),
        motivo: v.motivo,
      })
      .subscribe({
        next: () => {
          this.salvando.set(false);
          this.formBloqueio.reset();
          this.aviso.sucesso('Bloqueio adicionado.');
          this.api.listarBloqueios(f.id).subscribe((b) => this.bloqueios.set(b));
        },
        error: (e) => {
          this.salvando.set(false);
          this.aviso.erro(mensagemDeErro(e));
        },
      });
  }

  excluirBloqueio(b: BloqueioAgenda): void {
    if (!confirm('Remover este bloqueio?')) return;
    this.api.excluirBloqueio(b.id).subscribe({
      next: () => this.bloqueios.update((lista) => lista.filter((x) => x.id !== b.id)),
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }

  enviarFoto(evento: Event): void {
    const arquivo = (evento.target as HTMLInputElement).files?.[0];
    const f = this.funcionario();
    if (!arquivo || !f) return;
    this.api.enviarFoto(f.id, arquivo).subscribe({
      next: (atualizado) => {
        this.funcionario.set(atualizado);
        this.aviso.sucesso('Foto atualizada.');
      },
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }

  excluir(): void {
    const f = this.funcionario();
    if (!f || !confirm(`Excluir ${f.nome}? Os agendamentos já feitos continuam no histórico.`)) return;
    this.api.excluirFuncionario(f.id).subscribe({
      next: () => {
        this.aviso.sucesso('Barbeiro excluído.');
        this.router.navigate(['/barbeiros']);
      },
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }

  private iniciarEnvio(): void {
    this.salvando.set(true);
    this.erro.set('');
    this.errosApi.set({});
  }

  private falhou(e: unknown): void {
    this.salvando.set(false);
    this.errosApi.set(errosPorCampo(e));
    this.erro.set(mensagemDeErro(e));
    this.aviso.erro(mensagemDeErro(e));
  }
}
