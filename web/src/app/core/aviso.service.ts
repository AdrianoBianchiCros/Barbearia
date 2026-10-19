import { Injectable, signal } from '@angular/core';

export interface Aviso {
  texto: string;
  tipo: 'sucesso' | 'erro';
}

/** Mensagem curta exibida no canto da tela após salvar ou em caso de erro. */
@Injectable({ providedIn: 'root' })
export class AvisoService {
  readonly atual = signal<Aviso | null>(null);
  private timer?: ReturnType<typeof setTimeout>;

  sucesso(texto: string): void {
    this.mostrar({ texto, tipo: 'sucesso' });
  }

  erro(texto: string): void {
    this.mostrar({ texto, tipo: 'erro' });
  }

  private mostrar(aviso: Aviso): void {
    clearTimeout(this.timer);
    this.atual.set(aviso);
    this.timer = setTimeout(() => this.atual.set(null), 4000);
  }
}
