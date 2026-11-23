import { Component, inject, signal } from '@angular/core';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService, telaInicial } from '../../core/auth.service';
import { AvisoService } from '../../core/aviso.service';
import { mensagemDeErro } from '../../core/erros';
import { RodapeLegal } from '../../layout/rodape-legal';
import { VitrineAcesso } from '../../layout/vitrine-acesso';
import { VerSenha } from '../../layout/ver-senha';

const senhasIguais = (grupo: AbstractControl): ValidationErrors | null =>
  grupo.get('password')?.value === grupo.get('password_confirmation')?.value ? null : { senhasDiferentes: true };

/** Primeiro acesso com a senha provisória gerada pelo administrador: o usuário cria a própria senha. */
@Component({
  selector: 'app-trocar-senha',
  imports: [ReactiveFormsModule, RodapeLegal, VitrineAcesso, VerSenha],
  template: `
    <app-vitrine-acesso>
      <form class="cartao" [formGroup]="form" (ngSubmit)="salvar()" novalidate>
        <h1 class="marca">Crie sua senha</h1>
        <p class="subtitulo">Você entrou com a senha provisória. Para continuar, crie a sua própria senha.</p>

        @if (erro()) {
          <div class="alerta" role="alert">{{ erro() }}</div>
        }

        <div class="campo">
          <label for="nova-senha"><span>Nova senha</span></label>
          <div class="campo-senha">
            <input #senha id="nova-senha" type="password" formControlName="password" autocomplete="new-password" autofocus />
            <app-ver-senha [campo]="senha" />
          </div>
          @if (form.controls.password.touched && form.controls.password.invalid) {
            <small class="erro-campo">Mínimo de 8 caracteres, com letras e números.</small>
          }
        </div>

        <div class="campo">
          <label for="confirmar-senha"><span>Confirme a nova senha</span></label>
          <div class="campo-senha">
            <input #confirmacao id="confirmar-senha" type="password" formControlName="password_confirmation" autocomplete="new-password" />
            <app-ver-senha [campo]="confirmacao" />
          </div>
          @if (form.controls.password_confirmation.touched && form.hasError('senhasDiferentes')) {
            <small class="erro-campo">As senhas não conferem.</small>
          }
        </div>

        <button class="botao" type="submit" [disabled]="salvando()">
          {{ salvando() ? 'Salvando…' : 'Salvar e continuar →' }}
        </button>

        <p class="rodape-cartao"><a href="" (click)="$event.preventDefault(); sair()">Sair</a></p>
        <app-rodape-legal />
      </form>
    </app-vitrine-acesso>
  `,
})
export class TrocarSenha {
  private readonly auth = inject(AuthService);
  private readonly aviso = inject(AvisoService);
  private readonly router = inject(Router);

  protected readonly form = inject(FormBuilder).nonNullable.group(
    {
      password: ['', [Validators.required, Validators.minLength(8), Validators.pattern(/^(?=.*[A-Za-z])(?=.*\d).+$/)]],
      password_confirmation: ['', Validators.required],
    },
    { validators: senhasIguais },
  );
  protected readonly salvando = signal(false);
  protected readonly erro = signal('');

  salvar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.salvando.set(true);
    this.erro.set('');

    this.auth.trocarSenha(this.form.getRawValue()).subscribe({
      next: (s) => {
        this.aviso.sucesso('Senha criada. Use-a nos próximos acessos.');
        this.router.navigate([telaInicial(s.usuario)]);
      },
      error: (e) => {
        this.erro.set(mensagemDeErro(e));
        this.salvando.set(false);
      },
    });
  }

  sair(): void {
    this.auth.logout();
  }
}
