import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AdminService } from '../../core/admin.service';
import { mensagemDeErro } from '../../core/erros';

/** "Esqueci a senha" do administrador da plataforma: envia o link de redefinição por e-mail. */
@Component({
  selector: 'app-admin-esqueci-senha',
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <main class="tela-acesso">
      <form class="cartao" [formGroup]="form" (ngSubmit)="enviar()" novalidate>
        <h1 class="marca">Esqueci a senha</h1>
        <p class="subtitulo">Digite o e-mail de administrador. Vamos enviar um link para você criar uma nova senha.</p>

        @if (enviado()) {
          <div class="aviso-sucesso" role="status">{{ enviado() }}</div>
        }
        @if (erro()) {
          <div class="alerta" role="alert">{{ erro() }}</div>
        }

        <label class="campo">
          <span>E-mail</span>
          <input type="email" formControlName="email" autocomplete="username" autofocus />
          @if (form.controls.email.touched && form.controls.email.invalid) {
            <small class="erro-campo">Informe um e-mail válido.</small>
          }
        </label>

        <button class="botao" type="submit" [disabled]="enviando()">
          {{ enviando() ? 'Enviando…' : 'Enviar link' }}
        </button>

        <p class="rodape-cartao"><a routerLink="/admin/login">Voltar para o login</a></p>
      </form>
    </main>
  `,
})
export class AdminEsqueciSenha {
  private readonly admin = inject(AdminService);

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
    this.enviado.set('');
    this.erro.set('');

    this.admin.esqueciSenha(this.form.getRawValue().email).subscribe({
      next: (r) => {
        this.enviado.set(r.message);
        this.enviando.set(false);
      },
      error: (e) => {
        this.erro.set(mensagemDeErro(e));
        this.enviando.set(false);
      },
    });
  }
}
