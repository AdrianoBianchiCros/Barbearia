import { Component, input, output } from '@angular/core';
import { RouterLink } from '@angular/router';

export interface PassoInicial {
  titulo: string;
  detalhe: string;
  rota: string;
  feito: boolean;
  icone: string;
}

/** Popup do primeiro acesso: explica os três cadastros iniciais e leva ao primeiro que falta. */
@Component({
  selector: 'app-boas-vindas',
  imports: [RouterLink],
  template: `
    <div class="fundo-modal" (click)="fechar.emit()"></div>
    <div class="boas-vindas" role="dialog" aria-modal="true" aria-labelledby="boas-vindas-titulo">
      <button class="boas-vindas-fechar" type="button" (click)="fechar.emit()" aria-label="Fechar">×</button>

      <h2 id="boas-vindas-titulo">Bem-vindo à sua barbearia!</h2>
      <p class="texto-suave">
        Antes de começar a agendar, faça estes três cadastros. Leva poucos minutos e você pode voltar a este guia
        pela agenda a qualquer momento.
      </p>

      <ol class="boas-vindas-passos">
        @for (p of passos(); track p.rota; let i = $index) {
          <li [class.feito]="p.feito">
            <span class="boas-vindas-icone" aria-hidden="true">
              @if (p.feito) {
                <svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
              } @else {
                <svg viewBox="0 0 24 24"><path [attr.d]="p.icone" /></svg>
              }
            </span>
            <div>
              <span class="passo-rotulo">Passo {{ i + 1 < 10 ? '0' : '' }}{{ i + 1 }}{{ p.feito ? ' · feito' : '' }}</span>
              <strong>{{ p.titulo }}</strong>
              <small class="texto-suave">{{ p.detalhe }}</small>
            </div>
          </li>
        }
      </ol>

      <div class="rodape-painel">
        <button class="botao botao-secundario botao-auto" type="button" (click)="fechar.emit()">Fechar</button>
        @if (proximo(); as p) {
          <a class="botao botao-auto" [routerLink]="p.rota" (click)="fechar.emit()">Começar: {{ p.titulo.toLowerCase() }}</a>
        }
      </div>
    </div>
  `,
})
export class BoasVindas {
  readonly passos = input.required<PassoInicial[]>();
  readonly fechar = output<void>();

  protected proximo(): PassoInicial | undefined {
    return this.passos().find((p) => !p.feito);
  }
}
