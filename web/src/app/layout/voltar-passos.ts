import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { AgendaService } from '../core/agenda.service';

/** Botão de volta ao guia de primeiros passos; só aparece enquanto faltar algum dos três cadastros. */
@Component({
  selector: 'app-voltar-passos',
  imports: [RouterLink],
  template: `
    @if (agenda.guiaPendente()) {
      <a class="botao botao-secundario botao-auto" routerLink="/agenda">← Voltar aos primeiros passos</a>
    }
  `,
})
export class VoltarPassos {
  protected readonly agenda = inject(AgendaService);
}
