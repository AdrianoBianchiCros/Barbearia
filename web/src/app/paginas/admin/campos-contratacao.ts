import { Component, input } from '@angular/core';
import { ControlContainer, FormsModule, NgForm } from '@angular/forms';
import { OpcoesAdmin } from '../../core/admin.service';

/** Dados de uma contratação ou renovação (preenchidos pelos campos abaixo). */
export interface Contratacao {
  periodo: string;
  forma_pagamento: string;
  valor: number | null;
  inicio: string;
  observacao: string;
  contrato: File | null;
}

export const contratacaoVazia = (): Contratacao => ({
  periodo: '1m',
  forma_pagamento: 'pix',
  valor: null,
  inicio: '',
  observacao: '',
  contrato: null,
});

/** Acrescenta os campos da contratação ao FormData enviado à API. */
export function anexarContratacao(dados: FormData, c: Contratacao): FormData {
  dados.append('periodo', c.periodo);
  dados.append('forma_pagamento', c.forma_pagamento);
  dados.append('valor', String(c.valor ?? ''));
  if (c.inicio) dados.append('inicio', c.inicio);
  if (c.observacao) dados.append('observacao', c.observacao);
  if (c.contrato) dados.append('contrato', c.contrato);
  return dados;
}

/** Campos de período, pagamento e contrato, usados no cadastro e na renovação. */
@Component({
  selector: 'app-campos-contratacao',
  imports: [FormsModule],
  viewProviders: [{ provide: ControlContainer, useExisting: NgForm }],
  template: `
    <div class="grade-2">
      <label class="campo">
        <span>Período de acesso</span>
        <select name="periodo" [(ngModel)]="dados().periodo" required>
          @for (p of opcoes()?.periodos ?? []; track p.valor) {<option [value]="p.valor">{{ p.rotulo }}</option>}
        </select>
      </label>
      <label class="campo">
        <span>Forma de pagamento</span>
        <select name="forma_pagamento" [(ngModel)]="dados().forma_pagamento" required>
          @for (f of opcoes()?.formas_pagamento ?? []; track f.valor) {<option [value]="f.valor">{{ f.rotulo }}</option>}
        </select>
      </label>
      <label class="campo">
        <span>Valor pago (R$)</span>
        <input type="number" min="0" step="0.01" name="valor" [(ngModel)]="dados().valor" required />
        @if (erros()['valor']) {<small class="erro-campo">{{ erros()['valor'] }}</small>}
      </label>
      <label class="campo">
        <span>Início <em>(opcional)</em></span>
        <input type="date" name="inicio" [(ngModel)]="dados().inicio" />
        <small class="texto-suave">{{ dicaInicio() }}</small>
      </label>
    </div>
    <label class="campo">
      <span>Contrato assinado <em>(PDF ou foto, até 10 MB)</em></span>
      <input type="file" name="contrato" accept=".pdf,.jpg,.jpeg,.png" (change)="escolherArquivo($event)" />
      @if (erros()['contrato']) {<small class="erro-campo">{{ erros()['contrato'] }}</small>}
    </label>
    <label class="campo">
      <span>Observação <em>(opcional)</em></span>
      <textarea name="observacao" rows="2" maxlength="500" [(ngModel)]="dados().observacao" placeholder="Ex.: desconto de lançamento, pago no dia 10"></textarea>
    </label>
  `,
})
export class CamposContratacao {
  readonly dados = input.required<Contratacao>();
  readonly opcoes = input<OpcoesAdmin | null>(null);
  readonly erros = input<Record<string, string>>({});
  readonly dicaInicio = input('Se ficar em branco, começa hoje.');

  escolherArquivo(evento: Event): void {
    this.dados().contrato = (evento.target as HTMLInputElement).files?.[0] ?? null;
  }
}
