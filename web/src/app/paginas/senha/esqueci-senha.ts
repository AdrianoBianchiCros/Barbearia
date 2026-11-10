import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { mensagemDeErro } from '../../core/erros';
import { RodapeLegal } from '../../layout/rodape-legal';
import { VitrineAcesso } from '../../layout/vitrine-acesso';

/** Pede o link de redefinição de senha: o usuário informa o e-mail cadastrado. */
@Component({
  selector: 'app-esqueci-senha',
  imports: [ReactiveFormsModule, RouterLink, RodapeLegal, VitrineAcesso],
  template: `
    <app-vitrine-acesso>
      <form class="cartao" [formGroup]="form" (ngSubmit)="enviar()" novalidate>
        <h1 class="marca">Esqueci a senha</h1>
        <p class="subtitulo">Digite o e-mail cadastrado. Vamos enviar um link para você criar uma nova senha.</p>

        @if (enviado()) {
          <div class="aviso-sucesso" role="status">{{ enviado() }}</div>
          <p class="subtitulo">Não chegou? Confira a caixa de spam ou tente de novo em alguns minutos.</p>
        } @else {
          @if (erro()) {
            <div class="alerta" role="alert">{{ erro() }}</div>
          }

          <label class="campo">
            <span>E-mail</span>
            <input type="email" formControlName="email" autocomplete="email" autofocus />
            @if (form.controls.email.touched && form.controls.email.invalid) {
              <small class="erro-campo">Informe um e-mail válido.</small>
            }
          </label>

          <button class="botao" type="submit" [disabled]="enviando()">
            {{ enviando() ? 'Enviando…' : 'Enviar link' }}
          </button>
        }

        <p class="rodape-cartao"><a routerLink="/login">Voltar para o login</a></p>
        <app-rodape-legal />
      </form>
    </app-vitrine-acesso>
  `,
})
export class EsqueciSenha {
  private readonly auth = inject(AuthService);

  protected readonly form = inject(FormBuilder).nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
  });
  protected readonly enviando = signal(false);
  protected readonly enviado = signal('');
  protected readonly erro = signal('');

  enviar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.enviando.set(true);
    this.erro.set('');

    this.auth.esqueciSenha(this.form.getRawValue().email).subscribe({
      next: (r) => this.enviado.set(r.message),
      error: (e) => {
        this.erro.set(mensagemDeErro(e));
        this.enviando.set(false);
      },
    });
  }
}
