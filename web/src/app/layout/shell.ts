import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService, Perfil } from '../core/auth.service';
import { AvisoService } from '../core/aviso.service';

interface ItemMenu {
  rota: string;
  rotulo: string;
  icone: string;
  emBreve?: boolean;
}

interface GrupoMenu {
  titulo: string;
  itens: ItemMenu[];
}

const MENU: GrupoMenu[] = [
  {
    titulo: 'Atendimento',
    itens: [
      { rota: '/agenda', rotulo: 'Agenda do dia', icone: 'agenda' },
      { rota: '/clientes', rotulo: 'Clientes', icone: 'clientes' },
    ],
  },
  {
    titulo: 'Gestão',
    itens: [
      { rota: '/financeiro', rotulo: 'Financeiro', icone: 'financeiro', emBreve: true },
      { rota: '/relatorios', rotulo: 'Relatórios', icone: 'relatorios', emBreve: true },
      { rota: '/configuracoes', rotulo: 'Configurações', icone: 'config' },
    ],
  },
];

/** Ícones em SVG (traço simples), para não depender de biblioteca externa. */
const ICONES: Record<string, string> = {
  agenda: 'M8 2v3M16 2v3M3.5 9h17M5 4.5h14A1.5 1.5 0 0 1 20.5 6v13a1.5 1.5 0 0 1-1.5 1.5H5A1.5 1.5 0 0 1 3.5 19V6A1.5 1.5 0 0 1 5 4.5Z',
  clientes: 'M16 19v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 17.5V19M10 10.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM20 19v-1.5a3.5 3.5 0 0 0-2.5-3.35M15 3.6a3.5 3.5 0 0 1 0 6.8',
  financeiro: 'M12 3v18M16.5 7.5c0-1.7-2-3-4.5-3s-4.5 1.3-4.5 3 2 2.6 4.5 3 4.5 1.3 4.5 3-2 3-4.5 3-4.5-1.3-4.5-3',
  relatorios: 'M4 20V10M10 20V4M16 20v-7M21 20H3',
  config: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z',
  sair: 'M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H3',
};

const CHAVE_RECOLHIDO = 'barbearia.menuRecolhido';

function lerPreferencia(): boolean {
  try {
    return localStorage.getItem(CHAVE_RECOLHIDO) === '1';
  } catch {
    return false;
  }
}

const NOMES_PERFIL: Record<Perfil, string> = { dono: 'Dono', recepcao: 'Recepção', barbeiro: 'Barbeiro' };

/** Estrutura da área logada: menu lateral e conteúdo. */
@Component({
  selector: 'app-shell',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
    @if (sessao(); as s) {
      <div class="shell" [class.menu-aberto]="menuAberto()" [class.menu-recolhido]="recolhido()">
        <aside class="menu-lateral">
          <button class="botao-recolher" type="button" (click)="alternarRecolhido()"
            [attr.aria-label]="recolhido() ? 'Expandir menu' : 'Recolher menu'"
            [title]="recolhido() ? 'Expandir menu' : 'Recolher menu'" [attr.aria-expanded]="!recolhido()">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
          </button>
          <div class="menu-marca" [title]="recolhido() ? s.empresa.nome : ''">
            <span class="marca-icone">{{ s.empresa.nome.charAt(0) }}</span>
            <div class="marca-textos">
              <strong>{{ s.empresa.nome }}</strong>
              <small>Painel da barbearia</small>
            </div>
          </div>

          <nav>
            @for (grupo of menu; track grupo.titulo) {
              <span class="menu-grupo"><span class="menu-rotulo">{{ grupo.titulo }}</span></span>
              @for (item of grupo.itens; track item.rota) {
                @if (item.emBreve) {
                  <span class="menu-item desativado" [title]="item.rotulo + ' (disponível em breve)'">
                    <svg class="menu-icone" viewBox="0 0 24 24" aria-hidden="true"><path [attr.d]="icones[item.icone]" /></svg>
                    <span class="menu-rotulo">{{ item.rotulo }}</span>
                    <small class="em-breve">em breve</small>
                  </span>
                } @else {
                  <a class="menu-item" [routerLink]="item.rota" routerLinkActive="ativo" (click)="menuAberto.set(false)"
                    [title]="recolhido() ? item.rotulo : ''" [attr.aria-label]="item.rotulo">
                    <svg class="menu-icone" viewBox="0 0 24 24" aria-hidden="true"><path [attr.d]="icones[item.icone]" /></svg>
                    <span class="menu-rotulo">{{ item.rotulo }}</span>
                  </a>
                }
              }
            }
          </nav>

          <div class="menu-usuario">
            <span class="avatar-usuario" [title]="recolhido() ? s.usuario.nome + ' (' + nomePerfil() + ')' : ''">{{ iniciais() }}</span>
            <div class="menu-usuario-textos">
              <strong>{{ s.usuario.nome }}</strong>
              <small>{{ nomePerfil() }} · {{ s.usuario.email }}</small>
            </div>
            <button class="botao-icone" type="button" title="Sair" aria-label="Sair" (click)="auth.logout()">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path [attr.d]="icones['sair']" /></svg>
            </button>
          </div>
        </aside>
        <div class="fundo-menu" (click)="menuAberto.set(false)"></div>

        <div class="area-principal">
          <header class="topo-celular">
            <button class="botao-menu" type="button" aria-label="Abrir menu" (click)="menuAberto.set(true)">☰</button>
            <strong>{{ s.empresa.nome }}</strong>
          </header>
          <main class="conteudo">
            <router-outlet />
          </main>
        </div>
      </div>
    }

    @if (aviso.atual(); as a) {
      <div class="aviso" [class.aviso-erro]="a.tipo === 'erro'" role="status">{{ a.texto }}</div>
    }
  `,
})
export class Shell {
  protected readonly auth = inject(AuthService);
  protected readonly aviso = inject(AvisoService);
  protected readonly sessao = this.auth.sessao;
  protected readonly menuAberto = signal(false);
  /** Menu só com ícones (desktop). A escolha fica salva neste navegador. */
  protected readonly recolhido = signal(lerPreferencia());
  protected readonly menu = MENU;
  protected readonly icones = ICONES;

  protected readonly nomePerfil = computed(() => {
    const perfil = this.sessao()?.usuario.perfil;
    return perfil ? NOMES_PERFIL[perfil] : '';
  });

  alternarRecolhido(): void {
    this.recolhido.update((v) => !v);
    try {
      localStorage.setItem(CHAVE_RECOLHIDO, this.recolhido() ? '1' : '0');
    } catch {
      // armazenamento indisponível: vale só até recarregar a página
    }
  }

  protected readonly iniciais = computed(() => {
    const partes = (this.sessao()?.usuario.nome ?? '').trim().split(/\s+/);
    return ((partes[0]?.[0] ?? '') + (partes.length > 1 ? partes[partes.length - 1][0] : '')).toUpperCase();
  });
}
