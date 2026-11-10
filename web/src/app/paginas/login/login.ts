import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { mensagemDeErro } from '../../core/erros';
import { RodapeLegal } from '../../layout/rodape-legal';
import { VerSenha } from '../../layout/ver-senha';

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule, RouterLink, RodapeLegal, VerSenha],
  template: `
    <main class="tela-acesso">
      <form class="cartao" [formGroup]="form" (ngSubmit)="entrar()" novalidate>
        <h1 class="marca">Barbearia</h1>
        <p class="subtitulo">Entre para acessar a agenda da sua barbearia.</p>

        @if (senhaAlterada && !erro()) {
          <div class="aviso-sucesso" role="status">Senha alterada. Entre com a nova senha.</div>
        }
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

        <div class="campo">
          <div class="rotulo-com-link">
            <label for="senha">Senha</label>
            <a routerLink="/esqueci-senha">Esqueci a senha</a>
          </div>
          <div class="campo-senha">
            <input #senha id="senha" type="password" formControlName="password" autocomplete="current-password" />
            <app-ver-senha [campo]="senha" />
          </div>
          @if (form.controls.password.touched && form.controls.password.invalid) {
            <small class="erro-campo">Informe sua senha.</small>
          }
        </div>

        <button class="botao" type="submit" [disabled]="enviando()">
          {{ enviando() ? 'Entrando…' : 'Entrar' }}
        </button>

        <p class="rodape-cartao">Ainda não tem conta? <a routerLink="/cadastro">Cadastre sua barbearia</a></p>
        <app-rodape-legal />
      </form>
    </main>
  `,
})
export class Login {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  /** Veio da tela de nova senha (/login?senha=alterada). */
  protected readonly senhaAlterada = inject(ActivatedRoute).snapshot.queryParamMap.get('senha') === 'alterada';

  protected readonly form = inject(FormBuilder).nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', Validators.required],
  });
  protected readonly enviando = signal(false);
  protected readonly erro = signal('');

  entrar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.enviando.set(true);
    this.erro.set('');

    const { email, password } = this.form.getRawValue();
    this.auth.login(email, password).subscribe({
      next: () => this.router.navigate(['/agenda']),
      error: (e) => {
        this.erro.set(mensagemDeErro(e));
        this.enviando.set(false);
      },
    });
  }
}
