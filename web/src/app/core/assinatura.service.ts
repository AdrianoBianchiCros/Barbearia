import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ResumoAcesso } from './auth.service';

const API = `${environment.apiUrl}/assinatura`;

/** aberta: aguardando o Pix; informada: o dono avisou que pagou e falta a confirmação. */
export type StatusFatura = 'aberta' | 'informada' | 'paga' | 'cancelada';

export interface Fatura {
  id: number;
  periodo: string;
  periodo_rotulo: string;
  valor: string;
  vencimento: string;
  inicio: string;
  fim: string;
  txid: string;
  status: StatusFatura;
  informada_em: string | null;
}

export interface FaturaPaga {
  id: number;
  periodo: string;
  inicio: string;
  fim: string;
  valor: string;
  forma_pagamento: string;
  registrada_em: string | null;
}

export interface MinhaAssinaturaDados {
  acesso: ResumoAcesso;
  proxima: Fatura | null;
  pix_disponivel: boolean;
  pagas: FaturaPaga[];
}

export interface PixFatura {
  copia_e_cola: string;
  recebedor: string;
  valor: string;
}

/** Mensalidade do sistema no painel do dono: faturas e pagamento por Pix. */
@Injectable({ providedIn: 'root' })
export class AssinaturaService {
  private readonly http = inject(HttpClient);

  dados(): Observable<MinhaAssinaturaDados> {
    return this.http.get<MinhaAssinaturaDados>(API);
  }

  pix(faturaId: number): Observable<PixFatura> {
    return this.http.post<PixFatura>(`${API}/faturas/${faturaId}/pix`, {});
  }

  informarPagamento(faturaId: number): Observable<{ fatura: Fatura; message: string }> {
    return this.http.post<{ fatura: Fatura; message: string }>(`${API}/faturas/${faturaId}/informar-pagamento`, {});
  }
}
