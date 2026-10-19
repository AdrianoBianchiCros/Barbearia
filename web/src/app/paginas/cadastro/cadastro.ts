import { Component, inject, signal } from '@angular/core';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { errosPorCampo, mensagemDeErro } from '../../core/erros';

const senhasIguais = (grupo: AbstractControl): ValidationErrors | null =>
  grupo.get('password')?.value === grupo.get('password_confirmation')?.value ? null : { senhasDiferentes: true };

@Component({
  selector: 'app-cadastro',
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <main class="tela-acesso">
      <form class="cartao" [formGroup]="form" (ngSubmit)="cadastrar()" novalidate>
        <h1 class="marca">Cadastre sua barbearia</h1>
        <p class="subtitulo">Leva menos de um minuto. Depois você configura serviços e barbeiros.</p>

        @if (erro()) {
          <div class="alerta" role="alert">{{ erro() }}</div>
        }

        <label class="campo">
          <span>Nome da barbearia</span>
          <input formControlName="empresa_nome" autocomplete="organization" />
          @if (mostrarErro('empresa_nome')) {
            <small class="erro-campo">{{ errosApi()['empresa_nome'] || 'Informe o nome da barbearia.' }}</small>
          }
        </label>

        <label class="campo">
          <span>WhatsApp da barbearia <em>(opcional)</em></span>
          <input type="tel" formControlName="empresa_telefone" autocomplete="tel" placeholder="(11) 99999-0000" />
        </label>

        <label class="campo">
          <span>Seu nome</span>
          <input formControlName="nome" autocomplete="name" />
          @if (mostrarErro('nome')) {
            <small class="erro-campo">Informe seu nome.</small>
          }
        </label>

        <label class="campo">
          <span>E-mail</span>
          <input type="email" formControlName="email" autocomplete="email" />
          @if (mostrarErro('email')) {
            <small class="erro-campo">{{ errosApi()['email'] || 'Informe um e-mail válido.' }}</small>
          }
        </label>

        <label class="campo">
          <span>Senha</span>
          <input type="password" formControlName="password" autocomplete="new-password" />
          @if (mostrarErro('password')) {
            <small class="erro-campo">{{ errosApi()['password'] || 'Mínimo de 8 caracteres, com letras e números.' }}</small>
          }
        </label>

        <label class="campo">
          <span>Confirme a senha</span>
          <input type="password" formControlName="password_confirmation" autocomplete="new-password" />
          @if (form.controls.password_confirmation.touched && form.hasError('senhasDiferentes')) {
            <small class="erro-campo">As senhas não conferem.</small>
          }
        </label>

        <button class="botao" type="submit" [disabled]="enviando()">
          {{ enviando() ? 'Criando conta…' : 'Criar conta' }}
        </button>

        <p class="rodape-cartao">Já tem conta? <a routerLink="/login">Entrar</a></p>
      </form>
    </main>
  `,
})
export class Cadastro {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly form = inject(FormBuilder).nonNullable.group(
    {
      empresa_nome: ['', [Validators.required, Validators.maxLength(120)]],
      empresa_telefone: [''],
      nome: ['', [Validators.required, Validators.maxLength(120)]],
      email: ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required, Validators.minLength(8), Validators.pattern(/^(?=.*[A-Za-z])(?=.*\d).+$/)]],
      password_confirmation: ['', Validators.required],
    },
    { validators: senhasIguais },
  );
  protected readonly enviando = signal(false);
  protected readonly erro = signal('');
  protected readonly errosApi = signal<Record<string, string>>({});

  mostrarErro(campo: keyof typeof this.form.controls): boolean {
    const controle = this.form.controls[campo];
    return (controle.touched && controle.invalid) || !!this.errosApi()[campo];
  }

  cadastrar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.enviando.set(true);
    this.erro.set('');
    this.errosApi.set({});

    this.auth.registrar(this.form.getRawValue()).subscribe({
      next: () => this.router.navigate(['/agenda']),
      error: (e) => {
        const porCampo = errosPorCampo(e);
        this.errosApi.set(porCampo);
        if (Object.keys(porCampo).length === 0) this.erro.set(mensagemDeErro(e));
        this.enviando.set(false);
      },
    });
  }
}
