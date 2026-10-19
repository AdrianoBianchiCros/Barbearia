import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { FormaPagamento } from './financeiro.service';

export interface Indicadores {
  faturamento: number;
  comandas_pagas: number;
  ticket_medio: number;
  descontos: number;
  faturamento_servicos: number;
  faturamento_produtos: number;
  agendamentos: number;
  concluidos: number;
  faltas: number;
  cancelados: number;
  taxa_faltas: number;
  agendados_online: number;
  comissoes: number;
  despesas_pagas: number;
  resultado: number;
}

export interface ResumoRelatorio {
  inicio: string;
  fim: string;
  periodo_anterior: { inicio: string; fim: string };
  indicadores: Indicadores;
  anterior: Indicadores;
  formas_pagamento: { forma: FormaPagamento; total: number }[];
  serie: { agrupamento: 'dia' | 'mes'; pontos: { data: string; faturamento: number; atendimentos: number }[] };
  barbeiros: {
    funcionario_id: number;
    nome: string;
    concluidos: number;
    faltas: number;
    taxa_faltas: number;
    faturamento: number;
    ticket_medio: number;
    comissao: number;
    ocupacao: number;
  }[];
  servicos: { nome: string; quantidade: number; receita: number }[];
  produtos: { nome: string; quantidade: number; receita: number }[];
  horarios_pico: { dia_semana: number; hora: number; quantidade: number }[];
}

export interface RelatorioClientes {
  atendidos: number;
  novos: number;
  recorrentes: number;
  dias_sem_voltar: number;
  sumidos: {
    id: number;
    nome: string;
    telefone: string;
    aceita_whatsapp: boolean;
    ultima_visita: string;
    dias_sem_vir: number;
    visitas: number;
    total_gasto: number;
    ultimo_barbeiro: string | null;
  }[];
}

@Injectable({ providedIn: 'root' })
export class RelatoriosService {
  private readonly http = inject(HttpClient);

  resumo(inicio: string, fim: string): Observable<ResumoRelatorio> {
    return this.http.get<ResumoRelatorio>(`${environment.apiUrl}/relatorios/resumo`, { params: { inicio, fim } });
  }

  clientes(inicio: string, fim: string, diasSemVoltar: number): Observable<RelatorioClientes> {
    return this.http.get<RelatorioClientes>(`${environment.apiUrl}/relatorios/clientes`, {
      params: { inicio, fim, dias_sem_voltar: diasSemVoltar },
    });
  }
}
