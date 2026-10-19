import { Routes } from '@angular/router';
import { exigeLogin, somenteVisitante } from './core/auth.guards';

export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'painel' },
  {
    path: 'login',
    canActivate: [somenteVisitante],
    title: 'Entrar | Barbearia',
    loadComponent: () => import('./paginas/login/login').then((m) => m.Login),
  },
  {
    path: 'cadastro',
    canActivate: [somenteVisitante],
    title: 'Cadastro | Barbearia',
    loadComponent: () => import('./paginas/cadastro/cadastro').then((m) => m.Cadastro),
  },
  {
    path: 'painel',
    canActivate: [exigeLogin],
    title: 'Painel | Barbearia',
    loadComponent: () => import('./paginas/painel/painel').then((m) => m.Painel),
  },
  { path: '**', redirectTo: 'painel' },
];
