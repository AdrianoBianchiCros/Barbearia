import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { map } from 'rxjs';
import { AuthService, Perfil, telaInicial } from './auth.service';

/**
 * Área logada de uma tela inicial (painel da barbearia ou painel do barbeiro):
 * sem sessão, vai para o login; quem é de outra área (ou ainda precisa criar a senha) vai para a sua.
 */
const exigeArea =
  (inicio: string, login: string): CanActivateFn =>
  () => {
    const auth = inject(AuthService);
    const router = inject(Router);
    return auth.carregarSessao().pipe(
      map((ok) => {
        if (!ok) return router.createUrlTree([login]);
        const destino = telaInicial(auth.sessao()?.usuario);
        return destino === inicio || router.createUrlTree([destino]);
      }),
    );
  };

/** Painel da barbearia (dono e recepção). */
export const exigeLogin: CanActivateFn = exigeArea('/agenda', '/login');

/** Painel do barbeiro: só a própria agenda e a própria comissão. */
export const exigeBarbeiro: CanActivateFn = exigeArea('/barbeiro', '/barbeiro/login');

/** Tela de troca da senha provisória: só para quem entrou com ela. */
export const exigeTrocaSenha: CanActivateFn = exigeArea('/trocar-senha', '/login');

/** Login e telas de senha: quem já está logado vai direto para a sua tela inicial. */
export const somenteVisitante: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);
  return auth.carregarSessao().pipe(map((ok) => (ok ? router.createUrlTree([telaInicial(auth.sessao()?.usuario)]) : true)));
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
