import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { map } from 'rxjs';
import { AuthService } from './auth.service';

/** Área logada: sem sessão válida, vai para o login. */
export const exigeLogin: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);
  return auth.carregarSessao().pipe(map((ok) => ok || router.createUrlTree(['/login'])));
};

/** Login e cadastro: quem já está logado vai direto para o painel. */
export const somenteVisitante: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);
  return auth.carregarSessao().pipe(map((ok) => (ok ? router.createUrlTree(['/painel']) : true)));
};
