import { DatePipe } from '@angular/common';
import { Component, inject, OnInit, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { AvisoService } from '../../core/aviso.service';
import { errosPorCampo, mensagemDeErro } from '../../core/erros';
import { ConfigWhatsapp, MensagemWhatsapp, PaginaMensagens, StatusMensagem, WhatsappService } from '../../core/whatsapp.service';

const TIPOS: Record<MensagemWhatsapp['tipo'], string> = {
  confirmacao: 'Confirmação',
  remarcacao: 'Remarcação',
  lembrete_24h: 'Lembrete 24 h',
  lembrete_2h: 'Lembrete 2 h',
};

const STATUS: Record<StatusMensagem, string> = {
  pendente: 'Na fila',
  enviado: 'Enviada',
  entregue: 'Entregue',
  lido: 'Lida',
  falhou: 'Falhou',
  ignorado: 'Não enviada',
};

/** Modelos de mensagem para cadastrar na Meta (WhatsApp Manager > Modelos de mensagem). */
const MODELOS = [
  {
    nome: 'confirmacao_agendamento',
    texto:
      'Olá, {{1}}! Seu horário na {{2}} está marcado para {{3}} às {{4}}, com {{5}} ({{6}}).\n\n' +
      'Para confirmar, remarcar ou cancelar, toque no botão abaixo.',
    exemplo: 'João · Barbearia do Zé · terça, 20/10 · 14:00 · Carlos · Corte + Barba',
  },
  {
    nome: 'lembrete_agendamento',
    texto:
      'Oi, {{1}}! Passando para lembrar do seu horário na {{2}} {{3}} às {{4}}, com {{5}}. Te esperamos!\n\n' +
      'Se não puder vir, remarque ou cancele pelo botão abaixo.',
    exemplo: 'João · Barbearia do Zé · amanhã · 14:00 · Carlos',
  },
];

/** Mensagens automáticas pelo WhatsApp: ligar, conectar com a Meta e acompanhar os envios (somente dono). */
@Component({
  selector: 'app-config-whatsapp',
  imports: [ReactiveFormsModule, DatePipe],
  styles: `
    .faixa-modo { display: flex; gap: 12px; align-items: flex-start; padding: 14px 16px; border-radius: var(--raio); margin-bottom: 18px; border: 1px solid var(--cor-borda); background: var(--cor-superficie); }
    .faixa-modo.teste { border-color: var(--st-em_atendimento-bd); background: var(--st-em_atendimento-bg); }
    .faixa-modo strong { display: block; margin-bottom: 2px; }
    .numeros-mes { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px; margin-bottom: 16px; }
    .numeros-mes div { padding: 10px 12px; border: 1px solid var(--cor-borda); border-radius: 10px; }
    .numeros-mes strong { display: block; font-size: 1.4rem; }
    .numeros-mes small { color: var(--cor-texto-suave); }
    .copiar { display: flex; gap: 8px; align-items: center; }
    .copiar code { flex: 1; padding: 10px 12px; border: 1px solid var(--cor-borda); border-radius: 8px; background: var(--cor-fundo); overflow-x: auto; white-space: nowrap; font-size: 0.85rem; }
    .modelo { border: 1px solid var(--cor-borda); border-radius: 10px; padding: 14px; margin-bottom: 12px; }
    .modelo pre { white-space: pre-wrap; font: inherit; margin: 8px 0; padding: 10px 12px; background: var(--cor-fundo); border-radius: 8px; }
    .modelo .cabeca { display: flex; justify-content: space-between; gap: 8px; align-items: center; flex-wrap: wrap; }
    .passos-meta { margin: 0 0 12px; padding-left: 20px; }
    .passos-meta li { margin-bottom: 6px; }
    .filtros { display: flex; gap: 10px; align-items: center; margin-bottom: 12px; flex-wrap: wrap; }
    .filtros select { padding: 8px 10px; font: inherit; color: inherit; background: var(--cor-superficie); border: 1px solid var(--cor-borda); border-radius: 8px; }
    .paginacao { display: flex; gap: 10px; justify-content: flex-end; align-items: center; margin-top: 10px; }
    td small { display: block; color: var(--cor-texto-suave); }
    .erro-msg { color: var(--cor-erro); }
  `,
  template: `
    <div class="cabecalho-pagina">
      <div>
        <h1>WhatsApp</h1>
        <p class="texto-suave">Confirmação logo após o agendamento e lembretes antes do horário, com link para o cliente confirmar, remarcar ou cancelar.</p>
      </div>
    </div>

    @if (config(); as c) {
      @if (c.modo === 'teste') {
        <div class="faixa-modo teste">
          <div>
            <strong>Modo de teste</strong>
            As mensagens são registradas no histórico abaixo, mas ainda não saem para o cliente. Quando a conta da Meta estiver aprovada,
            preencha a conexão abaixo e ative o envio real no servidor (WHATSAPP_DRIVER=cloud).
          </div>
        </div>
      } @else {
        <div class="faixa-modo">
          <div><strong>Envio real ativo</strong> As mensagens saem pelo número da barbearia na WhatsApp Cloud API.</div>
        </div>
      }

      <form [formGroup]="form" (ngSubmit)="salvar()" novalidate>
        @if (erro()) {<div class="alerta">{{ erro() }}</div>}

        <section class="cartao-secao">
          <h2>Mensagens automáticas</h2>
          <label class="campo-check"><input type="checkbox" formControlName="wa_ativo" /> Enviar mensagens pelo WhatsApp</label>
          <label class="campo-check"><input type="checkbox" formControlName="wa_lembrete_24h" /> Lembrete 24 h antes do horário</label>
          <label class="campo-check"><input type="checkbox" formControlName="wa_lembrete_2h" /> Lembrete 2 h antes do horário</label>
          <p class="texto-suave pequeno">A confirmação sai sempre que um horário é marcado ou remarcado. Só recebe quem autorizou mensagens no WhatsApp; quem responder PARAR deixa de receber.</p>
          <label class="campo campo-curto">
            <span>Cliente pode remarcar/cancelar pelo link até</span>
            <div class="campo-prefixo"><input type="number" min="0" max="72" formControlName="cancelamento_antecedencia_horas" /><span>horas antes</span></div>
            @if (erroCampo('cancelamento_antecedencia_horas')) {<small class="erro-campo">{{ erroCampo('cancelamento_antecedencia_horas') }}</small>}
          </label>
        </section>

        <section class="cartao-secao">
          <h2>Conexão com a Meta</h2>
          <p class="texto-suave pequeno">No painel da Meta: WhatsApp &gt; Configuração da API. Use um token permanente (usuário do sistema), não o temporário de 24 h.</p>
          <div class="grade-2">
            <label class="campo">
              <span>ID do número de telefone</span>
              <input formControlName="wa_phone_number_id" inputmode="numeric" placeholder="Ex.: 109876543210987" />
              @if (erroCampo('wa_phone_number_id')) {<small class="erro-campo">{{ erroCampo('wa_phone_number_id') }}</small>}
            </label>
            <label class="campo">
              <span>Token de acesso</span>
              <input type="password" formControlName="wa_token" autocomplete="off"
                [placeholder]="c.wa_token_configurado ? '•••••••• (configurado — deixe em branco para manter)' : 'Cole o token aqui'" />
              @if (erroCampo('wa_token')) {<small class="erro-campo">{{ erroCampo('wa_token') }}</small>}
            </label>
          </div>
          <span class="rotulo">Endereço do webhook (cadastre na Meta, campo "messages")</span>
          <div class="copiar">
            <code>{{ c.webhook_url }}</code>
            <button class="botao botao-secundario botao-auto" type="button" (click)="copiar(c.webhook_url)">Copiar</button>
          </div>
        </section>

        <div class="rodape-secao">
          <button class="botao botao-auto" type="submit" [disabled]="salvando()">{{ salvando() ? 'Salvando…' : 'Salvar' }}</button>
        </div>
      </form>

      <section class="cartao-secao">
        <h2>Modelos para aprovar na Meta</h2>
        <ol class="passos-meta texto-suave">
          <li>No WhatsApp Manager, abra <strong>Modelos de mensagem</strong> e crie cada modelo abaixo com categoria <strong>Utilidade</strong> e idioma <strong>Português (BR)</strong>.</li>
          <li>Cole o texto do corpo e preencha os exemplos das variáveis.</li>
          <li>Adicione um botão <strong>Visitar site</strong> com texto "Gerenciar horário", URL <strong>dinâmica</strong>: <code>{{ urlBotao(c) }}</code> (exemplo do final: <code>abc123</code>).</li>
        </ol>
        @for (m of modelos; track m.nome) {
          <div class="modelo">
            <div class="cabeca">
              <strong>{{ m.nome }}</strong>
              <button class="botao-link" type="button" (click)="copiar(m.texto)">Copiar texto</button>
            </div>
            <pre>{{ m.texto }}</pre>
            <small class="texto-suave">Exemplo das variáveis: {{ m.exemplo }}</small>
          </div>
        }
      </section>

      <section class="cartao-secao">
        <h2>Mensagens deste mês</h2>
        <div class="numeros-mes">
          @for (s of statusLista; track s) {
            <div><strong>{{ c.mes[s] ?? 0 }}</strong><small>{{ rotuloStatus(s) }}</small></div>
          }
        </div>

        <div class="filtros">
          <label>Mostrar
            <select [value]="filtro()" (change)="filtrar($event)">
              <option value="">Todas</option>
              @for (s of statusLista; track s) {<option [value]="s">{{ rotuloStatus(s) }}</option>}
            </select>
          </label>
        </div>

        @if (pagina(); as p) {
          @if (p.data.length) {
            <div class="tabela-container">
              <table class="tabela">
                <thead><tr><th>Quando</th><th>Cliente</th><th>Mensagem</th><th>Situação</th></tr></thead>
                <tbody>
                  @for (m of p.data; track m.id) {
                    <tr>
                      <td>{{ m.criada_em | date: 'dd/MM HH:mm' }}</td>
                      <td>{{ m.cliente ?? '—' }}<small>{{ m.telefone }}</small></td>
                      <td>{{ rotuloTipo(m.tipo) }}@if (m.horario) {<small>horário {{ m.horario | date: 'dd/MM HH:mm' }}</small>}</td>
                      <td>
                        <span class="etiqueta" [class.etiqueta-ok]="m.status === 'entregue' || m.status === 'lido'" [class.etiqueta-erro]="m.status === 'falhou'">{{ rotuloStatus(m.status) }}</span>
                        @if (m.erro) {<small class="erro-msg">{{ m.erro }}</small>}
                        @if (m.resposta) {<small>Respondeu: “{{ m.resposta }}”</small>}
                      </td>
                    </tr>
                  }
                </tbody>
              </table>
            </div>
            @if (p.ultima_pagina > 1) {
              <div class="paginacao">
                <button class="botao-link" type="button" [disabled]="p.pagina <= 1" (click)="carregar(p.pagina - 1)">← Anteriores</button>
                <span class="texto-suave pequeno">Página {{ p.pagina }} de {{ p.ultima_pagina }}</span>
                <button class="botao-link" type="button" [disabled]="p.pagina >= p.ultima_pagina" (click)="carregar(p.pagina + 1)">Próximas →</button>
              </div>
            }
          } @else {
            <p class="texto-suave">Nenhuma mensagem ainda. Elas aparecem aqui assim que um cliente que autorizou o WhatsApp marcar um horário.</p>
          }
        }
      </section>
    } @else {
      <p class="texto-suave">Carregando…</p>
    }
  `,
})
export class ConfigWhatsappPagina implements OnInit {
  private readonly api = inject(WhatsappService);
  private readonly aviso = inject(AvisoService);

  protected readonly modelos = MODELOS;
  protected readonly statusLista = Object.keys(STATUS) as StatusMensagem[];
  protected readonly config = signal<ConfigWhatsapp | null>(null);
  protected readonly pagina = signal<PaginaMensagens | null>(null);
  protected readonly filtro = signal('');
  protected readonly salvando = signal(false);
  protected readonly erro = signal('');
  protected readonly errosApi = signal<Record<string, string>>({});

  protected readonly form = inject(FormBuilder).nonNullable.group({
    wa_ativo: [false],
    wa_lembrete_24h: [true],
    wa_lembrete_2h: [false],
    cancelamento_antecedencia_horas: [2, [Validators.min(0), Validators.max(72)]],
    wa_phone_number_id: [''],
    wa_token: [''],
  });

  ngOnInit(): void {
    this.api.buscar().subscribe({
      next: (c) => this.aplicar(c),
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
    this.carregar(1);
  }

  private aplicar(c: ConfigWhatsapp): void {
    this.config.set(c);
    this.form.reset({
      wa_ativo: c.wa_ativo,
      wa_lembrete_24h: c.wa_lembrete_24h,
      wa_lembrete_2h: c.wa_lembrete_2h,
      cancelamento_antecedencia_horas: c.cancelamento_antecedencia_horas,
      wa_phone_number_id: c.wa_phone_number_id ?? '',
      wa_token: '',
    });
  }

  carregar(pagina: number): void {
    this.api.mensagens(pagina, this.filtro()).subscribe({
      next: (p) => this.pagina.set(p),
      error: (e) => this.aviso.erro(mensagemDeErro(e)),
    });
  }

  filtrar(evento: Event): void {
    this.filtro.set((evento.target as HTMLSelectElement).value);
    this.carregar(1);
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
    this.api.salvar({ ...v, wa_phone_number_id: v.wa_phone_number_id.trim() || null, wa_token: v.wa_token.trim() || null }).subscribe({
      next: (c) => {
        this.salvando.set(false);
        this.aplicar(c);
        this.aviso.sucesso('Configuração do WhatsApp salva.');
      },
      error: (e) => {
        this.salvando.set(false);
        this.errosApi.set(errosPorCampo(e));
        this.erro.set(mensagemDeErro(e));
      },
    });
  }

  erroCampo(campo: string): string {
    if (this.errosApi()[campo]) return this.errosApi()[campo];
    const c = this.form.get(campo);
    return c?.touched && c.invalid ? 'Preencha este campo corretamente.' : '';
  }

  copiar(texto: string): void {
    navigator.clipboard.writeText(texto).then(
      () => this.aviso.sucesso('Copiado.'),
      () => this.aviso.erro('Não foi possível copiar. Selecione o texto e copie manualmente.'),
    );
  }

  /** URL do botão do modelo: a parte {{1}} é preenchida com o código de cada agendamento. */
  urlBotao(c: ConfigWhatsapp): string {
    return c.webhook_url.replace(/\/api\/webhooks\/whatsapp$/, '/a/{{1}}');
  }

  rotuloTipo(t: MensagemWhatsapp['tipo']): string {
    return TIPOS[t] ?? t;
  }

  rotuloStatus(s: StatusMensagem): string {
    return STATUS[s] ?? s;
  }
}
