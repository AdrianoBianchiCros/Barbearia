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

@Injectable({ providedIn: 'root' })
export class AgendaService {
  private readonly http = inject(HttpClient);

  dia(data?: string): Observable<AgendaDia> {
    const params = data ? new HttpParams().set('data', data) : undefined;
    return this.http.get<AgendaDia>(`${environment.apiUrl}/agenda/dia`, { params });
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
