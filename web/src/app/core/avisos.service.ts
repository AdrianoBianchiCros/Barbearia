import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

const API = environment.apiUrl;

/** Cancelamento ou remarcação mostrado no sino da agenda. */
export interface AvisoAgenda {
  id: number;
  agendamento_id: number;
  tipo: 'cancelamento' | 'remarcacao';
  origem: 'cliente' | 'painel';
  cliente: string;
  barbeiro: string | null;
  barbeiro_anterior: string | null;
  inicio: string; // ISO no fuso da barbearia
  inicio_anterior: string | null;
  quando: string;
  visto: boolean;
}

export interface ListaAvisos {
  nao_vistos: number;
  avisos: AvisoAgenda[];
}

@Injectable({ providedIn: 'root' })
export class AvisosService {
  private readonly http = inject(HttpClient);

  listar(): Observable<ListaAvisos> {
    return this.http.get<ListaAvisos>(`${API}/avisos`);
  }

  marcarVistos(): Observable<{ nao_vistos: number }> {
    return this.http.post<{ nao_vistos: number }>(`${API}/avisos/vistos`, {});
  }
}
