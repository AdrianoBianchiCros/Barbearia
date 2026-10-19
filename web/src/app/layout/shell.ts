import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService, Perfil } from '../core/auth.service';
import { AvisoService } from '../core/aviso.service';

interface ItemMenu {
  rota: string;
  rotulo: string;
  icone: string;
  perfis?: Perfil[];
  emBreve?: boolean;
}

const MENU: ItemMenu[] = [
  { rota: '/painel', rotulo: 'Início', icone: '⌂' },
  { rota: '/agenda', rotulo: 'Agenda', icone: '◷', emBreve: true },
  { rota: '/clientes', rotulo: 'Clientes', icone: '☺' },
  { rota: '/servicos', rotulo: 'Serviços', icone: '✂' },
  { rota: '/barbeiros', rotulo: 'Barbeiros', icone: '✦' },
  { rota: '/configuracoes', rotulo: 'Barbearia e página', icone: '⚙', perfis: ['dono'] },
];

const NOMES_PERFIL: Record<Perfil, string> = { dono: 'Dono', recepcao: 'Recepção', barbeiro: 'Barbeiro' };

/** Estrutura da área logada: menu lateral, topo e conteúdo. */
@Component({
  selector: 'app-shell',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
    @if (sessao(); as s) {
      <div class="shell" [class.menu-aberto]="menuAberto()">
        <aside class="menu-lateral">
          <div class="menu-marca">{{ s.empresa.nome }}</div>
          <nav>
            @for (item of menu(); track item.rota) {
              @if (item.emBreve) {
                <span class="menu-item desativado" title="Disponível em breve">
                  <span class="menu-icone" aria-hidden="true">{{ item.icone }}</span>{{ item.rotulo }}
                  <small class="em-breve">em breve</small>
                </span>
              } @else {
                <a class="menu-item" [routerLink]="item.rota" routerLinkActive="ativo" (click)="menuAberto.set(false)">
                  <span class="menu-icone" aria-hidden="true">{{ item.icone }}</span>{{ item.rotulo }}
                </a>
              }
            }
          </nav>
        </aside>
        <div class="fundo-menu" (click)="menuAberto.set(false)"></div>

        <div class="area-principal">
          <header class="topo">
            <button class="botao-menu" type="button" aria-label="Abrir menu" (click)="menuAberto.set(!menuAberto())">☰</button>
            <span class="perfil">{{ nomePerfil() }}</span>
            <div class="usuario-topo">
              <span class="nome-usuario">{{ s.usuario.nome }}</span>
              <button class="botao botao-secundario" type="button" (click)="auth.logout()">Sair</button>
            </div>
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

  protected readonly menu = computed(() => {
    const perfil = this.sessao()?.usuario.perfil;
    return MENU.filter((i) => !i.perfis || (perfil && i.perfis.includes(perfil)));
  });

  protected readonly nomePerfil = computed(() => {
    const perfil = this.sessao()?.usuario.perfil;
    return perfil ? NOMES_PERFIL[perfil] : '';
  });
}
