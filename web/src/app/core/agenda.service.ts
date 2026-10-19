import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

export type StatusAgendamento = 'agendado' | 'confirmado' | 'em_atendimento' | 'concluido' | 'cancelado' | 'faltou';

export interface AgendamentoDoDia {
  id: number;
  inicio: string; // ISO no fuso da barbearia, ex.: 2026-10-19T09:00:00-03:00
  fim: string;
  status: StatusAgendamento;
  origem: 'recepcao' | 'link_publico' | 'whatsapp';
  valor_total: string;
  observacoes: string | null;
  cliente: { id: number; nome: string; telefone: string; observacoes: string | null } | null;
  servicos: string[];
  servico_ids: number[];
  comanda: { id: number; status: 'aberta' | 'paga' | 'cancelada'; total: string } | null;
}

export interface ColunaBarbeiro {
  id: number;
  nome: string;
  foto_url: string | null;
  expediente: { inicio: string; fim: string }[];
  bloqueios: { id: number; inicio: string; fim: string; motivo: string | null }[];
  agendamentos: AgendamentoDoDia[];
}

export interface AgendaDia {
  data: string;
  fuso_horario: string;
  agora: string;
  meu_funcionario_id: number | null;
  barbeiros: ColunaBarbeiro[];
}

export const STATUS_ROTULO: Record<StatusAgendamento, string> = {
  agendado: 'Agendado',
  confirmado: 'Confirmado',
  em_atendimento: 'Em atendimento',
  concluido: 'Concluído',
  cancelado: 'Cancelado',
  faltou: 'Faltou',
};

export interface OpcaoBarbeiro {
  funcionario_id: number;
  nome: string;
  foto_url: string | null;
  duracao_minutos: number;
  valor_total: string;
  horarios: string[];
}

export interface DadosAgendamento {
  cliente_id?: number | null;
  novo_cliente?: { nome: string; telefone: string; aceita_whatsapp: boolean } | null;
  funcionario_id: number;
  servico_ids: number[];
  data: string;
  hora: string;
  observacoes?: string | null;
  encaixe?: boolean;
}

export interface ResultadoBusca {
  id: number;
  data: string;
  inicio: string;
  fim: string;
  status: StatusAgendamento;
  cliente: { id: number; nome: string; telefone: string } | null;
  barbeiro: { id: number; nome: string } | null;
  servicos: string[];
}

export interface BuscaAgendamentos {
  proximos: ResultadoBusca[];
  anteriores: ResultadoBusca[];
}

@Injectable({ providedIn: 'root' })
export class AgendaService {
  private readonly http = inject(HttpClient);

  dia(data?: string): Observable<AgendaDia> {
    const params = data ? new HttpParams().set('data', data) : undefined;
    return this.http.get<AgendaDia>(`${environment.apiUrl}/agenda/dia`, { params });
  }

  /** Agendamentos por nome/telefone do cliente ou serviço: próximos e dos últimos 60 dias. */
  buscar(termo: string): Observable<BuscaAgendamentos> {
    return this.http.get<BuscaAgendamentos>(`${environment.apiUrl}/agenda/busca`, {
      params: new HttpParams().set('q', termo),
    });
  }

  /** Horários livres por barbeiro para os serviços escolhidos. */
  horarios(data: string, servicoIds: number[], ignorarAgendamentoId?: number): Observable<OpcaoBarbeiro[]> {
    let params = new HttpParams().set('data', data);
    servicoIds.forEach((id) => (params = params.append('servico_ids[]', id)));
    if (ignorarAgendamentoId) params = params.set('ignorar_agendamento_id', ignorarAgendamentoId);
    return this.http.get<OpcaoBarbeiro[]>(`${environment.apiUrl}/agenda/horarios`, { params });
  }

  criar(dados: DadosAgendamento): Observable<{ id: number }> {
    return this.http.post<{ id: number }>(`${environment.apiUrl}/agendamentos`, dados);
  }

  remarcar(id: number, dados: DadosAgendamento): Observable<{ id: number }> {
    return this.http.put<{ id: number }>(`${environment.apiUrl}/agendamentos/${id}`, dados);
  }

  alterarStatus(id: number, status: StatusAgendamento): Observable<{ id: number; status: StatusAgendamento }> {
    return this.http.patch<{ id: number; status: StatusAgendamento }>(
      `${environment.apiUrl}/agendamentos/${id}/status`,
      { status },
    );
  }
}

/** Minutos desde 00:00 de um horário ISO no fuso da barbearia (lê o texto, sem converter fuso). */
export function minutosDoDia(iso: string): number {
  const [h, m] = iso.slice(11, 16).split(':').map(Number);
  return h * 60 + m;
}

export function minutosDeHora(hhmm: string): number {
  const [h, m] = hhmm.split(':').map(Number);
  return h * 60 + m;
}

export function hora(iso: string): string {
  return iso.slice(11, 16);
}
