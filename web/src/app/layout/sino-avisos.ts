import { DatePipe } from '@angular/common';
import { Component, inject, OnDestroy, OnInit, output, signal } from '@angular/core';
import { AvisoAgenda, AvisosService } from '../core/avisos.service';

const ATUALIZAR_A_CADA_MS = 60_000;

/**
 * Sino da agenda: cancelamentos e remarcações (do painel ou do cliente pelo link).
 * Fica marcado até o usuário abrir a lista; clicar num aviso leva ao horário.
 */
@Component({
  selector: 'app-sino-avisos',
  imports: [DatePipe],
  template: `
    <div class="sino" (keydown.escape)="aberto.set(false)">
      <button type="button" class="sino-botao" [class.tem-novos]="naoVistos() > 0" (click)="alternar()"
        [attr.aria-label]="naoVistos() ? naoVistos() + ' alteração(ões) nova(s) na agenda' : 'Alterações na agenda'" [attr.aria-expanded]="aberto()">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15L6 16Z" /><path d="M10 20.5a2 2 0 0 0 4 0" />
        </svg>
        @if (naoVistos() > 0) {<span class="sino-contador">{{ naoVistos() > 9 ? '9+' : naoVistos() }}</span>}
      </button>

      @if (aberto()) {
        <div class="sino-fundo" (click)="aberto.set(false)"></div>
        <div class="sino-lista" role="dialog" aria-label="Alterações na agenda">
          <strong class="sino-titulo">Alterações na agenda</strong>
          @for (a of avisos(); track a.id) {
            <button type="button" class="sino-item" [class.novo]="!a.visto" (click)="escolher(a)">
              <span class="sino-tipo" [class.cancelado]="a.tipo === 'cancelamento'">{{ a.tipo === 'cancelamento' ? 'Cancelou' : 'Remarcou' }}</span>
              <span class="sino-texto">
                <strong>{{ a.cliente }}</strong>
                @if (a.tipo === 'cancelamento') {
                  {{ a.inicio | date: "EEE dd/MM 'às' HH:mm" }} · {{ a.barbeiro }}
                } @else {
                  {{ a.inicio_anterior | date: "EEE dd/MM HH:mm" }} → <strong>{{ a.inicio | date: "EEE dd/MM HH:mm" }}</strong>
                  · {{ a.barbeiro_anterior ? a.barbeiro_anterior + ' → ' : '' }}{{ a.barbeiro }}
                }
                <small>{{ a.origem === 'cliente' ? 'Pelo cliente (link)' : 'Pelo painel' }} · {{ a.quando | date: "dd/MM 'às' HH:mm" }}</small>
              </span>
            </button>
          } @empty {
            <p class="sino-vazio">Nenhum cancelamento ou remarcação por enquanto.</p>
          }
        </div>
      }
    </div>
  `,
  styles: `
    .sino { position: relative; }
    .sino-botao {
      position: relative; display: grid; place-items: center; width: 36px; height: 36px;
      color: var(--cor-texto); background: var(--cor-superficie); border: 1px solid var(--cor-borda);
      border-radius: var(--radius-buttons, 8px); cursor: pointer;
    }
    .sino-botao svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    .sino-botao.tem-novos { color: var(--cor-erro); border-color: var(--cor-erro); background: var(--cor-erro-fundo); }
    .sino-contador {
      position: absolute; top: -7px; right: -7px; min-width: 18px; height: 18px; padding: 0 4px;
      border-radius: 9px; background: var(--cor-erro); color: #fff; font-size: 11px; font-weight: 700; line-height: 18px; text-align: center;
    }
    .sino-fundo { position: fixed; inset: 0; z-index: 40; }
    .sino-lista {
      position: absolute; z-index: 41; top: calc(100% + 6px); left: 0; width: min(380px, calc(100vw - 32px));
      max-height: 420px; overflow-y: auto; padding: 8px; background: var(--cor-superficie);
      border: 1px solid var(--cor-borda); border-radius: 12px; box-shadow: 0 12px 32px rgb(0 0 0 / 0.16);
    }
    .sino-titulo { display: block; padding: 6px 8px 8px; }
    .sino-item {
      display: flex; gap: 10px; width: 100%; padding: 10px 8px; text-align: left; font: inherit; color: inherit;
      background: none; border: 0; border-radius: 8px; cursor: pointer;
    }
    .sino-item:hover { background: var(--cor-fundo); }
    .sino-item.novo { background: var(--cor-primaria-suave); }
    .sino-tipo {
      flex: none; align-self: flex-start; padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: 600;
      color: var(--cor-primaria); border: 1px solid currentColor;
    }
    .sino-tipo.cancelado { color: var(--cor-erro); }
    .sino-texto { display: flex; flex-direction: column; gap: 2px; font-size: 14px; }
    .sino-texto small { color: var(--cor-texto-suave); }
    .sino-vazio { margin: 8px; color: var(--cor-texto-suave); font-size: 14px; }
    @media (max-width: 600px) {
      .sino-lista { position: fixed; top: 72px; left: 16px; right: 16px; width: auto; }
    }
  `,
})
export class SinoAvisos implements OnInit, OnDestroy {
  private readonly api = inject(AvisosService);
  private timer?: ReturnType<typeof setInterval>;

  /** Aviso escolhido: a agenda abre o dia (e o horário, quando der). */
  readonly abrir = output<AvisoAgenda>();

  protected readonly avisos = signal<AvisoAgenda[]>([]);
  protected readonly naoVistos = signal(0);
  protected readonly aberto = signal(false);

  ngOnInit(): void {
    this.carregar();
    this.timer = setInterval(() => this.carregar(), ATUALIZAR_A_CADA_MS);
  }

  ngOnDestroy(): void {
    clearInterval(this.timer);
  }

  /** Atualiza a lista (a agenda chama depois de mudanças). */
  carregar(): void {
    this.api.listar().subscribe({
      next: (r) => {
        if (!this.aberto()) this.avisos.set(r.avisos);
        this.naoVistos.set(r.nao_vistos);
      },
      error: () => undefined, // o sino não atrapalha a agenda
    });
  }

  protected alternar(): void {
    if (this.aberto()) {
      this.aberto.set(false);
      this.avisos.update((lista) => lista.map((a) => ({ ...a, visto: true })));
      return;
    }
    this.aberto.set(true);
    if (this.naoVistos() > 0) {
      this.naoVistos.set(0);
      this.api.marcarVistos().subscribe({ error: () => undefined });
    }
  }

  protected escolher(aviso: AvisoAgenda): void {
    this.aberto.set(false);
    this.avisos.update((lista) => lista.map((a) => ({ ...a, visto: true })));
    this.abrir.emit(aviso);
  }
}
