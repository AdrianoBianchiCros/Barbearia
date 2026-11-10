import { Component, input, signal } from '@angular/core';

/**
 * Botão de "olho" que mostra ou esconde a senha digitada.
 * Uso: <div class="campo-senha"><input #senha type="password" ... /><app-ver-senha [campo]="senha" /></div>
 */
@Component({
  selector: 'app-ver-senha',
  template: `
    <button
      type="button"
      class="ver-senha"
      [attr.aria-label]="visivel() ? 'Esconder senha' : 'Mostrar senha'"
      [attr.aria-pressed]="visivel()"
      [title]="visivel() ? 'Esconder senha' : 'Mostrar senha'"
      (click)="alternar()"
    >
      @if (visivel()) {
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M3 3l18 18" />
          <path d="M10.6 5.1A10.4 10.4 0 0 1 12 5c6 0 9.5 7 9.5 7a17.6 17.6 0 0 1-3.2 4.1" />
          <path d="M6.6 6.6A17.4 17.4 0 0 0 2.5 12S6 19 12 19a9.9 9.9 0 0 0 5.4-1.6" />
          <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
        </svg>
      } @else {
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z" />
          <circle cx="12" cy="12" r="3" />
        </svg>
      }
    </button>
  `,
})
export class VerSenha {
  readonly campo = input.required<HTMLInputElement>();
  protected readonly visivel = signal(false);

  protected alternar(): void {
    const campo = this.campo();
    this.visivel.update((v) => !v);
    campo.type = this.visivel() ? 'text' : 'password';
    campo.focus();
  }
}
