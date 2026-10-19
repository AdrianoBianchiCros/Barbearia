import { Component, computed, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';

@Component({
  selector: 'app-painel',
  imports: [RouterLink],
  template: `
    <h1>Olá, {{ primeiroNome() }}!</h1>
    <p class="texto-suave">Configure sua barbearia nesta ordem para liberar a agenda:</p>

    <ol class="passos">
      <li><a routerLink="/servicos">Cadastre os serviços</a> com duração e preço.</li>
      <li><a routerLink="/barbeiros">Cadastre os barbeiros</a>, os serviços que cada um faz e o expediente.</li>
      <li><a routerLink="/clientes">Cadastre ou importe seus clientes</a>.</li>
      @if (dono()) {
        <li><a routerLink="/configuracoes">Personalize a página da barbearia</a> com logo, capa, endereço e redes sociais.</li>
      }
    </ol>
  `,
})
export class Painel {
  private readonly auth = inject(AuthService);
  protected readonly primeiroNome = computed(() => this.auth.sessao()?.usuario.nome.split(' ')[0] ?? '');
  protected readonly dono = computed(() => this.auth.sessao()?.usuario.perfil === 'dono');
}
