import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { FormsModule } from '@angular/forms';
import { CamposContratacao, Contratacao, contratacaoVazia } from './campos-contratacao';

const OPCOES = {
  periodos: [
    { valor: '1m', rotulo: '1 mês' },
    { valor: 'divulgacao', rotulo: 'Divulgação (parceria, sem mensalidade)' },
  ],
  formas_pagamento: [{ valor: 'pix', rotulo: 'Pix' }],
  carencia_dias: 5,
  aviso_dias: 10,
  teste_gratis_dias: 7,
};

@Component({
  imports: [FormsModule, CamposContratacao],
  template: `<form #f="ngForm"><app-campos-contratacao [dados]="dados" [opcoes]="opcoes" /></form>`,
})
class Host {
  dados: Contratacao = contratacaoVazia();
  opcoes = OPCOES;
}

async function montar(periodo: string) {
  await TestBed.configureTestingModule({ imports: [Host] }).compileComponents();
  const fixture = TestBed.createComponent(Host);
  fixture.componentInstance.dados.periodo = periodo;
  fixture.detectChanges();
  await fixture.whenStable();
  fixture.detectChanges();
  return fixture.nativeElement as HTMLElement;
}

describe('CamposContratacao', () => {
  it('no plano pago pede forma de pagamento e mensalidade', async () => {
    const tela = await montar('1m');
    expect(tela.querySelector('input[name=valor]')).not.toBeNull();
    expect(tela.querySelector('select[name=forma_pagamento]')).not.toBeNull();
    expect(tela.textContent).not.toContain('Parceria de divulgação');
  });

  it('na Divulgação esconde valores e explica a parceria', async () => {
    const tela = await montar('divulgacao');
    expect(tela.querySelector('input[name=valor]')).toBeNull();
    expect(tela.querySelector('select[name=forma_pagamento]')).toBeNull();
    expect(tela.textContent).toContain('Parceria de divulgação');
    expect(tela.textContent).toContain('sem vencimento');
  });
});
