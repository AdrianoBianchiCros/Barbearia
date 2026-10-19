import { Component, computed, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService, Perfil } from '../../core/auth.service';

interface Secao {
  rota: string;
  titulo: string;
  descricao: string;
  perfis: Perfil[];
}

const SECOES: Secao[] = [
  { rota: 'caixa', titulo: 'Caixa', descricao: 'Recebimentos do dia', perfis: ['dono', 'recepcao'] },
  { rota: 'comissoes', titulo: 'Comissões', descricao: 'Valores por barbeiro', perfis: ['dono', 'recepcao', 'barbeiro'] },
  { rota: 'despesas', titulo: 'Despesas', descricao: 'Contas a pagar', perfis: ['dono'] },
];

/** Área do financeiro: caixa, comissões e despesas. */
@Component({
  selector: 'app-financeiro',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
    <div class="cabecalho-pagina">
      <div>
        <h1>Financeiro</h1>
        <p class="texto-suave">Caixa do dia, comissões da equipe e contas da barbearia.</p>
      </div>
    </div>

    <div class="config-layout">
      <nav class="config-nav" aria-label="Seções do financeiro">
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
export class Financeiro {
  private readonly auth = inject(AuthService);

  protected readonly secoes = computed(() => {
    const perfil = this.auth.sessao()?.usuario.perfil;
    return SECOES.filter((s) => perfil && s.perfis.includes(perfil));
  });
}
