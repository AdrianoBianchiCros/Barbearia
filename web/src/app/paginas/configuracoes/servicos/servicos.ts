import { CurrencyPipe } from '@angular/common';
import { Component, inject, OnInit, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { AuthService } from '../../../core/auth.service';
import { podeEditar } from '../../../core/auth.guards';
import { AvisoService } from '../../../core/aviso.service';
import { CadastrosService } from '../../../core/cadastros.service';
import { errosPorCampo, mensagemDeErro } from '../../../core/erros';
import { Servico } from '../../../core/modelos';

@Component({
  selector: 'app-servicos',
  imports: [ReactiveFormsModule, CurrencyPipe],
  template: `
    <div class="cabecalho-pagina">
      <div>
        <h1>Serviços</h1>
        <p class="texto-suave">Duração e preço usados na agenda e na página da barbearia.</p>
      </div>
      @if (editavel) {
        <button class="botao botao-auto" type="button" (click)="abrir()">+ Novo serviço</button>
      }
    </div>

    @if (carregando()) {
      <p class="texto-suave">Carregando…</p>
    } @else if (servicos().length === 0) {
      <div class="vazio">Nenhum serviço cadastrado ainda.</div>
    } @else {
      <div class="tabela-container">
        <table class="tabela">
          <thead>
            <tr><th>Serviço</th><th>Duração</th><th>Preço</th><th>Situação</th>@if (editavel) {<th></th>}</tr>
          </thead>
          <tbody>
            @for (s of servicos(); track s.id) {
              <tr [class.inativo]="!s.ativo">
                <td>
                  <strong>{{ s.nome }}</strong>
                  @if (s.descricao) {<div class="texto-suave pequeno">{{ s.descricao }}</div>}
                </td>
                <td>{{ s.duracao_minutos }} min</td>
                <td>{{ +s.preco | currency: 'BRL' }}</td>
                <td><span class="etiqueta" [class.etiqueta-ok]="s.ativo">{{ s.ativo ? 'Ativo' : 'Inativo' }}</span></td>
                @if (editavel) {
                  <td class="acoes">
                    <button class="botao-link" type="button" (click)="abrir(s)">Editar</button>
                    <button class="botao-link perigo" type="button" (click)="excluir(s)">Excluir</button>
                  </td>
                }
              </tr>
            }
          </tbody>
        </table>
      </div>
    }

    @if (aberto()) {
      <div class="fundo-modal" (click)="fechar()"></div>
      <form class="painel-lateral" [formGroup]="form" (ngSubmit)="salvar()" novalidate>
        <h2>{{ editando() ? 'Editar serviço' : 'Novo serviço' }}</h2>
        @if (erro()) {<div class="alerta">{{ erro() }}</div>}

        <label class="campo">
          <span>Nome</span>
          <input formControlName="nome" placeholder="Ex.: Corte masculino" />
          @if (erroCampo('nome')) {<small class="erro-campo">{{ erroCampo('nome') }}</small>}
        </label>
        <label class="campo">
          <span>Descrição <em>(opcional, aparece na página)</em></span>
          <textarea formControlName="descricao" rows="3"></textarea>
        </label>
        <div class="grade-2">
          <label class="campo">
            <span>Duração (minutos)</span>
            <input type="number" formControlName="duracao_minutos" min="5" step="5" />
            @if (erroCampo('duracao_minutos')) {<small class="erro-campo">{{ erroCampo('duracao_minutos') }}</small>}
          </label>
          <label class="campo">
            <span>Preço (R$)</span>
            <input type="number" formControlName="preco" min="0" step="0.01" />
            @if (erroCampo('preco')) {<small class="erro-campo">{{ erroCampo('preco') }}</small>}
          </label>
        </div>
        <label class="campo-check"><input type="checkbox" formControlName="ativo" /> Serviço ativo</label>

        <div class="rodape-painel">
          <button class="botao botao-secundario" type="button" (click)="fechar()">Cancelar</button>
          <button class="botao botao-auto" type="submit" [disabled]="salvando()">{{ salvando() ? 'Salvando…' : 'Salvar' }}</button>
        </div>
      </form>
    }
  `,
})
export class Servicos implements OnInit {
  private readonly api = inject(CadastrosService);
  private readonly aviso = inject(AvisoService);
  protected readonly editavel = podeEditar(inject(AuthService), 'dono');

  protected readonly servicos = signal<Servico[]>([]);
  protected readonly carregando = signal(true);
  protected readonly aberto = signal(false);
  protected readonly editando = signal<Servico | null>(null);
  protected readonly salvando = signal(false);
  protected readonly erro = signal('');
  protected readonly errosApi = signal<Record<string, string>>({});

  protected readonly form = inject(FormBuilder).group({
    nome: ['', [Validators.required, Validators.maxLength(120)]],
    descricao: [''],
    duracao_minutos: [30, [Validators.required, Validators.min(5)]],
    preco: [0 as number | null, [Validators.required, Validators.min(0)]],
    ativo: [true],
  });

  ngOnInit(): void {
    this.carregar();
  }

  carregar(): void {
    this.api.listarServicos().subscribe({
      next: (s) => {
        this.servicos.set(s);
        this.carregando.set(false);
      },
      error: (e) => {
        this.aviso.erro(mensagemDeErro(e));
        this.carregando.set(false);
      },
    });
  }

  abrir(servico?: Servico): void {
    this.editando.set(servico ?? null);
    this.erro.set('');
    this.errosApi.set({});
    this.form.reset({
      nome: servico?.nome ?? '',
      descricao: servico?.descricao ?? '',
      duracao_minutos: servico?.duracao_minutos ?? 30,
      preco: servico ? +servico.preco : null,
      ativo: servico?.ativo ?? true,
    });
    this.aberto.set(true);
  }

  fechar(): void {
    this.aberto.set(false);
  }

  erroCampo(campo: string): string {
    const c = this.form.get(campo);
    if (this.errosApi()[campo]) return this.errosApi()[campo];
    return c?.touched && c.invalid ? 'Preencha este campo corretamente.' : '';
  }

  salvar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.salvando.set(true);
    const dados = this.form.getRawValue();
    this.api.salvarServico(dados, this.editando()?.id).subscribe({
      next: () => {
        this.salvando.set(false);
        this.aberto.set(false);
        this.aviso.sucesso('Serviço salvo.');
        this.carregar();
      },
      error: (e) => {
        this.salvando.set(false);
        this.errosApi.set(errosPorCampo(e));
        if (!Object.keys(this.errosApi()).length) this.erro.set(mensagemDeErro(e));
      },
    });
  }

  excluir(servico: Servico): void {
    if (!confirm(`Excluir o serviço "${servico.nome}"?`)) return;
    this.api.excluirServico(servico.id).subscribe({
      next: () => {
        this.aviso.sucesso('Serviço excluído.');
        this.carregar();
      },
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }
}
