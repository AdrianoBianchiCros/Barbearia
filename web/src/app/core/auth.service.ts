import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, catchError, of, tap, map } from 'rxjs';
import { environment } from '../../environments/environment';

export type Perfil = 'dono' | 'recepcao' | 'barbeiro';

export interface Usuario {
  id: number;
  nome: string;
  email: string;
  perfil: Perfil;
}

export interface Empresa {
  id: number;
  nome: string;
  slug: string;
  telefone: string | null;
  fuso_horario: string;
}

export interface Sessao {
  usuario: Usuario;
  empresa: Empresa;
}

interface RespostaLogin extends Sessao {
  token: string;
}

export interface DadosRegistro {
  empresa_nome: string;
  empresa_telefone?: string;
  nome: string;
  email: string;
  password: string;
  password_confirmation: string;
}

const CHAVE_TOKEN = 'barbearia.token';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);

  private readonly _sessao = signal<Sessao | null>(null);
  readonly sessao = this._sessao.asReadonly();
  readonly logado = computed(() => this._sessao() !== null);

  get token(): string | null {
    try {
      return localStorage.getItem(CHAVE_TOKEN);
    } catch {
      return null;
    }
  }

  login(email: string, password: string): Observable<Sessao> {
    return this.http
      .post<RespostaLogin>(`${environment.apiUrl}/auth/login`, { email, password })
      .pipe(map((r) => this.iniciarSessao(r)));
  }

  registrar(dados: DadosRegistro): Observable<Sessao> {
    return this.http
      .post<RespostaLogin>(`${environment.apiUrl}/auth/registrar`, dados)
      .pipe(map((r) => this.iniciarSessao(r)));
  }

  /** Recupera a sessão a partir do token salvo (ao recarregar a página). */
  carregarSessao(): Observable<boolean> {
    if (this._sessao()) return of(true);
    if (!this.token) return of(false);

    return this.http.get<Sessao>(`${environment.apiUrl}/auth/eu`).pipe(
      tap((s) => this._sessao.set(s)),
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
      this.router.navigate(['/login']);
    };
    this.http.post(`${environment.apiUrl}/auth/logout`, {}).subscribe({ next: sair, error: sair });
  }

  /** Encerra a sessão local (token expirado ou acesso revogado). */
  limpar(): void {
    try {
      localStorage.removeItem(CHAVE_TOKEN);
    } catch {
      // armazenamento indisponível: nada a remover
    }
    this._sessao.set(null);
  }

  private iniciarSessao(resposta: RespostaLogin): Sessao {
    try {
      localStorage.setItem(CHAVE_TOKEN, resposta.token);
    } catch {
      // sem armazenamento: a sessão dura até recarregar a página
    }
    const sessao = { usuario: resposta.usuario, empresa: resposta.empresa };
    this._sessao.set(sessao);
    return sessao;
  }
}
