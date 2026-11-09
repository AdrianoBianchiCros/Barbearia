import { Component } from '@angular/core';
import { PLATAFORMA } from '../core/plataforma';

/** Rodapé com a identificação da empresa e os links da Política de privacidade e dos Termos de uso. */
@Component({
  selector: 'app-rodape-legal',
  template: `
    <footer class="rodape-legal">
      <span>{{ p.nome }} · CNPJ {{ p.cnpj }}</span>
      <span>
        <a [href]="p.privacidade" target="_blank" rel="noopener">Política de privacidade</a> ·
        <a [href]="p.termos" target="_blank" rel="noopener">Termos de uso</a>
      </span>
    </footer>
  `,
})
export class RodapeLegal {
  protected readonly p = PLATAFORMA;
}
