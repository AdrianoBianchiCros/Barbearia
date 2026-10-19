import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { Pagina } from './modelos';

const API = environment.apiUrl;

export type FormaPagamento = 'dinheiro' | 'pix' | 'debito' | 'credito';

export const FORMAS: { id: FormaPagamento; rotulo: string }[] = [
  { id: 'pix', rotulo: 'Pix' },
  { id: 'dinheiro', rotulo: 'Dinheiro' },
  { id: 'debito', rotulo: 'Débito' },
  { id: 'credito', rotulo: 'Crédito' },
];

export const ROTULO_FORMA: Record<FormaPagamento, string> = {
  pix: 'Pix',
  dinheiro: 'Dinheiro',
  debito: 'Débito',
  credito: 'Crédito',
};

export interface Produto {
  id: number;
  nome: string;
  preco: string;
  custo: string | null;
  estoque: number | null;
  comissao_percentual: string;
  ativo: boolean;
}

export interface ItemComanda {
  id?: number;
  tipo: 'servico' | 'produto';
  servico_id: number | null;
  produto_id: number | null;
  funcionario_id: number | null;
  descricao: string;
  quantidade: number;
  preco_unitario: string | number;
  total?: string;
  comissao_valor?: string;
}

export interface Comanda {
  id: number;
  status: 'aberta' | 'paga' | 'cancelada';
  total: string;
  subtotal: string;
  desconto: string;
  agendamento_id: number | null;
  horario: string | null;
  pago_em: string | null;
  criada_em: string | null;
  observacoes: string | null;
  cliente: { id: number; nome: string; telefone: string } | null;
  barbeiro: { id: number; nome: string } | null;
  itens: ItemComanda[];
  pagamentos: { id: number; forma: FormaPagamento; valor: string }[];
}

export type ComandaResumo = Omit<Comanda, 'itens' | 'pagamentos' | 'subtotal' | 'desconto' | 'observacoes'>;

export interface ResumoCaixa {
  valor_inicial: number;
  recebido_por_forma: Record<FormaPagamento, number>;
  total_recebido: number;
  atendimentos: number;
  suprimentos: number;
  sangrias: number;
  despesas_pagas: number;
  dinheiro_esperado: number;
  valor_contado: number | null;
  diferenca: number | null;
}

export interface Caixa {
  id: number;
  aberto: boolean;
  aberto_em: string;
  fechado_em: string | null;
  aberto_por: string | null;
  fechado_por: string | null;
  observacoes: string | null;
  resumo: ResumoCaixa;
  /** Dinheiro acima do limite da gaveta (0 = dentro do limite ou sem limite definido). */
  excedente_gaveta?: number;
  movimentos?: { id: number; tipo: 'suprimento' | 'sangria'; valor: string; descricao: string | null; usuario: string | null; em: string }[];
  recebimentos?: { comanda_id: number; cliente: string | null; barbeiro: string | null; total: string; formas: FormaPagamento[]; pago_em: string }[];
}

export interface Lancamento {
  id: number;
  tipo: 'despesa' | 'vale' | 'repasse_comissao';
  categoria: string | null;
  descricao: string;
  valor: string;
  funcionario: { id: number; nome: string } | null;
  vencimento: string | null;
  pago_em: string | null;
  do_caixa: boolean;
  atrasada: boolean;
}

export interface ComissaoBarbeiro {
  funcionario_id: number;
  nome: string;
  comissao_percentual: string;
  atendimentos: number;
  faturado_servicos: number;
  faturado_produtos: number;
  comissao: number;
  vales: number;
  repasses: number;
  saldo: number;
}

export interface RelatorioComissoes {
  inicio: string;
  fim: string;
  barbeiros: ComissaoBarbeiro[];
  totais: { comissao: number; vales: number; repasses: number; saldo: number };
}

export const CATEGORIAS_DESPESA: Record<string, string> = {
  aluguel: 'Aluguel',
  produtos: 'Produtos',
  contas: 'Contas (luz, água, internet)',
  salarios: 'Salários',
  impostos: 'Impostos',
  manutencao: 'Manutenção',
  marketing: 'Marketing',
  outros: 'Outros',
};

@Injectable({ providedIn: 'root' })
export class FinanceiroService {
  private readonly http = inject(HttpClient);

  // Produtos
  listarProdutos(): Observable<Produto[]> {
    return this.http.get<Produto[]>(`${API}/produtos`);
  }
  salvarProduto(dados: object, id?: number): Observable<Produto> {
    return id ? this.http.put<Produto>(`${API}/produtos/${id}`, dados) : this.http.post<Produto>(`${API}/produtos`, dados);
  }
  excluirProduto(id: number): Observable<void> {
    return this.http.delete<void>(`${API}/produtos/${id}`);
  }

  // Comandas
  comandasAbertas(): Observable<ComandaResumo[]> {
    return this.http.get<ComandaResumo[]>(`${API}/comandas`, { params: { status: 'aberta' } });
  }
  comanda(id: number): Observable<Comanda> {
    return this.http.get<Comanda>(`${API}/comandas/${id}`);
  }
  comandaDoAgendamento(agendamentoId: number): Observable<Comanda> {
    return this.http.post<Comanda>(`${API}/agendamentos/${agendamentoId}/comanda`, {});
  }
  novaVenda(): Observable<Comanda> {
    return this.http.post<Comanda>(`${API}/comandas`, {});
  }
  fecharComanda(
    id: number,
    dados: { itens: object[]; desconto: number; pagamentos: { forma: FormaPagamento; valor: number }[]; observacoes?: string | null },
  ): Observable<Comanda> {
    return this.http.post<Comanda>(`${API}/comandas/${id}/fechar`, dados);
  }
  cancelarComanda(id: number): Observable<Comanda> {
    return this.http.post<Comanda>(`${API}/comandas/${id}/cancelar`, {});
  }
  estornarComanda(id: number): Observable<Comanda> {
    return this.http.post<Comanda>(`${API}/comandas/${id}/estornar`, {});
  }

  // Caixa
  caixaAtual(): Observable<{ caixa: Caixa | null; comandas_abertas: number; limite_gaveta: number | null }> {
    return this.http.get<{ caixa: Caixa | null; comandas_abertas: number; limite_gaveta: number | null }>(`${API}/caixa/atual`);
  }
  abrirCaixa(valorInicial: number): Observable<Caixa> {
    return this.http.post<Caixa>(`${API}/caixa/abrir`, { valor_inicial: valorInicial });
  }
  movimentarCaixa(tipo: 'suprimento' | 'sangria', valor: number, descricao: string): Observable<Caixa> {
    return this.http.post<Caixa>(`${API}/caixa/movimentos`, { tipo, valor, descricao });
  }
  fecharCaixa(valorContado: number, observacoes: string): Observable<Caixa> {
    return this.http.post<Caixa>(`${API}/caixa/fechar`, { valor_contado: valorContado, observacoes });
  }
  historicoCaixas(pagina: number): Observable<Pagina<Caixa>> {
    return this.http.get<Pagina<Caixa>>(`${API}/caixas`, { params: { page: pagina } });
  }

  // Lançamentos (despesas, vales, repasses)
  lancamentos(tipo: Lancamento['tipo'], mes: string): Observable<{ mes: string; total: number; total_pago: number; lancamentos: Lancamento[] }> {
    return this.http.get<{ mes: string; total: number; total_pago: number; lancamentos: Lancamento[] }>(`${API}/lancamentos`, {
      params: new HttpParams().set('tipo', tipo).set('mes', mes),
    });
  }
  criarLancamento(dados: object): Observable<Lancamento> {
    return this.http.post<Lancamento>(`${API}/lancamentos`, dados);
  }
  pagarLancamento(id: number, comCaixa: boolean): Observable<Lancamento> {
    return this.http.post<Lancamento>(`${API}/lancamentos/${id}/pagar`, { pagar_com_caixa: comCaixa });
  }
  excluirLancamento(id: number): Observable<void> {
    return this.http.delete<void>(`${API}/lancamentos/${id}`);
  }

  // Comissões
  comissoes(inicio: string, fim: string): Observable<RelatorioComissoes> {
    return this.http.get<RelatorioComissoes>(`${API}/comissoes`, { params: { inicio, fim } });
  }
}
