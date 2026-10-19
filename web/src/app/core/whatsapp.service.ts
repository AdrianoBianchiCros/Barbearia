import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

const API = environment.apiUrl;

export type StatusMensagem = 'pendente' | 'enviado' | 'entregue' | 'lido' | 'falhou' | 'ignorado';

export interface ConfigWhatsapp {
  modo: 'teste' | 'real';
  wa_ativo: boolean;
  wa_lembrete_24h: boolean;
  wa_lembrete_2h: boolean;
  wa_phone_number_id: string | null;
  wa_token_configurado: boolean;
  cancelamento_antecedencia_horas: number;
  webhook_url: string;
  mes: Partial<Record<StatusMensagem, number>>;
}

export interface MensagemWhatsapp {
  id: number;
  tipo: 'confirmacao' | 'remarcacao' | 'lembrete_24h' | 'lembrete_2h';
  status: StatusMensagem;
  telefone: string;
  cliente: string | null;
  horario: string | null;
  erro: string | null;
  resposta: string | null;
  criada_em: string;
}

export interface PaginaMensagens {
  data: MensagemWhatsapp[];
  pagina: number;
  ultima_pagina: number;
  total: number;
}

@Injectable({ providedIn: 'root' })
export class WhatsappService {
  private readonly http = inject(HttpClient);

  buscar(): Observable<ConfigWhatsapp> {
    return this.http.get<ConfigWhatsapp>(`${API}/whatsapp`);
  }
  salvar(dados: object): Observable<ConfigWhatsapp> {
    return this.http.put<ConfigWhatsapp>(`${API}/whatsapp`, dados);
  }
  mensagens(pagina: number, status: string): Observable<PaginaMensagens> {
    let params = new HttpParams().set('page', pagina);
    if (status) params = params.set('status', status);
    return this.http.get<PaginaMensagens>(`${API}/whatsapp/mensagens`, { params });
  }
}
