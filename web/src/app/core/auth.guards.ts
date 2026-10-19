import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { map } from 'rxjs';
import { AuthService, Perfil } from './auth.service';

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
  return auth.carregarSessao().pipe(map((ok) => (ok ? router.createUrlTree(['/agenda']) : true)));
};

/** Restringe a rota a determinados perfis (use depois de exigeLogin). */
export const exigePerfil =
  (...perfis: Perfil[]): CanActivateFn =>
  () => {
    const perfil = inject(AuthService).sessao()?.usuario.perfil;
    return (perfil && perfis.includes(perfil)) || inject(Router).createUrlTree(['/agenda']);
  };

/** Atalho usado nas telas para mostrar/ocultar botões de edição. */
export function podeEditar(auth: AuthService, ...perfis: Perfil[]): boolean {
  const perfil = auth.sessao()?.usuario.perfil;
  return !!perfil && perfis.includes(perfil);
}
