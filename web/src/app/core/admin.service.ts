import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, catchError, map, of, tap } from 'rxjs';
import { environment } from '../../environments/environment';
import { ResumoAcesso } from './auth.service';

export interface Administrador {
  id: number;
  nome: string;
  email: string;
}

export interface Opcao {
  valor: string;
  rotulo: string;
}

export interface OpcoesAdmin {
  periodos: Opcao[];
  formas_pagamento: Opcao[];
  carencia_dias: number;
  aviso_dias: number;
}

export interface Assinatura {
  id: number;
  periodo: string;
  inicio: string;
  fim: string;
  forma_pagamento: string;
  valor: string;
  observacao: string | null;
  contrato_nome: string | null;
  tem_contrato: boolean;
  created_at: string;
  administrador: { id: number; nome: string } | null;
}

export interface BarbeariaAdmin {
  id: number;
  nome: string;
  slug: string;
  telefone: string | null;
  documento: string | null;
  ativo: boolean;
  criada_em: string | null;
  acesso: ResumoAcesso;
  dono: { id: number; nome: string; email: string } | null;
  ultima_assinatura: Pick<Assinatura, 'periodo' | 'forma_pagamento' | 'valor' | 'inicio' | 'fim'> | null;
  pagina_url: string;
}

export interface DetalheBarbearia extends BarbeariaAdmin {
  assinaturas: Assinatura[];
  total_usuarios: number;
}

/** Login do dono gerado pelo administrador (a senha provisória aparece uma única vez). */
export interface DadosAcesso {
  email: string;
  senha_provisoria: string;
  painel_url: string;
}

const CHAVE_TOKEN = 'barbearia.tokenAdmin';
const API = `${environment.apiUrl}/admin`;

/** Sessão e chamadas da área do administrador da plataforma (separada do painel das barbearias). */
@Injectable({ providedIn: 'root' })
export class AdminService {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);

  private readonly _admin = signal<Administrador | null>(null);
  readonly admin = this._admin.asReadonly();
  readonly logado = computed(() => this._admin() !== null);

  get token(): string | null {
    try {
      return localStorage.getItem(CHAVE_TOKEN);
    } catch {
      return null;
    }
  }

  login(email: string, password: string): Observable<Administrador> {
    return this.http.post<{ token: string; admin: Administrador }>(`${API}/auth/login`, { email, password }).pipe(
      map((r) => {
        try {
          localStorage.setItem(CHAVE_TOKEN, r.token);
        } catch {
          // sem armazenamento: a sessão dura até recarregar a página
        }
        this._admin.set(r.admin);
        return r.admin;
      }),
    );
  }

  carregarSessao(): Observable<boolean> {
    if (this._admin()) return of(true);
    if (!this.token) return of(false);

    return this.http.get<{ admin: Administrador }>(`${API}/auth/eu`).pipe(
      tap((r) => this._admin.set(r.admin)),
      map(() => true),
      catchError(() => {
        this.limpar();
        return of(false);
      }),
    );
  }

  logout(): void {
    const sair = () => {
      this.limpar();
      this.router.navigate(['/admin/login']);
    };
    this.http.post(`${API}/auth/logout`, {}).subscribe({ next: sair, error: sair });
  }

  limpar(): void {
    try {
      localStorage.removeItem(CHAVE_TOKEN);
    } catch {
      // armazenamento indisponível: nada a remover
    }
    this._admin.set(null);
  }

  opcoes(): Observable<OpcoesAdmin> {
    return this.http.get<OpcoesAdmin>(`${API}/opcoes`);
  }

  barbearias(): Observable<BarbeariaAdmin[]> {
    return this.http.get<{ barbearias: BarbeariaAdmin[] }>(`${API}/barbearias`).pipe(map((r) => r.barbearias));
  }

  barbearia(id: number): Observable<DetalheBarbearia> {
    return this.http.get<DetalheBarbearia>(`${API}/barbearias/${id}`);
  }

  /** Dados em FormData por causa do contrato anexado. */
  criar(dados: FormData): Observable<{ barbearia: BarbeariaAdmin; acesso: DadosAcesso }> {
    return this.http.post<{ barbearia: BarbeariaAdmin; acesso: DadosAcesso }>(`${API}/barbearias`, dados);
  }

  renovar(id: number, dados: FormData): Observable<DetalheBarbearia> {
    return this.http.post<DetalheBarbearia>(`${API}/barbearias/${id}/renovar`, dados);
  }

  suspender(id: number): Observable<DetalheBarbearia> {
    return this.http.post<DetalheBarbearia>(`${API}/barbearias/${id}/suspender`, {});
  }

  reativar(id: number): Observable<DetalheBarbearia> {
    return this.http.post<DetalheBarbearia>(`${API}/barbearias/${id}/reativar`, {});
  }

  novaSenha(id: number): Observable<{ acesso: DadosAcesso }> {
    return this.http.post<{ acesso: DadosAcesso }>(`${API}/barbearias/${id}/nova-senha`, {});
  }

  /** Baixa o contrato com o token (um link direto não levaria a autenticação). */
  contrato(assinaturaId: number): Observable<Blob> {
    return this.http.get(`${API}/assinaturas/${assinaturaId}/contrato`, { responseType: 'blob' });
  }
}

/** Rótulos das situações do prazo de acesso. */
export const SITUACOES_ACESSO: Record<ResumoAcesso['situacao'], { rotulo: string; classe: string }> = {
  sem_prazo: { rotulo: 'Sem prazo', classe: '' },
  em_dia: { rotulo: 'Em dia', classe: 'etiqueta-ok' },
  vencendo: { rotulo: 'Vencendo', classe: 'etiqueta-alerta' },
  carencia: { rotulo: 'Em carência', classe: 'etiqueta-erro' },
  vencida: { rotulo: 'Bloqueada', classe: 'etiqueta-erro' },
  suspensa: { rotulo: 'Suspensa', classe: 'etiqueta-erro' },
};
