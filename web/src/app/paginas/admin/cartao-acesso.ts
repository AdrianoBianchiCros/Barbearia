import { Component, computed, inject, input } from '@angular/core';
import { AvisoService } from '../../core/aviso.service';
import { DadosAcesso } from '../../core/admin.service';

/** Login e senha provisória do dono, para copiar e mandar (a senha não aparece de novo). */
@Component({
  selector: 'app-cartao-acesso',
  template: `
    <section class="cartao-secao cartao-acesso">
      <h2>Acesso do dono</h2>
      @if (acesso().email_enviado) {
        <p class="texto-suave">Enviamos a senha provisória para {{ acesso().email }}, com o link para o dono criar a própria senha. No primeiro acesso com a provisória, o painel também pede a troca.</p>
      } @else {
        <p class="alerta" role="alert">Não foi possível enviar o e-mail agora. Mande a senha pelo WhatsApp: ela não aparece de novo.</p>
      }
      <p class="texto-suave">A senha provisória não aparece de novo depois que você sair desta tela.</p>
      <dl>
        <dt>Endereço</dt><dd>{{ acesso().painel_url }}</dd>
        <dt>E-mail</dt><dd>{{ acesso().email }}</dd>
        <dt>Senha provisória</dt><dd><code>{{ acesso().senha_provisoria }}</code></dd>
      </dl>
      <button class="botao botao-secundario botao-auto" type="button" (click)="copiar()">Copiar mensagem para o WhatsApp</button>
    </section>
  `,
})
export class CartaoAcesso {
  private readonly aviso = inject(AvisoService);
  readonly acesso = input.required<DadosAcesso>();

  private readonly mensagem = computed(
    () =>
      `Seu acesso ao sistema da barbearia está liberado!\n\nEndereço: ${this.acesso().painel_url}\nE-mail: ${this.acesso().email}\nSenha provisória: ${this.acesso().senha_provisoria}\n\nNo primeiro acesso, o sistema pede para você criar a sua própria senha.`,
  );

  copiar(): void {
    navigator.clipboard?.writeText(this.mensagem()).then(
      () => this.aviso.sucesso('Mensagem copiada.'),
      () => this.aviso.erro('Não foi possível copiar. Selecione o texto manualmente.'),
    ) ?? this.aviso.erro('Não foi possível copiar. Selecione o texto manualmente.');
  }
}
