import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService, Perfil } from '../core/auth.service';
import { AvisoService } from '../core/aviso.service';
import { ENDERECO_SITE, PLATAFORMA } from '../core/plataforma';
import { TemaService } from '../core/tema.service';

interface ItemMenu {
  rota: string;
  rotulo: string;
  icone: string;
  emBreve?: boolean;
  /** Link para a página pública da barbearia (abre em nova aba); `rota` serve só de identificador. */
  paginaPublica?: boolean;
  /** Texto curto mostrado abaixo do rótulo. */
  descricao?: string;
  /** Perfis que veem o item (todos, se omitido). */
  perfis?: Perfil[];
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
      { rota: '/financeiro', rotulo: 'Financeiro', icone: 'financeiro' },
      { rota: '/relatorios', rotulo: 'Relatórios', icone: 'relatorios', perfis: ['dono'] },
      { rota: '/configuracoes', rotulo: 'Configurações', icone: 'config' },
      { rota: 'pagina-publica', rotulo: 'Minha página', icone: 'pagina', paginaPublica: true, descricao: 'Agendamento online dos clientes' },
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
  pagina: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM3.5 9h17M3.5 15h17M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18',
  externo: 'M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5',
  sair: 'M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H3',
  lua: 'M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11Z',
  sol: 'M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4',
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
            @for (grupo of menu(); track grupo.titulo) {
              <span class="menu-grupo"><span class="menu-rotulo">{{ grupo.titulo }}</span></span>
              @for (item of grupo.itens; track item.rota) {
                @if (item.emBreve) {
                  <span class="menu-item desativado" [title]="item.rotulo + ' (disponível em breve)'">
                    <svg class="menu-icone" viewBox="0 0 24 24" aria-hidden="true"><path [attr.d]="icones[item.icone]" /></svg>
                    <span class="menu-rotulo">{{ item.rotulo }}</span>
                    <small class="em-breve">em breve</small>
                  </span>
                } @else if (item.paginaPublica) {
                  <a class="menu-item" [href]="enderecoPagina()" target="_blank" rel="noopener" (click)="menuAberto.set(false)"
                    [title]="recolhido() ? item.rotulo : enderecoPagina()" [attr.aria-label]="item.rotulo + ' (abre em nova aba)'">
                    <svg class="menu-icone" viewBox="0 0 24 24" aria-hidden="true"><path [attr.d]="icones[item.icone]" /></svg>
                    <span class="menu-rotulo menu-textos">
                      <span>{{ item.rotulo }}</span>
                      <small class="menu-descricao">{{ item.descricao }}</small>
                    </span>
                    <svg class="menu-icone menu-icone-externo menu-rotulo" viewBox="0 0 24 24" aria-hidden="true"><path [attr.d]="icones['externo']" /></svg>
                  </a>
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
            <button class="botao-icone" type="button" (click)="tema.alternar()"
              [title]="tema.tema() === 'escuro' ? 'Usar modo claro' : 'Usar modo noturno'"
              [attr.aria-label]="tema.tema() === 'escuro' ? 'Usar modo claro' : 'Usar modo noturno'">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path [attr.d]="icones[tema.tema() === 'escuro' ? 'sol' : 'lua']" /></svg>
            </button>
            <button class="botao-icone botao-sair" type="button" title="Sair" aria-label="Sair" (click)="auth.logout()">
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
          @if (avisoAcesso(); as a) {
            <div class="faixa-acesso" [class.faixa-acesso-carencia]="a.carencia" role="status">{{ a.texto }}</div>
          }
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
  protected readonly tema = inject(TemaService);
  protected readonly sessao = this.auth.sessao;
  protected readonly menuAberto = signal(false);
  /** Menu só com ícones (desktop). A escolha fica salva neste navegador. */
  protected readonly recolhido = signal(lerPreferencia());
  /** Menu filtrado pelo perfil do usuário (grupos vazios somem). */
  protected readonly menu = computed(() => {
    const perfil = this.sessao()?.usuario.perfil;
    return MENU.map((g) => ({ ...g, itens: g.itens.filter((i) => !i.perfis || (perfil && i.perfis.includes(perfil))) })).filter(
      (g) => g.itens.length,
    );
  });
  protected readonly icones = ICONES;
  /** Endereço da página pública da barbearia, por exemplo app.abianchitech.com/saas/barbearia/{slug}. */
  protected readonly enderecoPagina = computed(() => `${ENDERECO_SITE}/${this.sessao()?.empresa.slug ?? ''}`);

  /** Aviso de vencimento do plano para o dono (a API só manda o prazo para ele). */
  protected readonly avisoAcesso = computed(() => {
    const acesso = this.sessao()?.acesso;
    if (!acesso?.vence_em || !['vencendo', 'carencia'].includes(acesso.situacao)) return null;

    const data = (d: string | null) => (d ?? '').split('-').reverse().join('/');
    const dias = acesso.dias_restantes ?? 0;
    if (acesso.situacao === 'carencia') {
      return {
        carencia: true,
        texto: `O plano venceu em ${data(acesso.vence_em)}. O sistema será bloqueado depois de ${data(acesso.bloqueia_em)}. Para renovar, fale com a ${PLATAFORMA.nome}.`,
      };
    }
    const quando = dias === 0 ? 'vence hoje' : dias === 1 ? 'vence amanhã' : `vence em ${dias} dias (${data(acesso.vence_em)})`;
    return { carencia: false, texto: `O plano da barbearia ${quando}. Para renovar, fale com a ${PLATAFORMA.nome}.` };
  });

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
