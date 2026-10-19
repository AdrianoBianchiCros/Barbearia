import { Component, inject, OnInit, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { AvisoService } from '../../core/aviso.service';
import { CadastrosService } from '../../core/cadastros.service';
import { errosPorCampo, mensagemDeErro } from '../../core/erros';
import { EmpresaCompleta, FotoGaleria } from '../../core/modelos';
import { environment } from '../../../environments/environment';

/** A página pública fica no mesmo domínio da API, sem o /api. */
const ENDERECO_SITE = environment.apiUrl.replace(/\/api\/?$/, '');

/** Dados da barbearia e conteúdo da página pública (somente dono). */
@Component({
  selector: 'app-dados-barbearia',
  imports: [ReactiveFormsModule],
  styles: `
    .cabeca-galeria { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
    .cabeca-galeria h2 { margin-bottom: 4px; }
    .desativado { opacity: 0.6; pointer-events: none; }
    .grade-galeria { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 14px; margin-top: 16px; }
    .grade-galeria figure { margin: 0; display: flex; flex-direction: column; gap: 6px; }
    .grade-galeria img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 10px; }
    .grade-galeria input { width: 100%; padding: 6px 8px; font: inherit; font-size: 0.85rem; border: 1px solid var(--borda, #ddd); border-radius: 8px; }
    .grade-galeria button { align-self: flex-start; }
  `,
  template: `
    <div class="cabecalho-pagina">
      <div>
        <h1>Barbearia e página</h1>
        <p class="texto-suave">Essas informações aparecem na página pública da barbearia, onde os clientes agendam.</p>
      </div>
      @if (empresa(); as emp) {
        <a class="botao botao-secundario botao-auto" [href]="enderecoSite + '/' + emp.slug" target="_blank" rel="noopener">Ver minha página</a>
      }
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

      <section class="cartao-secao">
        <div class="cabeca-galeria">
          <div>
            <h2>Galeria de fotos</h2>
            <p class="texto-suave pequeno">
              Fotos de cortes e do espaço (até {{ maxFotos }}).
              @if (galeria().length === 0) { Enquanto você não enviar fotos, a página usa fotos ilustrativas. }
            </p>
          </div>
          @if (galeria().length < maxFotos) {
            <label class="botao botao-secundario botao-auto" [class.desativado]="enviandoFotos()">
              {{ enviandoFotos() ? 'Enviando…' : 'Adicionar fotos' }}
              <input type="file" accept="image/png,image/jpeg,image/webp" multiple hidden (change)="enviarFotos($event)" [disabled]="enviandoFotos()" />
            </label>
          }
        </div>
        @if (galeria().length) {
          <div class="grade-galeria">
            @for (foto of galeria(); track foto.id) {
              <figure>
                <img [src]="foto.url" alt="" />
                <input placeholder="Legenda (opcional)" maxlength="120" [value]="foto.legenda ?? ''" (change)="salvarLegenda(foto, $event)" />
                <button class="botao-link perigo" type="button" (click)="removerFoto(foto)">Remover</button>
              </figure>
            }
          </div>
        }
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
              <div class="campo-prefixo"><span>{{ dominio }}/</span><input formControlName="slug" /></div>
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
  protected readonly enderecoSite = ENDERECO_SITE;
  protected readonly dominio = ENDERECO_SITE.replace(/^https?:\/\//, '');
  protected readonly empresa = signal<EmpresaCompleta | null>(null);
  protected readonly galeria = signal<FotoGaleria[]>([]);
  protected readonly enviandoFotos = signal(false);
  protected readonly maxFotos = 12;
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
    this.api.listarGaleria().subscribe({ next: (fotos) => this.galeria.set(fotos) });
  }

  /** Envia as fotos escolhidas uma a uma, até o limite da galeria. */
  async enviarFotos(evento: Event): Promise<void> {
    const input = evento.target as HTMLInputElement;
    const arquivos = Array.from(input.files ?? []).slice(0, this.maxFotos - this.galeria().length);
    input.value = '';
    if (!arquivos.length) return;
    this.enviandoFotos.set(true);
    let enviadas = 0;
    for (const arquivo of arquivos) {
      try {
        const foto = await firstValueFrom(this.api.enviarFotoGaleria(arquivo));
        this.galeria.update((g) => [...g, foto]);
        enviadas++;
      } catch (e) {
        this.aviso.erro(`${arquivo.name}: ${mensagemDeErro(e)}`);
      }
    }
    this.enviandoFotos.set(false);
    if (enviadas) this.aviso.sucesso(enviadas === 1 ? 'Foto adicionada à galeria.' : `${enviadas} fotos adicionadas à galeria.`);
  }

  salvarLegenda(foto: FotoGaleria, evento: Event): void {
    const legenda = (evento.target as HTMLInputElement).value.trim() || null;
    this.api.salvarLegendaGaleria(foto.id, legenda).subscribe({
      next: (f) => this.galeria.update((g) => g.map((x) => (x.id === f.id ? f : x))),
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }

  removerFoto(foto: FotoGaleria): void {
    if (!confirm('Remover esta foto da galeria?')) return;
    this.api.removerFotoGaleria(foto.id).subscribe({
      next: () => this.galeria.update((g) => g.filter((x) => x.id !== foto.id)),
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
