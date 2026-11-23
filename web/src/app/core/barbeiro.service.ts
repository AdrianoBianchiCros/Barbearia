import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { StatusAgendamento } from './agenda.service';
import { RelatorioComissoes } from './financeiro.service';

const API = `${environment.apiUrl}/barbeiro`;

export interface AtendimentoBarbeiro {
  id: number;
  inicio: string;
  fim: string;
  status: StatusAgendamento;
  valor_total: string;
  observacoes: string | null;
  cliente: { nome: string; observacoes: string | null } | null;
  servicos: string[];
}

/** Agenda do dia só com os atendimentos do barbeiro logado. */
export interface AgendaBarbeiro {
  data: string;
  agora: string;
  barbeiro: { id: number; nome: string; foto_url: string | null };
  expediente: { inicio: string; fim: string }[];
  bloqueios: { inicio: string; fim: string; motivo: string | null }[];
  agendamentos: AtendimentoBarbeiro[];
}

/** Chamadas do painel do barbeiro (a API recusa as demais rotas para esse perfil). */
@Injectable({ providedIn: 'root' })
export class BarbeiroService {
  private readonly http = inject(HttpClient);

  agenda(data: string): Observable<AgendaBarbeiro> {
    return this.http.get<AgendaBarbeiro>(`${API}/agenda`, { params: { data } });
  }

  comissoes(inicio: string, fim: string): Observable<RelatorioComissoes> {
    return this.http.get<RelatorioComissoes>(`${API}/comissoes`, { params: { inicio, fim } });
  }
}
