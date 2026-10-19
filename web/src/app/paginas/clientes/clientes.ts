import { DatePipe } from '@angular/common';
import { Component, DestroyRef, inject, OnInit, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { debounceTime, distinctUntilChanged, Subject } from 'rxjs';
import { AuthService } from '../../core/auth.service';
import { podeEditar } from '../../core/auth.guards';
import { AvisoService } from '../../core/aviso.service';
import { CadastrosService } from '../../core/cadastros.service';
import { errosPorCampo, mensagemDeErro } from '../../core/erros';
import { Cliente, Pagina } from '../../core/modelos';
import { TelefonePipe } from '../../core/telefone.pipe';

@Component({
  selector: 'app-clientes',
  imports: [ReactiveFormsModule, TelefonePipe, DatePipe],
  template: `
    <div class="cabecalho-pagina">
      <div>
        <h1>Clientes</h1>
        <p class="texto-suave">{{ pagina()?.total ?? 0 }} cliente(s) cadastrado(s).</p>
      </div>
      @if (editavel) {
        <button class="botao botao-auto" type="button" (click)="abrir()">+ Novo cliente</button>
      }
    </div>

    <input class="campo-busca" type="search" placeholder="Buscar por nome ou telefone…"
      (input)="busca$.next($any($event.target).value)" aria-label="Buscar clientes" />

    @if (carregando()) {
      <p class="texto-suave">Carregando…</p>
    } @else if (!pagina()?.data?.length) {
      <div class="vazio">{{ termo ? 'Nenhum cliente encontrado para "' + termo + '".' : 'Nenhum cliente cadastrado ainda.' }}</div>
    } @else {
      <div class="tabela-container">
        <table class="tabela">
          <thead><tr><th>Nome</th><th>WhatsApp</th><th>Mensagens</th><th>Desde</th><th></th></tr></thead>
          <tbody>
            @for (c of pagina()!.data; track c.id) {
              <tr>
                <td><strong>{{ c.nome }}</strong>@if (c.email) {<div class="texto-suave pequeno">{{ c.email }}</div>}</td>
                <td>{{ c.telefone | telefone }}</td>
                <td><span class="etiqueta" [class.etiqueta-ok]="c.aceita_whatsapp">{{ c.aceita_whatsapp ? 'Autorizado' : 'Não autorizado' }}</span></td>
                <td>{{ c.created_at | date: 'dd/MM/yyyy' }}</td>
                <td class="acoes">
                  <button class="botao-link" type="button" (click)="abrir(c)">{{ editavel ? 'Editar' : 'Ver' }}</button>
                </td>
              </tr>
            }
          </tbody>
        </table>
      </div>

      @if (pagina()!.last_page > 1) {
        <div class="paginacao">
          <button class="botao botao-secundario" type="button" [disabled]="pagina()!.current_page === 1" (click)="carregar(pagina()!.current_page - 1)">Anterior</button>
          <span>Página {{ pagina()!.current_page }} de {{ pagina()!.last_page }}</span>
          <button class="botao botao-secundario" type="button" [disabled]="pagina()!.current_page === pagina()!.last_page" (click)="carregar(pagina()!.current_page + 1)">Próxima</button>
        </div>
      }
    }

    @if (aberto()) {
      <div class="fundo-modal" (click)="fechar()"></div>
      <form class="painel-lateral" [formGroup]="form" (ngSubmit)="salvar()" novalidate>
        <h2>{{ editando() ? editando()!.nome : 'Novo cliente' }}</h2>
        @if (erro()) {<div class="alerta">{{ erro() }}</div>}
        <fieldset [disabled]="!editavel">
          <label class="campo">
            <span>Nome</span>
            <input formControlName="nome" />
            @if (erroCampo('nome')) {<small class="erro-campo">{{ erroCampo('nome') }}</small>}
          </label>
          <label class="campo">
            <span>WhatsApp</span>
            <input type="tel" formControlName="telefone" placeholder="(11) 99999-0000" />
            @if (erroCampo('telefone')) {<small class="erro-campo">{{ erroCampo('telefone') }}</small>}
          </label>
          <label class="campo">
            <span>E-mail <em>(opcional)</em></span>
            <input type="email" formControlName="email" />
            @if (erroCampo('email')) {<small class="erro-campo">{{ erroCampo('email') }}</small>}
          </label>
          <label class="campo">
            <span>Data de nascimento <em>(opcional)</em></span>
            <input type="date" formControlName="data_nascimento" />
            @if (erroCampo('data_nascimento')) {<small class="erro-campo">{{ erroCampo('data_nascimento') }}</small>}
          </label>
          <label class="campo">
            <span>Observações <em>(preferências, tipo de corte…)</em></span>
            <textarea formControlName="observacoes" rows="3"></textarea>
          </label>
          <label class="campo-check">
            <input type="checkbox" formControlName="aceita_whatsapp" />
            Cliente autorizou receber confirmações e lembretes pelo WhatsApp
          </label>
        </fieldset>

        <div class="rodape-painel">
          @if (editando() && dono) {
            <button class="botao-link perigo" type="button" (click)="excluir()">Excluir</button>
          }
          <button class="botao botao-secundario" type="button" (click)="fechar()">{{ editavel ? 'Cancelar' : 'Fechar' }}</button>
          @if (editavel) {
            <button class="botao botao-auto" type="submit" [disabled]="salvando()">{{ salvando() ? 'Salvando…' : 'Salvar' }}</button>
          }
        </div>
      </form>
    }
  `,
})
export class Clientes implements OnInit {
  private readonly api = inject(CadastrosService);
  private readonly aviso = inject(AvisoService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly auth = inject(AuthService);
  protected readonly editavel = podeEditar(this.auth, 'dono', 'recepcao');
  protected readonly dono = podeEditar(this.auth, 'dono');

  protected readonly busca$ = new Subject<string>();
  protected termo = '';
  protected readonly pagina = signal<Pagina<Cliente> | null>(null);
  protected readonly carregando = signal(true);
  protected readonly aberto = signal(false);
  protected readonly editando = signal<Cliente | null>(null);
  protected readonly salvando = signal(false);
  protected readonly erro = signal('');
  protected readonly errosApi = signal<Record<string, string>>({});

  protected readonly form = inject(FormBuilder).group({
    nome: ['', [Validators.required, Validators.maxLength(120)]],
    telefone: ['', Validators.required],
    email: ['', Validators.email],
    data_nascimento: [''],
    observacoes: [''],
    aceita_whatsapp: [false],
  });

  ngOnInit(): void {
    this.busca$
      .pipe(debounceTime(300), distinctUntilChanged(), takeUntilDestroyed(this.destroyRef))
      .subscribe((termo) => {
        this.termo = termo.trim();
        this.carregar(1);
      });
    this.carregar(1);
  }

  carregar(pagina: number): void {
    this.api.listarClientes(this.termo, pagina).subscribe({
      next: (p) => {
        this.pagina.set(p);
        this.carregando.set(false);
      },
      error: (e) => {
        this.aviso.erro(mensagemDeErro(e));
        this.carregando.set(false);
      },
    });
  }

  abrir(cliente?: Cliente): void {
    this.editando.set(cliente ?? null);
    this.erro.set('');
    this.errosApi.set({});
    this.form.reset({
      nome: cliente?.nome ?? '',
      telefone: cliente?.telefone ?? '',
      email: cliente?.email ?? '',
      data_nascimento: cliente?.data_nascimento ?? '',
      observacoes: cliente?.observacoes ?? '',
      aceita_whatsapp: cliente?.aceita_whatsapp ?? false,
    });
    this.aberto.set(true);
  }

  fechar(): void {
    this.aberto.set(false);
  }

  erroCampo(campo: string): string {
    if (this.errosApi()[campo]) return this.errosApi()[campo];
    const c = this.form.get(campo);
    return c?.touched && c.invalid ? 'Preencha este campo corretamente.' : '';
  }

  salvar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.salvando.set(true);
    this.erro.set('');
    this.errosApi.set({});
    const v = this.form.getRawValue();
    const dados = { ...v, data_nascimento: v.data_nascimento || null };
    this.api.salvarCliente(dados, this.editando()?.id).subscribe({
      next: () => {
        this.salvando.set(false);
        this.aberto.set(false);
        this.aviso.sucesso('Cliente salvo.');
        this.carregar(this.pagina()?.current_page ?? 1);
      },
      error: (e) => {
        this.salvando.set(false);
        this.errosApi.set(errosPorCampo(e));
        if (!Object.keys(this.errosApi()).length) this.erro.set(mensagemDeErro(e));
      },
    });
  }

  excluir(): void {
    const c = this.editando();
    if (!c || !confirm(`Excluir ${c.nome}? Esta ação remove o cliente da lista.`)) return;
    this.api.excluirCliente(c.id).subscribe({
      next: () => {
        this.aberto.set(false);
        this.aviso.sucesso('Cliente excluído.');
        this.carregar(1);
      },
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }
}
