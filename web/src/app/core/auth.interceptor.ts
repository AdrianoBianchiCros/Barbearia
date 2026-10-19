import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { environment } from '../../environments/environment';
import { AuthService } from './auth.service';

/** Envia o token nas chamadas à API e volta ao login quando a sessão expira. */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  const paraApi = req.url.startsWith(environment.apiUrl);
  const token = auth.token;
  const requisicao =
    paraApi && token
      ? req.clone({ setHeaders: { Authorization: `Bearer ${token}`, Accept: 'application/json' } })
      : req;

  return next(requisicao).pipe(
    catchError((erro: HttpErrorResponse) => {
      const sessaoInvalida =
        erro.status === 401 || (erro.status === 403 && erro.error?.message?.startsWith('Acesso desativado'));
      if (paraApi && token && sessaoInvalida) {
        auth.limpar();
        router.navigate(['/login']);
      }
      return throwError(() => erro);
    }),
  );
};
