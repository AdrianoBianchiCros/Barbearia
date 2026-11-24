import { CurrencyPipe, DatePipe } from '@angular/common';
import { Component, inject, OnInit, signal } from '@angular/core';
import { toDataURL } from 'qrcode';
import { AssinaturaService, Fatura, MinhaAssinaturaDados, PixFatura } from '../../core/assinatura.service';
import { AvisoService } from '../../core/aviso.service';
import { mensagemDeErro } from '../../core/erros';
import { PLATAFORMA } from '../../core/plataforma';

/**
 * Minha assinatura (somente dono): a próxima fatura com o pagamento por Pix (QR Code e copia e cola)
 * e o histórico das faturas já pagas. Aberta pelo farol do vencimento no menu.
 */
@Component({
  selector: 'app-minha-assinatura',
  imports: [DatePipe, CurrencyPipe],
  styles: `
    .proxima { display: grid; grid-template-columns: 1fr auto; gap: 16px; align-items: center; }
    .proxima .valor { font-size: 32px; font-weight: 500; letter-spacing: -0.02em; margin: 4px 0; }
    .proxima p { margin: 0 0 4px; }
    .etiqueta-espera { color: var(--color-tangerine); background: var(--st-em_atendimento-bg); }
    .pagamento { display: grid; grid-template-columns: 220px 1fr; gap: 24px; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--cor-borda); }
    .qr { width: 220px; height: 220px; padding: 8px; background: #fff; border: 1px solid var(--cor-borda); border-radius: var(--radius-cards); }
    .qr img { width: 100%; height: 100%; display: block; image-rendering: pixelated; }
    .passos { margin: 0 0 16px; padding-left: 20px; }
    .passos li { margin-bottom: 6px; }
    .copiar { display: flex; gap: 8px; align-items: stretch; margin-bottom: 16px; }
    .copiar code { flex: 1; min-width: 0; padding: 8px 12px; border: 1px solid var(--cor-borda); border-radius: var(--radius-inputs); background: var(--cor-superficie-2); overflow-x: auto; white-space: nowrap; font-size: 12px; }
    .acoes { display: flex; gap: 8px; flex-wrap: wrap; }
    @media (max-width: 700px) {
      .proxima { grid-template-columns: 1fr; }
      .pagamento { grid-template-columns: 1fr; justify-items: center; }
      .pagamento > div:last-child { width: 100%; }
    }
  `,
  template: `
    <div class="cabecalho-pagina">
      <div>
        <h1>Minha assinatura</h1>
        <p class="texto-suave">Faturas do sistema da barbearia. Pague a próxima por Pix, sem sair do painel.</p>
      </div>
    </div>

    @if (dados(); as d) {
      <section class="cartao-secao">
        <h2>Próxima fatura</h2>
        @if (d.proxima; as f) {
          <div class="proxima">
            <div>
              <p class="valor">{{ +f.total | currency: 'BRL' }}</p>
              @if (f.taxa_instalacao) {
                <p class="texto-suave pequeno">Mensalidade {{ +f.valor | currency: 'BRL' }} + taxa única de instalação {{ +f.taxa_instalacao | currency: 'BRL' }}</p>
              }
              <p>
                <strong>{{ f.periodo_rotulo }}</strong> · vence em
                <strong>{{ f.vencimento + 'T12:00:00' | date: 'dd/MM/yyyy' }}</strong>
                @if (f.status === 'informada') {
                  <span class="etiqueta etiqueta-espera">Pagamento informado</span>
                } @else {
                  <span class="etiqueta">Em aberto</span>
                }
              </p>
              <p class="texto-suave">
                Renova o acesso de {{ f.inicio + 'T12:00:00' | date: 'dd/MM/yyyy' }} a {{ f.fim + 'T12:00:00' | date: 'dd/MM/yyyy' }}.
              </p>
            </div>
            @if (d.pix_disponivel && !pix()) {
              <button class="botao botao-auto" type="button" (click)="pagar(f)" [disabled]="gerando()">
                {{ gerando() ? 'Gerando…' : 'Pagar' }}
              </button>
            }
          </div>

          @if (f.status === 'informada') {
            <div class="alerta">
              Você avisou que pagou em {{ f.informada_em | date: 'dd/MM/yyyy HH:mm' }}. Assim que a {{ plataforma }} confirmar o
              recebimento, o acesso é renovado e a fatura aparece em "Faturas pagas".
            </div>
          }

          @if (!d.pix_disponivel) {
            <p class="texto-suave">O pagamento por Pix pelo painel ainda não está disponível. Para pagar, fale com a {{ plataforma }}.</p>
          }

          @if (pix(); as p) {
            <div class="pagamento">
              <div class="qr">
                @if (qrCode()) {
                  <img [src]="qrCode()" alt="QR Code do Pix para pagar a fatura" />
                }
              </div>
              <div>
                <ol class="passos">
                  <li>Abra o app do seu banco e escolha <strong>Pix › Ler QR Code</strong> (ou "Pix copia e cola").</li>
                  <li>Confira o valor de <strong>{{ +p.valor | currency: 'BRL' }}</strong> para <strong>{{ p.recebedor }}</strong> e pague.</li>
                  <li>Volte aqui e clique em <strong>"Já paguei"</strong>.</li>
                </ol>
                <div class="copiar">
                  <code>{{ p.copia_e_cola }}</code>
                  <button class="botao botao-secundario botao-auto" type="button" (click)="copiar(p.copia_e_cola)">Copiar</button>
                </div>
                <div class="acoes">
                  @if (f.status === 'aberta') {
                    <button class="botao botao-auto" type="button" (click)="informar(f)" [disabled]="informando()">
                      {{ informando() ? 'Enviando…' : 'Já paguei' }}
                    </button>
                  }
                  <button class="botao botao-secundario botao-auto" type="button" (click)="fecharPix()">Fechar</button>
                </div>
              </div>
            </div>
          }
        } @else {
          <p class="texto-suave">
            @if (d.acesso.vence_em) {
              Nenhuma fatura para pagar pelo painel. Para contratar ou mudar o plano, fale com a {{ plataforma }}.
            } @else {
              Sua barbearia não tem mensalidade com vencimento.
            }
          </p>
        }
      </section>

      <h2>Faturas pagas</h2>
      @if (d.pagas.length === 0) {
        <div class="vazio">Nenhuma fatura paga ainda.</div>
      } @else {
        <div class="tabela-container">
          <table class="tabela">
            <thead><tr><th>Plano</th><th>Período</th><th>Pagamento</th><th>Valor</th><th>Situação</th></tr></thead>
            <tbody>
              @for (a of d.pagas; track a.id) {
                <tr>
                  <td><strong>{{ a.periodo }}</strong></td>
                  <td>{{ a.inicio + 'T12:00:00' | date: 'dd/MM/yyyy' }} a {{ a.fim + 'T12:00:00' | date: 'dd/MM/yyyy' }}</td>
                  <td>{{ a.forma_pagamento }}</td>
                  <td>{{ +a.valor | currency: 'BRL' }}</td>
                  <td><span class="etiqueta etiqueta-ok">Paga</span></td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      }
    } @else {
      <div class="vazio">Carregando…</div>
    }
  `,
})
export class MinhaAssinatura implements OnInit {
  private readonly api = inject(AssinaturaService);
  private readonly aviso = inject(AvisoService);
  protected readonly plataforma = PLATAFORMA.nome;

  protected readonly dados = signal<MinhaAssinaturaDados | null>(null);
  protected readonly pix = signal<PixFatura | null>(null);
  protected readonly qrCode = signal('');
  protected readonly gerando = signal(false);
  protected readonly informando = signal(false);

  ngOnInit(): void {
    this.carregar();
  }

  private carregar(): void {
    this.api.dados().subscribe({
      next: (d) => this.dados.set(d),
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }

  pagar(f: Fatura): void {
    this.gerando.set(true);
    this.api.pix(f.id).subscribe({
      next: async (p) => {
        this.pix.set(p);
        this.qrCode.set(await toDataURL(p.copia_e_cola, { margin: 1, width: 400, errorCorrectionLevel: 'M' }));
        this.gerando.set(false);
      },
      error: (e) => {
        this.gerando.set(false);
        this.aviso.erro(mensagemDeErro(e));
      },
    });
  }

  fecharPix(): void {
    this.pix.set(null);
    this.qrCode.set('');
  }

  async copiar(texto: string): Promise<void> {
    try {
      await navigator.clipboard.writeText(texto);
      this.aviso.sucesso('Código Pix copiado. Cole no app do banco em "Pix copia e cola".');
    } catch {
      this.aviso.erro('Não deu para copiar. Selecione o código e copie manualmente.');
    }
  }

  informar(f: Fatura): void {
    this.informando.set(true);
    this.api.informarPagamento(f.id).subscribe({
      next: (r) => {
        this.informando.set(false);
        this.dados.update((d) => (d ? { ...d, proxima: r.fatura } : d));
        this.fecharPix();
        this.aviso.sucesso(r.message);
      },
      error: (e) => {
        this.informando.set(false);
        this.aviso.erro(mensagemDeErro(e));
      },
    });
  }
}
