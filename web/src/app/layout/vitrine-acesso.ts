import { Component } from '@angular/core';
import { PLATAFORMA } from '../core/plataforma';

const DIAS = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

/**
 * Tela de entrada do painel das barbearias (login e telas de senha), no visual da página pública:
 * escuro, dourado, foto da barbearia, poste de barbeiro girando e uma prévia animada da agenda.
 * Uso: <app-vitrine-acesso><form class="cartao">…</form></app-vitrine-acesso>
 * Estilos em src/acesso.scss.
 */
@Component({
  selector: 'app-vitrine-acesso',
  template: `
    <main class="acesso-barbearia">
      <section class="acesso-vitrine" aria-hidden="true">
        <img class="acesso-foto" src="img/acesso/salao.jpg" alt="" />
        <div class="acesso-veu"></div>

        <div class="acesso-vitrine-conteudo">
          <p class="acesso-selo"><span class="acesso-tesoura">✂</span> Sistema para barbearias</p>
          <h2 class="acesso-titulo">
            <span>Sua cadeira</span>
            <span>sempre</span>
            <em>ocupada.</em>
          </h2>
          <p class="acesso-lead">Agenda online, lembretes no WhatsApp, caixa e comissões da sua barbearia num lugar só.</p>

          <div class="acesso-agenda">
            <div class="acesso-agenda-topo">
              <span>Hoje · {{ hoje }}</span>
              <span class="acesso-ao-vivo">ao vivo</span>
            </div>
            @for (h of horarios; track h.hora; let i = $index) {
              <div class="acesso-horario" [style.--i]="i">
                <strong>{{ h.hora }}</strong>
                <span class="acesso-cliente">{{ h.cliente }}<small>{{ h.servico }}</small></span>
                <span class="acesso-status" [class]="'acesso-status-' + h.tipo">{{ h.status }}</span>
              </div>
            }
            <div class="acesso-agenda-rodape">
              <span>Ocupação do dia</span>
              <span class="acesso-barra"><span></span></span>
              <strong>92%</strong>
            </div>
          </div>
        </div>

        <div class="acesso-letreiro">
          <div>
            @for (rodada of [1, 2]; track rodada) {
              @for (t of letreiro; track t) {
                <span>{{ t }}</span><span class="acesso-estrela">✦</span>
              }
            }
          </div>
        </div>
      </section>

      <div class="acesso-poste" aria-hidden="true"><span></span></div>

      <section class="acesso-lado-form">
        <div class="acesso-form-interno">
          <div class="acesso-emblema" aria-hidden="true">
            <svg viewBox="0 0 48 48">
              <circle cx="15" cy="34" r="6" />
              <circle cx="33" cy="34" r="6" />
              <path d="M19.5 30L34 8M28.5 30L14 8" />
            </svg>
          </div>
          <ng-content />
          <p class="acesso-assinatura">Feito para quem vive de tesoura, navalha e hora marcada · {{ plataforma.nome }}</p>
        </div>
      </section>
    </main>
  `,
})
export class VitrineAcesso {
  protected readonly plataforma = PLATAFORMA;

  protected readonly hoje = (() => {
    const d = new Date();
    return `${DIAS[d.getDay()]}, ${d.getDate()} ${MESES[d.getMonth()]}`;
  })();

  /** Prévia ilustrativa da agenda (nomes fictícios). */
  protected readonly horarios = [
    { hora: '09:00', cliente: 'Rafael', servico: 'Corte + barba', status: 'Confirmado', tipo: 'ok' },
    { hora: '10:30', cliente: 'Bruno', servico: 'Degradê', status: 'Pela página', tipo: 'online' },
    { hora: '11:15', cliente: 'Diego', servico: 'Barboterapia', status: 'Lembrete enviado', tipo: 'zap' },
    { hora: '14:00', cliente: 'Lucas', servico: 'Corte infantil', status: 'Em atendimento', tipo: 'agora' },
  ];

  protected readonly letreiro = ['Agenda online 24h', 'Lembretes no WhatsApp', 'Comanda e caixa', 'Comissões', 'Relatórios'];
}
