import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule, NgForm } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AdminService, BarbeariaAdmin, DadosAcesso, OpcoesAdmin } from '../../core/admin.service';
import { errosPorCampo, mensagemDeErro } from '../../core/erros';
import { anexarContratacao, CamposContratacao, contratacaoVazia } from './campos-contratacao';
import { CartaoAcesso } from './cartao-acesso';

/** Cadastro de uma barbearia cliente: dados, dono, período pago e contrato. */
@Component({
  selector: 'app-nova-barbearia',
  imports: [FormsModule, RouterLink, CamposContratacao, CartaoAcesso],
  template: `
    <a class="voltar botao-link" routerLink="/admin/barbearias">← Barbearias</a>

    @if (criada(); as c) {
      <div class="cabecalho-pagina">
        <div>
          <h1>{{ c.barbearia.nome }} cadastrada</h1>
          <p class="texto-suave">Acesso liberado até {{ formatarData(c.barbearia.acesso.vence_em) }}.</p>
        </div>
        <a class="botao botao-auto" [routerLink]="['/admin/barbearias', c.barbearia.id]">Ver barbearia</a>
      </div>
      <app-cartao-acesso [acesso]="c.acesso" />
      <button class="botao-link" type="button" (click)="novaCadastro()">Cadastrar outra barbearia</button>
    } @else {
      <div class="cabecalho-pagina">
        <div>
          <h1>Nova barbearia</h1>
          <p class="texto-suave">Cria a barbearia, o login do dono e libera o acesso pelo período contratado.</p>
        </div>
      </div>

      <form #f="ngForm" (ngSubmit)="salvar(f)" novalidate>
        @if (erro()) {<div class="alerta" role="alert">{{ erro() }}</div>}

        <section class="cartao-secao">
          <h2>Barbearia</h2>
          <label class="campo">
            <span>Nome da barbearia</span>
            <input name="nome" [(ngModel)]="form.nome" required maxlength="120" />
            @if (erros()['nome']) {<small class="erro-campo">{{ erros()['nome'] }}</small>}
          </label>
          <div class="grade-2">
            <label class="campo">
              <span>WhatsApp <em>(opcional)</em></span>
              <input type="tel" name="telefone" [(ngModel)]="form.telefone" placeholder="(11) 99999-0000" maxlength="20" />
            </label>
            <label class="campo">
              <span>CNPJ ou CPF <em>(opcional)</em></span>
              <input name="documento" [(ngModel)]="form.documento" maxlength="20" />
            </label>
          </div>
        </section>

        <section class="cartao-secao">
          <h2>Dono</h2>
          <p class="texto-suave">O sistema gera uma senha provisória para o primeiro acesso.</p>
          <div class="grade-2">
            <label class="campo">
              <span>Nome</span>
              <input name="dono_nome" [(ngModel)]="form.dono_nome" required maxlength="120" />
              @if (erros()['dono_nome']) {<small class="erro-campo">{{ erros()['dono_nome'] }}</small>}
            </label>
            <label class="campo">
              <span>E-mail (login)</span>
              <input type="email" name="dono_email" [(ngModel)]="form.dono_email" required maxlength="180" />
              @if (erros()['dono_email']) {<small class="erro-campo">{{ erros()['dono_email'] }}</small>}
            </label>
          </div>
        </section>

        <section class="cartao-secao">
          <h2>Plano e contrato</h2>
          <app-campos-contratacao [dados]="contratacao" [opcoes]="opcoes()" [erros]="erros()" />
          <div class="rodape-secao">
            <a class="botao botao-secundario botao-auto" routerLink="/admin/barbearias">Cancelar</a>
            <button class="botao botao-auto" type="submit" [disabled]="salvando()">
              {{ salvando() ? 'Cadastrando…' : 'Cadastrar e liberar acesso' }}
            </button>
          </div>
        </section>
      </form>
    }
  `,
})
export class NovaBarbearia implements OnInit {
  private readonly api = inject(AdminService);

  protected readonly opcoes = signal<OpcoesAdmin | null>(null);
  protected readonly salvando = signal(false);
  protected readonly erro = signal('');
  protected readonly erros = signal<Record<string, string>>({});
  protected readonly criada = signal<{ barbearia: BarbeariaAdmin; acesso: DadosAcesso } | null>(null);

  protected form = { nome: '', telefone: '', documento: '', dono_nome: '', dono_email: '' };
  protected contratacao = contratacaoVazia();

  ngOnInit(): void {
    this.api.opcoes().subscribe((o) => this.opcoes.set(o));
  }

  salvar(f: NgForm): void {
    if (f.invalid) {
      f.control.markAllAsTouched();
      this.erro.set('Preencha o nome da barbearia, o nome e o e-mail do dono e o valor pago.');
      return;
    }
    this.salvando.set(true);
    this.erro.set('');
    this.erros.set({});

    const dados = new FormData();
    Object.entries(this.form).forEach(([campo, valor]) => valor && dados.append(campo, valor));
    anexarContratacao(dados, this.contratacao);

    this.api.criar(dados).subscribe({
      next: (r) => {
        this.criada.set(r);
        this.salvando.set(false);
      },
      error: (e) => {
        this.erros.set(errosPorCampo(e));
        this.erro.set(mensagemDeErro(e));
        this.salvando.set(false);
      },
    });
  }

  novaCadastro(): void {
    this.form = { nome: '', telefone: '', documento: '', dono_nome: '', dono_email: '' };
    this.contratacao = contratacaoVazia();
    this.criada.set(null);
  }

  formatarData(data: string | null): string {
    return data ? data.split('-').reverse().join('/') : '—';
  }
}
