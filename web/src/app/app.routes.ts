import { Routes } from '@angular/router';
import { exigeLogin, exigePerfil, somenteVisitante } from './core/auth.guards';

export const routes: Routes = [
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
    path: '',
    canActivate: [exigeLogin],
    loadComponent: () => import('./layout/shell').then((m) => m.Shell),
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'painel' },
      {
        path: 'painel',
        title: 'Início | Barbearia',
        loadComponent: () => import('./paginas/painel/painel').then((m) => m.Painel),
      },
      {
        path: 'servicos',
        title: 'Serviços | Barbearia',
        loadComponent: () => import('./paginas/servicos/servicos').then((m) => m.Servicos),
      },
      {
        path: 'barbeiros',
        title: 'Barbeiros | Barbearia',
        loadComponent: () => import('./paginas/barbeiros/barbeiros').then((m) => m.Barbeiros),
      },
      {
        path: 'barbeiros/:id',
        title: 'Barbeiro | Barbearia',
        loadComponent: () => import('./paginas/barbeiros/barbeiro').then((m) => m.Barbeiro),
      },
      {
        path: 'clientes',
        title: 'Clientes | Barbearia',
        loadComponent: () => import('./paginas/clientes/clientes').then((m) => m.Clientes),
      },
      {
        path: 'configuracoes',
        canActivate: [exigePerfil('dono')],
        title: 'Barbearia e página | Barbearia',
        loadComponent: () => import('./paginas/configuracoes/configuracoes').then((m) => m.Configuracoes),
      },
    ],
  },
  { path: '**', redirectTo: 'painel' },
];
