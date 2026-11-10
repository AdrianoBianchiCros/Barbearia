import { CurrencyPipe, DatePipe } from '@angular/common';
import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { FormsModule, NgForm } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { AdminService, Assinatura, DadosAcesso, DetalheBarbearia, OpcoesAdmin, SITUACOES_ACESSO } from '../../core/admin.service';
import { AvisoService } from '../../core/aviso.service';
import { errosPorCampo, mensagemDeErro } from '../../core/erros';
import { anexarContratacao, CamposContratacao, contratacaoVazia } from './campos-contratacao';
import { CartaoAcesso } from './cartao-acesso';

/** Detalhe da barbearia: prazo de acesso, renovação, suspensão, senha do dono e histórico de contratos. */
@Component({
  selector: 'app-barbearia-detalhe',
  imports: [FormsModule, RouterLink, DatePipe, CurrencyPipe, CamposContratacao, CartaoAcesso],
  template: `
    <a class="voltar botao-link" routerLink="/admin/barbearias">← Barbearias</a>

    @if (b(); as b) {
      <div class="cabecalho-pagina">
        <div>
          <h1>{{ b.nome }} <span class="etiqueta" [class]="situacoes[b.acesso.situacao].classe">{{ situacoes[b.acesso.situacao].rotulo }}</span></h1>
          <p class="texto-suave">
            <a [href]="b.pagina_url" target="_blank" rel="noopener">{{ b.pagina_url }}</a>
            · cliente desde {{ b.criada_em ? (b.criada_em + 'T12:00:00' | date: 'dd/MM/yyyy') : '—' }}
          </p>
        </div>
        <button class="botao botao-auto" type="button" (click)="abrirRenovacao()">Renovar acesso</button>
      </div>

      @if (acessoGerado(); as a) {
        <app-cartao-acesso [acesso]="a" />
      }

      <div class="grade-2">
        <section class="cartao-secao">
          <h2>Prazo de acesso</h2>
          @if (b.acesso.vence_em) {
            <p>Pago até <strong>{{ b.acesso.vence_em + 'T12:00:00' | date: 'dd/MM/yyyy' }}</strong> ({{ textoDias() }}).</p>
            <p class="texto-suave">
              Depois do vencimento, o sistema continua funcionando por {{ opcoes()?.carencia_dias ?? 5 }} dias, até
              {{ b.acesso.bloqueia_em + 'T12:00:00' | date: 'dd/MM/yyyy' }}, e então bloqueia.
            </p>
          } @else {
            <p>Sem prazo definido: o acesso não vence. Use "Renovar acesso" para registrar o plano.</p>
          }
          @if (!b.ativo) {
            <div class="alerta">Barbearia suspensa: ninguém entra no painel e o agendamento online está desligado.</div>
          }
          <div class="rodape-secao">
            @if (b.ativo) {
              <button class="botao-link perigo" type="button" (click)="suspender()">Suspender acesso agora</button>
            } @else {
              <button class="botao botao-secundario botao-auto" type="button" (click)="reativar()">Reativar acesso</button>
            }
          </div>
        </section>

        <section class="cartao-secao">
          <h2>Dono e contato</h2>
          @if (b.dono; as d) {
            <p><strong>{{ d.nome }}</strong><br /><span class="texto-suave">{{ d.email }}</span></p>
          } @else {
            <p class="texto-suave">Sem dono cadastrado.</p>
          }
          <p class="texto-suave">
            WhatsApp: {{ b.telefone || '—' }} · CNPJ/CPF: {{ b.documento || '—' }} · {{ b.total_usuarios }} usuário(s)
          </p>
          <div class="rodape-secao">
            @if (b.dono) {
              <button class="botao botao-secundario botao-auto" type="button" (click)="novaSenha()">Gerar nova senha do dono</button>
            }
          </div>
        </section>
      </div>

      <h2>Histórico de contratações</h2>
      @if (b.assinaturas.length === 0) {
        <div class="vazio">Nenhuma contratação registrada.</div>
      } @else {
        <div class="tabela-container">
          <table class="tabela">
            <thead><tr><th>Período</th><th>Vigência</th><th>Pagamento</th><th>Valor</th><th>Contrato</th><th>Registro</th></tr></thead>
            <tbody>
              @for (a of b.assinaturas; track a.id) {
                <tr>
                  <td>
                    <strong>{{ rotulo('periodos', a.periodo) }}</strong>
                    @if (a.observacao) {<br /><small class="texto-suave">{{ a.observacao }}</small>}
                  </td>
                  <td>{{ a.inicio + 'T12:00:00' | date: 'dd/MM/yyyy' }} a {{ a.fim + 'T12:00:00' | date: 'dd/MM/yyyy' }}</td>
                  <td>{{ rotulo('formas_pagamento', a.forma_pagamento) }}</td>
                  <td>{{ +a.valor | currency: 'BRL' }}</td>
                  <td>
                    @if (a.tem_contrato) {
                      <button class="botao-link" type="button" (click)="baixarContrato(a)">{{ a.contrato_nome || 'Baixar' }}</button>
                    } @else {
                      <span class="texto-suave">—</span>
                    }
                  </td>
                  <td>
                    <small class="texto-suave">
                      {{ a.created_at | date: 'dd/MM/yyyy HH:mm' }}
                      @if (a.administrador) {<br />{{ a.administrador.nome }}}
                    </small>
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      }

      @if (renovando()) {
        <div class="fundo-modal" (click)="renovando.set(false)"></div>
        <form class="painel-lateral" #f="ngForm" (ngSubmit)="renovar(f)" novalidate>
          <h2>Renovar acesso</h2>
          @if (erro()) {<div class="alerta">{{ erro() }}</div>}
          <app-campos-contratacao [dados]="contratacao" [opcoes]="opcoes()" [erros]="erros()" [dicaInicio]="dicaInicio()" />
          <div class="rodape-painel">
            <button class="botao botao-secundario botao-auto" type="button" (click)="renovando.set(false)">Cancelar</button>
            <button class="botao botao-auto" type="submit" [disabled]="salvando()">{{ salvando() ? 'Salvando…' : 'Renovar' }}</button>
          </div>
        </form>
      }
    } @else {
      <div class="vazio">Carregando…</div>
    }
  `,
})
export class BarbeariaDetalhe implements OnInit {
  private readonly api = inject(AdminService);
  private readonly aviso = inject(AvisoService);
  private readonly id = Number(inject(ActivatedRoute).snapshot.paramMap.get('id'));
  protected readonly situacoes = SITUACOES_ACESSO;

  protected readonly b = signal<DetalheBarbearia | null>(null);
  protected readonly opcoes = signal<OpcoesAdmin | null>(null);
  protected readonly acessoGerado = signal<DadosAcesso | null>(null);
  protected readonly renovando = signal(false);
  protected readonly salvando = signal(false);
  protected readonly erro = signal('');
  protected readonly erros = signal<Record<string, string>>({});
  protected contratacao = contratacaoVazia();

  /** De onde a renovação começa se o início ficar em branco (mesma regra da API). */
  protected readonly dicaInicio = computed(() => {
    const acesso = this.b()?.acesso;
    return acesso?.vence_em && (acesso.dias_restantes ?? -1) >= 0
      ? 'Se ficar em branco, começa no dia seguinte ao vencimento atual.'
      : 'Se ficar em branco, começa hoje.';
  });

  protected readonly textoDias = computed(() => {
    const dias = this.b()?.acesso.dias_restantes ?? 0;
    if (dias > 1) return `faltam ${dias} dias`;
    if (dias === 1) return 'vence amanhã';
    if (dias === 0) return 'vence hoje';
    return `venceu há ${-dias} dia(s)`;
  });

  ngOnInit(): void {
    this.api.opcoes().subscribe((o) => this.opcoes.set(o));
    this.api.barbearia(this.id).subscribe({
      next: (b) => this.b.set(b),
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }

  rotulo(lista: 'periodos' | 'formas_pagamento', valor: string): string {
    return this.opcoes()?.[lista].find((o) => o.valor === valor)?.rotulo ?? valor;
  }

  abrirRenovacao(): void {
    this.contratacao = contratacaoVazia();
    this.erro.set('');
    this.erros.set({});
    this.renovando.set(true);
  }

  renovar(f: NgForm): void {
    if (f.invalid) {
      this.erro.set('Informe o período, a forma de pagamento e o valor pago.');
      return;
    }
    this.salvando.set(true);
    this.erro.set('');
    this.api.renovar(this.id, anexarContratacao(new FormData(), this.contratacao)).subscribe({
      next: (b) => {
        this.b.set(b);
        this.renovando.set(false);
        this.salvando.set(false);
        this.aviso.sucesso(`Acesso renovado até ${b.acesso.vence_em?.split('-').reverse().join('/')}.`);
      },
      error: (e) => {
        this.erros.set(errosPorCampo(e));
        this.erro.set(mensagemDeErro(e));
        this.salvando.set(false);
      },
    });
  }

  suspender(): void {
    if (!confirm('Suspender o acesso agora? Ninguém da barbearia conseguirá entrar e o agendamento online será desligado.')) return;
    this.api.suspender(this.id).subscribe({
      next: (b) => {
        this.b.set(b);
        this.aviso.sucesso('Acesso suspenso.');
      },
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }

  reativar(): void {
    this.api.reativar(this.id).subscribe({
      next: (b) => {
        this.b.set(b);
        this.aviso.sucesso('Acesso reativado.');
      },
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }

  novaSenha(): void {
    if (!confirm('Gerar uma nova senha para o dono? A senha atual deixa de valer e as sessões abertas são encerradas.')) return;
    this.api.novaSenha(this.id).subscribe({
      next: (r) => this.acessoGerado.set(r.acesso),
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }

  baixarContrato(a: Assinatura): void {
    this.api.contrato(a.id).subscribe({
      next: (arquivo) => {
        const url = URL.createObjectURL(arquivo);
        const link = document.createElement('a');
        link.href = url;
        link.download = a.contrato_nome || 'contrato.pdf';
        link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
      },
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }
}
