import { Component, computed, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService, Perfil } from '../../core/auth.service';

interface Secao {
  rota: string;
  titulo: string;
  descricao: string;
  perfis?: Perfil[];
}

const SECOES: Secao[] = [
  { rota: 'servicos', titulo: 'Serviços', descricao: 'Preços e duração' },
  { rota: 'barbeiros', titulo: 'Barbeiros', descricao: 'Equipe, expediente e folgas' },
  { rota: 'barbearia', titulo: 'Barbearia e página', descricao: 'Dados, endereço e página pública', perfis: ['dono'] },
];

/** Área de ajustes: agrupa as telas de cadastro em uma navegação própria. */
@Component({
  selector: 'app-configuracoes',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
    <div class="cabecalho-pagina">
      <div>
        <h1>Configurações</h1>
        <p class="texto-suave">Ajustes da barbearia usados pela agenda e pela página de agendamento.</p>
      </div>
    </div>

    <div class="config-layout">
      <nav class="config-nav" aria-label="Seções de configuração">
        @for (s of secoes(); track s.rota) {
          <a class="config-link" [routerLink]="s.rota" routerLinkActive="ativo">
            <strong>{{ s.titulo }}</strong>
            <small>{{ s.descricao }}</small>
          </a>
        }
      </nav>
      <section class="config-conteudo">
        <router-outlet />
      </section>
    </div>
  `,
})
export class Configuracoes {
  private readonly auth = inject(AuthService);

  protected readonly secoes = computed(() => {
    const perfil = this.auth.sessao()?.usuario.perfil;
    return SECOES.filter((s) => !s.perfis || (perfil && s.perfis.includes(perfil)));
  });
}
