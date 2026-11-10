import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { mensagemDeErro } from '../../core/erros';
import { PLATAFORMA } from '../../core/plataforma';
import { RodapeLegal } from '../../layout/rodape-legal';
import { VitrineAcesso } from '../../layout/vitrine-acesso';
import { VerSenha } from '../../layout/ver-senha';

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule, RouterLink, RodapeLegal, VitrineAcesso, VerSenha],
  template: `
    <app-vitrine-acesso>
      <form class="cartao" [formGroup]="form" (ngSubmit)="entrar()" novalidate>
        <h1 class="marca">{{ saudacao }}.<br /><em>Bora abrir a casa?</em></h1>
        <p class="subtitulo">Entre para abrir a agenda de hoje da sua barbearia.</p>

        @if (acessoVencido && !erro()) {
          <div class="alerta" role="alert">O acesso da barbearia está bloqueado porque o plano venceu. Para renovar, fale com a {{ plataforma.nome }}.</div>
        }
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
          <label for="senha"><span>Senha</span></label>
          <div class="campo-senha">
            <input #senha id="senha" type="password" formControlName="password" autocomplete="current-password" />
            <app-ver-senha [campo]="senha" />
          </div>
          @if (form.controls.password.touched && form.controls.password.invalid) {
            <small class="erro-campo">Informe sua senha.</small>
          }
          <a class="link-abaixo-campo" routerLink="/esqueci-senha">Esqueci a senha</a>
        </div>

        <button class="botao" type="submit" [disabled]="enviando()">
          {{ enviando() ? 'Abrindo a agenda…' : 'Entrar e abrir a agenda →' }}
        </button>

        <p class="rodape-cartao">O acesso é liberado pela {{ plataforma.nome }} na contratação do plano.</p>
        <app-rodape-legal />
      </form>
    </app-vitrine-acesso>
  `,
})
export class Login {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  private readonly parametros = inject(ActivatedRoute).snapshot.queryParamMap;
  /** Veio da tela de nova senha (/login?senha=alterada). */
  protected readonly senhaAlterada = this.parametros.get('senha') === 'alterada';
  /** A sessão foi encerrada porque o prazo do plano acabou (/login?acesso=vencido). */
  protected readonly acessoVencido = this.parametros.get('acesso') === 'vencido';
  protected readonly plataforma = PLATAFORMA;
  protected readonly saudacao = (() => {
    const hora = new Date().getHours();
    return hora < 12 ? 'Bom dia' : hora < 18 ? 'Boa tarde' : 'Boa noite';
  })();

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
