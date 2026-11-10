import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { environment } from '../../environments/environment';
import { AdminService } from './admin.service';
import { AuthService } from './auth.service';

/**
 * Envia o token nas chamadas à API e volta ao login quando a sessão expira.
 * As chamadas da área do administrador (/api/admin) usam o token do administrador.
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const admin = inject(AdminService);
  const router = inject(Router);

  const paraAdmin = req.url.startsWith(`${environment.apiUrl}/admin/`);
  const paraApi = req.url.startsWith(environment.apiUrl) && !paraAdmin;
  const token = paraAdmin ? admin.token : auth.token;
  if (paraAdmin) {
    const requisicao = token ? req.clone({ setHeaders: { Authorization: `Bearer ${token}`, Accept: 'application/json' } }) : req;
    return next(requisicao).pipe(
      catchError((erro: HttpErrorResponse) => {
        if (token && (erro.status === 401 || erro.status === 403)) {
          admin.limpar();
          router.navigate(['/admin/login']);
        }
        return throwError(() => erro);
      }),
    );
  }

  const requisicao =
    paraApi && token
      ? req.clone({ setHeaders: { Authorization: `Bearer ${token}`, Accept: 'application/json' } })
      : req;

  return next(requisicao).pipe(
    catchError((erro: HttpErrorResponse) => {
      const sessaoInvalida =
        erro.status === 401 || (erro.status === 403 && erro.error?.message?.startsWith('Acesso desativado'));
      // Prazo do plano vencido: a API já encerrou a sessão; o login explica o motivo.
      const acessoVencido = erro.status === 403 && erro.error?.codigo === 'acesso_vencido';
      if (paraApi && token && (sessaoInvalida || acessoVencido)) {
        auth.limpar();
        router.navigate(['/login'], acessoVencido ? { queryParams: { acesso: 'vencido' } } : {});
      }
      return throwError(() => erro);
    }),
  );
};
