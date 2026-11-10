import { TestBed } from '@angular/core/testing';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter, Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environments/environment';
import { AuthService } from './auth.service';
import { authInterceptor } from './auth.interceptor';

const sessao = {
  usuario: { id: 1, nome: 'José Silva', email: 'ze@exemplo.test', perfil: 'dono' as const },
  empresa: { id: 1, nome: 'Barbearia do Zé', slug: 'barbearia-do-ze', telefone: null, fuso_horario: 'America/Sao_Paulo' },
  acesso: null,
};

describe('AuthService', () => {
  let auth: AuthService;
  let http: HttpTestingController;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({
      providers: [
        provideRouter([{ path: '**', children: [] }]),
        provideHttpClient(withInterceptors([authInterceptor])),
        provideHttpClientTesting(),
      ],
    });
    auth = TestBed.inject(AuthService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('faz login, guarda o token e envia nas próximas chamadas', () => {
    auth.login('ze@exemplo.test', 'segredo123').subscribe();
    http.expectOne(`${environment.apiUrl}/auth/login`).flush({ token: 'abc', ...sessao });

    expect(auth.logado()).toBe(true);
    expect(auth.token).toBe('abc');

    TestBed.inject(HttpClient).get(`${environment.apiUrl}/empresa`).subscribe();
    const req = http.expectOne(`${environment.apiUrl}/empresa`);
    expect(req.request.headers.get('Authorization')).toBe('Bearer abc');
    req.flush({});
  });

  it('sem token, não chama a API ao carregar a sessão', () => {
    let resultado: boolean | undefined;
    auth.carregarSessao().subscribe((ok) => (resultado = ok));
    expect(resultado).toBe(false);
    http.expectNone(`${environment.apiUrl}/auth/eu`);
  });

  it('token expirado (401) encerra a sessão local', () => {
    localStorage.setItem('barbearia.token', 'expirado');
    let resultado: boolean | undefined;
    auth.carregarSessao().subscribe((ok) => (resultado = ok));
    http.expectOne(`${environment.apiUrl}/auth/eu`).flush({ message: 'Unauthenticated.' }, { status: 401, statusText: 'Unauthorized' });

    expect(resultado).toBe(false);
    expect(auth.token).toBeNull();
    expect(auth.logado()).toBe(false);
  });

  it('plano vencido (403 acesso_vencido) encerra a sessão e leva ao login com o aviso', () => {
    const router = TestBed.inject(Router);
    const navegar = vi.spyOn(router, 'navigate').mockResolvedValue(true);
    localStorage.setItem('barbearia.token', 'abc');

    TestBed.inject(HttpClient).get(`${environment.apiUrl}/agenda/dia`).subscribe({ error: () => undefined });
    http.expectOne(`${environment.apiUrl}/agenda/dia`).flush(
      { message: 'O acesso desta barbearia venceu.', codigo: 'acesso_vencido' },
      { status: 403, statusText: 'Forbidden' },
    );

    expect(auth.token).toBeNull();
    expect(navegar).toHaveBeenCalledWith(['/login'], { queryParams: { acesso: 'vencido' } });
  });

  it('chamadas da área do administrador usam o token do administrador', () => {
    localStorage.setItem('barbearia.token', 'dono');
    localStorage.setItem('barbearia.tokenAdmin', 'admin');

    TestBed.inject(HttpClient).get(`${environment.apiUrl}/admin/barbearias`).subscribe();
    const req = http.expectOne(`${environment.apiUrl}/admin/barbearias`);
    expect(req.request.headers.get('Authorization')).toBe('Bearer admin');
    req.flush({ barbearias: [] });
  });
});
