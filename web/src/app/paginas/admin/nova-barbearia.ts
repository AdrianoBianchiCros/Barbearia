import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule, NgForm } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AdminService, BarbeariaAdmin, ConsultaCnpj, DadosAcesso, OpcoesAdmin } from '../../core/admin.service';
import { errosPorCampo, mensagemDeErro } from '../../core/erros';
import { anexarContratacao, CamposContratacao, contratacaoVazia } from './campos-contratacao';
import { CartaoAcesso } from './cartao-acesso';

/** Campos do cadastro; os da empresa são preenchidos pela consulta do CNPJ e podem ser corrigidos. */
function formularioVazio() {
  return {
    documento: '', nome: '', razao_social: '', telefone: '', email: '',
    situacao_cadastral: '', data_abertura: '', natureza_juridica: '', cnae_principal: '', porte: '',
    optante_simples: null as boolean | null, optante_mei: null as boolean | null,
    cep: '', logradouro: '', numero: '', complemento: '', bairro: '', cidade: '', uf: '',
    dono_nome: '', dono_email: '',
  };
}

const UFS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

/** Cadastro de uma barbearia cliente: dados, dono, período pago e contrato. */
@Component({
  selector: 'app-nova-barbearia',
  imports: [FormsModule, RouterLink, CamposContratacao, CartaoAcesso],
  template: `
    <a class="voltar botao-link" routerLink="/admin/barbearias">← Barbearias</a>

    @if (criada(); as c) {
      <div class="cabecalho-pagina">
        <div>
          <h1>{{ c.barbearia.nome }} cadastrada</h1>
          <p class="texto-suave">Acesso liberado até {{ formatarData(c.barbearia.acesso.vence_em) }}.</p>
        </div>
        <a class="botao botao-auto" [routerLink]="['/admin/barbearias', c.barbearia.id]">Ver barbearia</a>
      </div>
      <app-cartao-acesso [acesso]="c.acesso" />
      <button class="botao-link" type="button" (click)="novaCadastro()">Cadastrar outra barbearia</button>
    } @else {
      <div class="cabecalho-pagina">
        <div>
          <h1>Nova barbearia</h1>
          <p class="texto-suave">Cria a barbearia, o login do dono e libera o acesso pelo período contratado.</p>
        </div>
      </div>

      <form #f="ngForm" (ngSubmit)="salvar(f)" novalidate>
        @if (erro()) {<div class="alerta" role="alert">{{ erro() }}</div>}

        <section class="cartao-secao">
          <h2>Barbearia</h2>
          <p class="texto-suave">Informe o CNPJ: os dados da Receita Federal preenchem o cadastro (dá para corrigir depois).</p>
          <div class="linha-cnpj">
            <label class="campo">
              <span>CNPJ <em>(ou CPF, sem consulta)</em></span>
              <input name="documento" [(ngModel)]="form.documento" maxlength="20" inputmode="numeric" placeholder="00.000.000/0000-00"
                (blur)="consultarSeCnpj()" (keydown.enter)="$event.preventDefault(); consultarCnpj()" />
              @if (erros()['documento']) {<small class="erro-campo">{{ erros()['documento'] }}</small>}
            </label>
            <button class="botao botao-secundario botao-auto" type="button" [disabled]="consultando()" (click)="consultarCnpj()">
              {{ consultando() ? 'Consultando…' : 'Buscar dados do CNPJ' }}
            </button>
          </div>
          @if (consulta(); as c) {
            <div class="aviso-cnpj" [class.atencao]="c.situacao_cadastral !== 'Ativa' || c.ja_cadastrada" role="status">
              Situação na Receita: <strong>{{ c.situacao_cadastral || '—' }}</strong>
              @if (c.situacao_cadastral && c.situacao_cadastral !== 'Ativa') { · confira antes de cadastrar}
              @if (c.ja_cadastrada) {<br />Este CNPJ já está na barbearia <strong>{{ c.ja_cadastrada }}</strong>.}
            </div>
          }

          <div class="grade-2">
            <label class="campo">
              <span>Nome da barbearia (fantasia)</span>
              <input name="nome" [(ngModel)]="form.nome" required maxlength="120" />
              @if (erros()['nome']) {<small class="erro-campo">{{ erros()['nome'] }}</small>}
            </label>
            <label class="campo">
              <span>Razão social</span>
              <input name="razao_social" [(ngModel)]="form.razao_social" maxlength="160" />
            </label>
          </div>
          <div class="grade-2">
            <label class="campo">
              <span>WhatsApp / telefone <em>(opcional)</em></span>
              <input type="tel" name="telefone" [(ngModel)]="form.telefone" placeholder="(11) 99999-0000" maxlength="20" />
            </label>
            <label class="campo">
              <span>E-mail da barbearia <em>(se vazio, usa o do dono)</em></span>
              <input type="email" name="email" [(ngModel)]="form.email" maxlength="180" />
              @if (erros()['email']) {<small class="erro-campo">{{ erros()['email'] }}</small>}
            </label>
          </div>
        </section>

        <section class="cartao-secao">
          <h2>Dados da empresa</h2>
          <div class="grade-2">
            <label class="campo"><span>Situação cadastral</span><input name="situacao_cadastral" [(ngModel)]="form.situacao_cadastral" maxlength="30" /></label>
            <label class="campo"><span>Data de abertura</span><input type="date" name="data_abertura" [(ngModel)]="form.data_abertura" /></label>
          </div>
          <div class="grade-2">
            <label class="campo"><span>Natureza jurídica</span><input name="natureza_juridica" [(ngModel)]="form.natureza_juridica" maxlength="120" /></label>
            <label class="campo"><span>Porte</span><input name="porte" [(ngModel)]="form.porte" maxlength="40" /></label>
          </div>
          <label class="campo"><span>Atividade principal (CNAE)</span><input name="cnae_principal" [(ngModel)]="form.cnae_principal" maxlength="200" /></label>
          <div class="grade-2">
            <label class="campo-check"><input type="checkbox" name="optante_simples" [(ngModel)]="form.optante_simples" /> Optante pelo Simples Nacional</label>
            <label class="campo-check"><input type="checkbox" name="optante_mei" [(ngModel)]="form.optante_mei" /> MEI</label>
          </div>
        </section>

        <section class="cartao-secao">
          <h2>Endereço</h2>
          <div class="grade-2">
            <label class="campo"><span>CEP</span><input name="cep" [(ngModel)]="form.cep" maxlength="9" inputmode="numeric" placeholder="00000-000" /></label>
            <label class="campo"><span>Bairro</span><input name="bairro" [(ngModel)]="form.bairro" maxlength="80" /></label>
          </div>
          <label class="campo"><span>Logradouro</span><input name="logradouro" [(ngModel)]="form.logradouro" maxlength="255" /></label>
          <div class="grade-2">
            <label class="campo"><span>Número</span><input name="numero" [(ngModel)]="form.numero" maxlength="20" /></label>
            <label class="campo"><span>Complemento</span><input name="complemento" [(ngModel)]="form.complemento" maxlength="80" /></label>
          </div>
          <div class="grade-2">
            <label class="campo"><span>Cidade</span><input name="cidade" [(ngModel)]="form.cidade" maxlength="80" /></label>
            <label class="campo">
              <span>UF</span>
              <select name="uf" [(ngModel)]="form.uf">
                <option value="">—</option>
                @for (uf of ufs; track uf) {<option [value]="uf">{{ uf }}</option>}
              </select>
            </label>
          </div>
        </section>

        <section class="cartao-secao">
          <h2>Dono</h2>
          <p class="texto-suave">O sistema gera uma senha provisória para o primeiro acesso.</p>
          <div class="grade-2">
            <label class="campo">
              <span>Nome</span>
              <input name="dono_nome" [(ngModel)]="form.dono_nome" required maxlength="120" />
              @if (erros()['dono_nome']) {<small class="erro-campo">{{ erros()['dono_nome'] }}</small>}
            </label>
            <label class="campo">
              <span>E-mail (login)</span>
              <input type="email" name="dono_email" [(ngModel)]="form.dono_email" required maxlength="180" />
              @if (erros()['dono_email']) {<small class="erro-campo">{{ erros()['dono_email'] }}</small>}
            </label>
          </div>
        </section>

        <section class="cartao-secao">
          <h2>Plano e contrato</h2>
          <app-campos-contratacao [dados]="contratacao" [opcoes]="opcoes()" [erros]="erros()" />
          <p class="texto-suave pequeno">
            Sem arquivo anexado, o sistema gera o contrato de prestação de serviço com os dados acima e guarda junto
            com a contratação. Confira antes em "Ver contrato".
          </p>
          <div class="rodape-secao">
            <a class="botao botao-secundario botao-auto" routerLink="/admin/barbearias">Cancelar</a>
            <button class="botao botao-secundario botao-auto" type="button" [disabled]="gerandoContrato()" (click)="verContrato(f)">
              {{ gerandoContrato() ? 'Gerando…' : 'Ver contrato (PDF)' }}
            </button>
            <button class="botao botao-auto" type="submit" [disabled]="salvando()">
              {{ salvando() ? 'Cadastrando…' : 'Cadastrar e liberar acesso' }}
            </button>
          </div>
        </section>
      </form>
    }
  `,
  styles: `
    .linha-cnpj { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; }
    .linha-cnpj .campo { flex: 1; min-width: 220px; }
    .linha-cnpj .botao { margin-bottom: 16px; }
    .aviso-cnpj {
      margin: 0 0 16px; padding: 10px 14px; border-radius: var(--radius-inputs, 8px); font-size: 14px;
      background: var(--cor-sucesso-fundo); color: var(--cor-texto);
    }
    .aviso-cnpj.atencao { background: var(--cor-erro-fundo); }
  `,
})
export class NovaBarbearia implements OnInit {
  private readonly api = inject(AdminService);

  protected readonly opcoes = signal<OpcoesAdmin | null>(null);
  protected readonly salvando = signal(false);
  protected readonly erro = signal('');
  protected readonly erros = signal<Record<string, string>>({});
  protected readonly criada = signal<{ barbearia: BarbeariaAdmin; acesso: DadosAcesso } | null>(null);
  protected readonly consultando = signal(false);
  protected readonly gerandoContrato = signal(false);
  protected readonly consulta = signal<ConsultaCnpj | null>(null);
  protected readonly ufs = UFS;
  private ultimoConsultado = '';

  protected form = formularioVazio();
  protected contratacao = contratacaoVazia();

  ngOnInit(): void {
    this.api.opcoes().subscribe((o) => this.opcoes.set(o));
  }

  salvar(f: NgForm): void {
    if (f.invalid) {
      f.control.markAllAsTouched();
      this.erro.set('Preencha o nome da barbearia, o nome e o e-mail do dono e o valor pago.');
      return;
    }
    this.salvando.set(true);
    this.erro.set('');
    this.erros.set({});

    this.api.criar(this.montarDados()).subscribe({
      next: (r) => {
        this.criada.set(r);
        this.salvando.set(false);
      },
      error: (e) => {
        this.erros.set(errosPorCampo(e));
        this.erro.set(mensagemDeErro(e));
        this.salvando.set(false);
      },
    });
  }

  private montarDados(): FormData {
    const dados = new FormData();
    Object.entries(this.form).forEach(([campo, valor]) => {
      if (typeof valor === 'boolean') dados.append(campo, valor ? '1' : '0');
      else if (valor) dados.append(campo, valor);
    });
    anexarContratacao(dados, this.contratacao);
    return dados;
  }

  /** Baixa o contrato gerado com os dados preenchidos, sem cadastrar nada. */
  verContrato(f: NgForm): void {
    if (f.invalid) {
      f.control.markAllAsTouched();
      this.erro.set('Para gerar o contrato, preencha o nome da barbearia, o nome e o e-mail do dono e o valor pago.');
      return;
    }
    this.erro.set('');
    this.gerandoContrato.set(true);
    const dados = this.montarDados();
    dados.delete('contrato');
    this.api.previaContrato(dados).subscribe({
      next: (pdf) => {
        this.gerandoContrato.set(false);
        const url = URL.createObjectURL(pdf);
        const link = document.createElement('a');
        link.href = url;
        link.download = `Contrato - ${this.form.nome}.pdf`;
        link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
      },
      error: async (e) => {
        this.gerandoContrato.set(false);
        // Com responseType "blob", o erro de validação também chega como arquivo.
        const corpo = e?.error instanceof Blob ? JSON.parse((await e.error.text()) || '{}') : e?.error;
        const erro = { ...e, error: corpo };
        this.erros.set(errosPorCampo(erro));
        this.erro.set(mensagemDeErro(erro));
      },
    });
  }

  /** Ao sair do campo: consulta sozinho quando há 14 números (CNPJ) ainda não consultados. */
  consultarSeCnpj(): void {
    const cnpj = this.form.documento.replace(/\D/g, '');
    if (cnpj.length === 14 && cnpj !== this.ultimoConsultado) this.consultarCnpj();
  }

  /** Busca os dados na Receita Federal e preenche o formulário (o que vier vazio não apaga o digitado). */
  consultarCnpj(): void {
    const cnpj = this.form.documento.replace(/\D/g, '');
    if (cnpj.length !== 14) {
      this.erros.set({ ...this.erros(), documento: 'Informe os 14 números do CNPJ para buscar os dados.' });
      return;
    }
    this.consultando.set(true);
    this.erros.set({ ...this.erros(), documento: '' });
    this.ultimoConsultado = cnpj;
    this.api.consultarCnpj(cnpj).subscribe({
      next: (c) => {
        this.consultando.set(false);
        this.consulta.set(c);
        const { ja_cadastrada: _ja, ...campos } = c;
        for (const [campo, valor] of Object.entries(campos)) {
          if (valor === null || valor === '' || !(campo in this.form)) continue;
          if (campo === 'dono_nome' && this.form.dono_nome.trim()) continue; // sugestão do sócio não apaga o digitado
          (this.form as Record<string, unknown>)[campo] = valor;
        }
        this.form.documento = this.formatarCnpj(c.documento);
        if (this.form.cep.length === 8) this.form.cep = `${this.form.cep.slice(0, 5)}-${this.form.cep.slice(5)}`;
      },
      error: (e) => {
        this.consultando.set(false);
        this.consulta.set(null);
        this.erros.set({ ...this.erros(), documento: errosPorCampo(e)['documento'] || mensagemDeErro(e) });
      },
    });
  }

  private formatarCnpj(d: string): string {
    return d.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
  }

  novaCadastro(): void {
    this.form = formularioVazio();
    this.consulta.set(null);
    this.ultimoConsultado = '';
    this.contratacao = contratacaoVazia();
    this.criada.set(null);
  }

  formatarData(data: string | null): string {
    return data ? data.split('-').reverse().join('/') : '—';
  }
}
