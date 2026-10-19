import { Component, computed, inject } from '@angular/core';
import { AuthService } from '../../core/auth.service';

const NOMES_PERFIL = { dono: 'Dono', recepcao: 'Recepção', barbeiro: 'Barbeiro' } as const;

@Component({
  selector: 'app-painel',
  template: `
    @if (sessao(); as s) {
      <header class="topo">
        <div>
          <strong class="marca-topo">{{ s.empresa.nome }}</strong>
          <span class="perfil">{{ nomePerfil() }}</span>
        </div>
        <div class="usuario-topo">
          <span>{{ s.usuario.nome }}</span>
          <button class="botao botao-secundario" type="button" (click)="sair()">Sair</button>
        </div>
      </header>

      <main class="conteudo">
        <h1>Olá, {{ primeiroNome() }}!</h1>
        <p>Seu acesso está pronto. Os próximos módulos (serviços, barbeiros, clientes e agenda) aparecem aqui.</p>
        <p class="link-publico">
          Link de agendamento da barbearia: <code>/{{ s.empresa.slug }}</code>
        </p>
      </main>
    }
  `,
})
export class Painel {
  private readonly auth = inject(AuthService);

  protected readonly sessao = this.auth.sessao;
  protected readonly primeiroNome = computed(() => this.sessao()?.usuario.nome.split(' ')[0] ?? '');
  protected readonly nomePerfil = computed(() => {
    const perfil = this.sessao()?.usuario.perfil;
    return perfil ? NOMES_PERFIL[perfil] : '';
  });

  sair(): void {
    this.auth.logout();
  }
}
