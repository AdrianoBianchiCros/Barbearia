import { Component, inject, OnInit, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { AvisoService } from '../../core/aviso.service';
import { CadastrosService } from '../../core/cadastros.service';
import { errosPorCampo, mensagemDeErro } from '../../core/erros';
import { EmpresaCompleta } from '../../core/modelos';

/** Dados da barbearia e conteúdo da página pública (somente dono). */
@Component({
  selector: 'app-dados-barbearia',
  imports: [ReactiveFormsModule],
  template: `
    <div class="cabecalho-pagina">
      <div>
        <h1>Barbearia e página</h1>
        <p class="texto-suave">Essas informações aparecem na página pública da barbearia, onde os clientes agendam.</p>
      </div>
    </div>

    @if (empresa(); as emp) {
      <section class="cartao-secao">
        <h2>Imagens</h2>
        <div class="grade-imagens">
          @for (img of imagens; track img.tipo) {
            <div class="bloco-imagem">
              <span class="rotulo">{{ img.rotulo }}</span>
              <div class="previa-imagem" [class.previa-capa]="img.tipo === 'capa'">
                @if (img.tipo === 'logo' ? emp.logo_url : emp.capa_url; as url) {
                  <img [src]="url" [alt]="img.rotulo" />
                } @else {
                  <span class="texto-suave pequeno">{{ img.dica }}</span>
                }
              </div>
              <div class="acoes-imagem">
                <label class="botao botao-secundario">
                  Enviar
                  <input type="file" accept="image/png,image/jpeg,image/webp" hidden (change)="enviarImagem(img.tipo, $event)" />
                </label>
                @if (img.tipo === 'logo' ? emp.logo_url : emp.capa_url) {
                  <button class="botao-link perigo" type="button" (click)="removerImagem(img.tipo)">Remover</button>
                }
              </div>
            </div>
          }
        </div>
      </section>

      <form [formGroup]="form" (ngSubmit)="salvar()" novalidate>
        @if (erro()) {<div class="alerta">{{ erro() }}</div>}

        <section class="cartao-secao">
          <h2>Apresentação</h2>
          <div class="grade-2">
            <label class="campo">
              <span>Nome da barbearia</span>
              <input formControlName="nome" />
              @if (erroCampo('nome')) {<small class="erro-campo">{{ erroCampo('nome') }}</small>}
            </label>
            <label class="campo">
              <span>Endereço da página</span>
              <div class="campo-prefixo"><span>seudominio.com.br/</span><input formControlName="slug" /></div>
              @if (erroCampo('slug')) {<small class="erro-campo">{{ erroCampo('slug') }}</small>}
            </label>
          </div>
          <label class="campo">
            <span>Frase de destaque</span>
            <input formControlName="slogan" placeholder="Ex.: Tradição e estilo desde 2015" />
          </label>
          <label class="campo">
            <span>Sobre a barbearia</span>
            <textarea formControlName="descricao" rows="4" placeholder="Conte a história, o ambiente, os diferenciais…"></textarea>
          </label>
          <label class="campo campo-cor">
            <span>Cor principal da página</span>
            <input type="color" formControlName="cor_primaria" />
          </label>
        </section>

        <section class="cartao-secao">
          <h2>Contato e redes</h2>
          <div class="grade-2">
            <label class="campo">
              <span>WhatsApp</span>
              <input type="tel" formControlName="whatsapp" placeholder="(11) 99999-0000" />
              @if (erroCampo('whatsapp')) {<small class="erro-campo">{{ erroCampo('whatsapp') }}</small>}
            </label>
            <label class="campo">
              <span>Telefone fixo <em>(opcional)</em></span>
              <input type="tel" formControlName="telefone" />
              @if (erroCampo('telefone')) {<small class="erro-campo">{{ erroCampo('telefone') }}</small>}
            </label>
            <label class="campo">
              <span>Instagram</span>
              <div class="campo-prefixo"><span>&#64;</span><input formControlName="instagram" placeholder="suabarbearia" /></div>
              @if (erroCampo('instagram')) {<small class="erro-campo">{{ erroCampo('instagram') }}</small>}
            </label>
            <label class="campo">
              <span>E-mail</span>
              <input type="email" formControlName="email" />
              @if (erroCampo('email')) {<small class="erro-campo">{{ erroCampo('email') }}</small>}
            </label>
          </div>
        </section>

        <section class="cartao-secao">
          <h2>Endereço</h2>
          <div class="grade-endereco">
            <label class="campo"><span>CEP</span><input formControlName="cep" (blur)="buscarCep()" placeholder="00000-000" /></label>
            <label class="campo campo-largo"><span>Rua</span><input formControlName="logradouro" /></label>
            <label class="campo"><span>Número</span><input formControlName="numero" /></label>
            <label class="campo"><span>Complemento</span><input formControlName="complemento" /></label>
            <label class="campo"><span>Bairro</span><input formControlName="bairro" /></label>
            <label class="campo"><span>Cidade</span><input formControlName="cidade" /></label>
            <label class="campo"><span>UF</span><input formControlName="uf" maxlength="2" /></label>
          </div>
          <label class="campo">
            <span>CNPJ / CPF <em>(não aparece na página)</em></span>
            <input formControlName="documento" />
          </label>
        </section>

        <div class="rodape-secao">
          <button class="botao botao-auto" type="submit" [disabled]="salvando()">{{ salvando() ? 'Salvando…' : 'Salvar alterações' }}</button>
        </div>
      </form>
    } @else {
      <p class="texto-suave">Carregando…</p>
    }
  `,
})
export class DadosBarbearia implements OnInit {
  private readonly api = inject(CadastrosService);
  private readonly aviso = inject(AvisoService);

  protected readonly imagens = [
    { tipo: 'logo' as const, rotulo: 'Logo', dica: 'Quadrada, mínimo 300×300' },
    { tipo: 'capa' as const, rotulo: 'Foto de capa', dica: 'Horizontal, mínimo 1600×600' },
  ];
  protected readonly empresa = signal<EmpresaCompleta | null>(null);
  protected readonly salvando = signal(false);
  protected readonly erro = signal('');
  protected readonly errosApi = signal<Record<string, string>>({});

  protected readonly form = inject(FormBuilder).nonNullable.group({
    nome: ['', Validators.required],
    slug: ['', [Validators.required, Validators.pattern(/^[a-z0-9-_]+$/i)]],
    slogan: [''],
    descricao: [''],
    cor_primaria: ['#1f3a5f'],
    whatsapp: [''],
    telefone: [''],
    instagram: [''],
    email: ['', Validators.email],
    cep: [''],
    logradouro: [''],
    numero: [''],
    complemento: [''],
    bairro: [''],
    cidade: [''],
    uf: [''],
    documento: [''],
  });

  ngOnInit(): void {
    this.api.buscarEmpresa().subscribe({
      next: (e) => this.aplicar(e),
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }

  private aplicar(e: EmpresaCompleta): void {
    this.empresa.set(e);
    const valores = Object.fromEntries(
      Object.keys(this.form.controls).map((k) => [k, (e as unknown as Record<string, unknown>)[k] ?? '']),
    );
    this.form.reset(valores as typeof this.form.value);
  }

  erroCampo(campo: string): string {
    if (this.errosApi()[campo]) return this.errosApi()[campo];
    const c = this.form.get(campo);
    return c?.touched && c.invalid ? 'Preencha este campo corretamente.' : '';
  }

  /** Preenche o endereço pelo CEP usando o ViaCEP (serviço público). */
  buscarCep(): void {
    const cep = this.form.controls.cep.value.replace(/\D/g, '');
    if (cep.length !== 8) return;
    fetch(`https://viacep.com.br/ws/${cep}/json/`)
      .then((r) => r.json())
      .then((d) => {
        if (d.erro) return;
        this.form.patchValue({ logradouro: d.logradouro, bairro: d.bairro, cidade: d.localidade, uf: d.uf });
      })
      .catch(() => undefined);
  }

  salvar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.salvando.set(true);
    this.erro.set('');
    this.errosApi.set({});
    const v = this.form.getRawValue();
    const dados = Object.fromEntries(Object.entries(v).map(([k, val]) => [k, val === '' ? null : val]));
    this.api.salvarEmpresa(dados).subscribe({
      next: (e) => {
        this.salvando.set(false);
        this.aplicar(e);
        this.aviso.sucesso('Dados da barbearia salvos.');
      },
      error: (e) => {
        this.salvando.set(false);
        this.errosApi.set(errosPorCampo(e));
        this.erro.set(mensagemDeErro(e));
      },
    });
  }

  enviarImagem(tipo: 'logo' | 'capa', evento: Event): void {
    const input = evento.target as HTMLInputElement;
    const arquivo = input.files?.[0];
    if (!arquivo) return;
    this.api.enviarImagemEmpresa(tipo, arquivo).subscribe({
      next: (e) => {
        this.empresa.set(e);
        this.aviso.sucesso(tipo === 'logo' ? 'Logo atualizado.' : 'Capa atualizada.');
        input.value = '';
      },
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }

  removerImagem(tipo: 'logo' | 'capa'): void {
    this.api.removerImagemEmpresa(tipo).subscribe({
      next: (e) => this.empresa.set(e),
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }
}
