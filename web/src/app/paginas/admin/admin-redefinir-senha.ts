import { Component, inject, signal } from '@angular/core';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AdminService } from '../../core/admin.service';
import { errosPorCampo, mensagemDeErro } from '../../core/erros';
import { VerSenha } from '../../layout/ver-senha';

const senhasIguais = (grupo: AbstractControl): ValidationErrors | null =>
  grupo.get('password')?.value === grupo.get('password_confirmation')?.value ? null : { senhasDiferentes: true };

/** Nova senha do administrador, aberta pelo link do e-mail: /admin/redefinir-senha?token=...&email=... */
@Component({
  selector: 'app-admin-redefinir-senha',
  imports: [ReactiveFormsModule, RouterLink, VerSenha],
  template: `
    <main class="tela-acesso">
      <form class="cartao" [formGroup]="form" (ngSubmit)="salvar()" novalidate>
        <h1 class="marca">Nova senha</h1>

        @if (!token || !email) {
          <div class="alerta" role="alert">Link incompleto. Abra de novo o link do e-mail ou peça um novo.</div>
          <p class="rodape-cartao"><a routerLink="/admin/esqueci-senha">Pedir um novo link</a></p>
        } @else {
          <p class="subtitulo">Crie a nova senha de administrador para {{ email }}.</p>

          @if (erro()) {
            <div class="alerta" role="alert">
              {{ erro() }}
              @if (linkInvalido()) {
                <a routerLink="/admin/esqueci-senha">Pedir um novo link</a>
              }
            </div>
          }

          <div class="campo">
            <label for="nova-senha-admin"><span>Nova senha</span></label>
            <div class="campo-senha">
              <input #senha id="nova-senha-admin" type="password" formControlName="password" autocomplete="new-password" autofocus />
              <app-ver-senha [campo]="senha" />
            </div>
            @if (form.controls.password.touched && form.controls.password.invalid) {
              <small class="erro-campo">Mínimo de 8 caracteres, com letras e números.</small>
            }
          </div>

          <div class="campo">
            <label for="confirmar-senha-admin"><span>Confirme a nova senha</span></label>
            <div class="campo-senha">
              <input #confirmacao id="confirmar-senha-admin" type="password" formControlName="password_confirmation" autocomplete="new-password" />
              <app-ver-senha [campo]="confirmacao" />
            </div>
            @if (form.controls.password_confirmation.touched && form.hasError('senhasDiferentes')) {
              <small class="erro-campo">As senhas não conferem.</small>
            }
          </div>

          <button class="botao" type="submit" [disabled]="salvando()">
            {{ salvando() ? 'Salvando…' : 'Salvar nova senha' }}
          </button>

          <p class="rodape-cartao"><a routerLink="/admin/login">Voltar para o login</a></p>
        }
      </form>
    </main>
  `,
})
export class AdminRedefinirSenha {
  private readonly admin = inject(AdminService);
  private readonly router = inject(Router);
  private readonly parametros = inject(ActivatedRoute).snapshot.queryParamMap;

  protected readonly token = this.parametros.get('token') ?? '';
  protected readonly email = this.parametros.get('email') ?? '';

  protected readonly form = inject(FormBuilder).nonNullable.group(
    {
      password: ['', [Validators.required, Validators.minLength(8), Validators.pattern(/^(?=.*[A-Za-z])(?=.*\d).+$/)]],
      password_confirmation: ['', Validators.required],
    },
    { validators: senhasIguais },
  );
  protected readonly salvando = signal(false);
  protected readonly erro = signal('');
  protected readonly linkInvalido = signal(false);

  salvar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.salvando.set(true);
    this.erro.set('');
    this.linkInvalido.set(false);

    this.admin.redefinirSenha({ token: this.token, email: this.email, ...this.form.getRawValue() }).subscribe({
      next: () => {
        this.admin.limpar(); // o servidor encerrou todas as sessões desse administrador
        this.router.navigate(['/admin/login'], { queryParams: { senha: 'alterada' } });
      },
      error: (e) => {
        this.erro.set(mensagemDeErro(e));
        this.linkInvalido.set('token' in errosPorCampo(e));
        this.salvando.set(false);
      },
    });
  }
}
