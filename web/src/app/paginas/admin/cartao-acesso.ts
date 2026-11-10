import { Component, computed, inject, input } from '@angular/core';
import { AvisoService } from '../../core/aviso.service';
import { DadosAcesso } from '../../core/admin.service';

/** Login e senha provisória do dono, para copiar e mandar (a senha não aparece de novo). */
@Component({
  selector: 'app-cartao-acesso',
  template: `
    <section class="cartao-secao cartao-acesso">
      <h2>Acesso do dono</h2>
      <p class="texto-suave">Anote ou envie agora: a senha provisória não aparece de novo. O dono pode trocá-la por "Esqueci a senha", na tela de entrada.</p>
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
      `Seu acesso ao sistema da barbearia está liberado!\n\nEndereço: ${this.acesso().painel_url}\nE-mail: ${this.acesso().email}\nSenha provisória: ${this.acesso().senha_provisoria}\n\nPara trocar a senha, use "Esqueci a senha" na tela de entrada.`,
  );

  copiar(): void {
    navigator.clipboard?.writeText(this.mensagem()).then(
      () => this.aviso.sucesso('Mensagem copiada.'),
      () => this.aviso.erro('Não foi possível copiar. Selecione o texto manualmente.'),
    ) ?? this.aviso.erro('Não foi possível copiar. Selecione o texto manualmente.');
  }
}
