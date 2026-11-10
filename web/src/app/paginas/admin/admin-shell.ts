import { Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AdminService } from '../../core/admin.service';
import { AvisoService } from '../../core/aviso.service';
import { PLATAFORMA } from '../../core/plataforma';

/** Estrutura da área do administrador: barra no topo e conteúdo. */
@Component({
  selector: 'app-admin-shell',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
    <header class="topo-admin">
      <a class="topo-admin-marca" routerLink="/admin/barbearias">
        <span class="marca-icone">A</span>
        <span><strong>Administração</strong><small>{{ plataforma.nome }}</small></span>
      </a>
      <nav>
        <a routerLink="/admin/barbearias" routerLinkActive="ativo" [routerLinkActiveOptions]="{ exact: true }">Barbearias</a>
        <a routerLink="/admin/barbearias/nova" routerLinkActive="ativo">Nova barbearia</a>
      </nav>
      <div class="topo-admin-usuario">
        <small>{{ admin.admin()?.nome }}</small>
        <button class="botao botao-secundario botao-auto" type="button" (click)="admin.logout()">Sair</button>
      </div>
    </header>
    <main class="conteudo conteudo-admin">
      <router-outlet />
    </main>

    @if (aviso.atual(); as a) {
      <div class="aviso" [class.aviso-erro]="a.tipo === 'erro'" role="status">{{ a.texto }}</div>
    }
  `,
})
export class AdminShell {
  protected readonly admin = inject(AdminService);
  protected readonly aviso = inject(AvisoService);
  protected readonly plataforma = PLATAFORMA;
}
