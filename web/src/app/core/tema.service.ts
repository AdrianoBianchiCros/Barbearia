import { Injectable, signal } from '@angular/core';

export type Tema = 'claro' | 'escuro';

const CHAVE_TEMA = 'barbearia.tema';

function lerTema(): Tema {
  try {
    return localStorage.getItem(CHAVE_TEMA) === 'escuro' ? 'escuro' : 'claro';
  } catch {
    return 'claro';
  }
}

/**
 * Modo noturno opcional. O padrão é o claro; a escolha fica salva neste navegador.
 * O `index.html` aplica o tema salvo antes de o painel carregar, para a tela não piscar em branco.
 */
@Injectable({ providedIn: 'root' })
export class TemaService {
  readonly tema = signal<Tema>(lerTema());

  constructor() {
    this.aplicar(this.tema());
  }

  alternar(): void {
    const novo: Tema = this.tema() === 'escuro' ? 'claro' : 'escuro';
    this.tema.set(novo);
    this.aplicar(novo);
    try {
      localStorage.setItem(CHAVE_TEMA, novo);
    } catch {
      // sem armazenamento: vale até recarregar a página
    }
  }

  private aplicar(tema: Tema): void {
    if (tema === 'escuro') {
      document.documentElement.dataset['tema'] = 'escuro';
    } else {
      delete document.documentElement.dataset['tema'];
    }
  }
}
