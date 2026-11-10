import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { map } from 'rxjs';
import { AdminService } from './admin.service';

/** Área do administrador: sem sessão, vai para o login do administrador. */
export const exigeAdmin: CanActivateFn = () => {
  const admin = inject(AdminService);
  const router = inject(Router);
  return admin.carregarSessao().pipe(map((ok) => ok || router.createUrlTree(['/admin/login'])));
};

/** Login do administrador: quem já entrou vai direto para a lista de barbearias. */
export const somenteVisitanteAdmin: CanActivateFn = () => {
  const admin = inject(AdminService);
  const router = inject(Router);
  return admin.carregarSessao().pipe(map((ok) => (ok ? router.createUrlTree(['/admin/barbearias']) : true)));
};
