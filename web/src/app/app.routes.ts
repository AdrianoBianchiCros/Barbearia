import { inject } from '@angular/core';
import { Routes } from '@angular/router';
import { AuthService } from './core/auth.service';
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
      { path: '', pathMatch: 'full', redirectTo: 'agenda' },
      {
        path: 'agenda',
        title: 'Agenda do dia | Barbearia',
        loadComponent: () => import('./paginas/agenda/agenda').then((m) => m.Agenda),
      },
      {
        path: 'clientes',
        title: 'Clientes | Barbearia',
        loadComponent: () => import('./paginas/clientes/clientes').then((m) => m.Clientes),
      },
      {
        path: 'financeiro',
        loadComponent: () => import('./paginas/financeiro/financeiro').then((m) => m.Financeiro),
        children: [
          {
            path: '',
            pathMatch: 'full',
            redirectTo: () => (inject(AuthService).sessao()?.usuario.perfil === 'barbeiro' ? 'comissoes' : 'caixa'),
          },
          {
            path: 'caixa',
            canActivate: [exigePerfil('dono', 'recepcao')],
            title: 'Caixa | Financeiro',
            loadComponent: () => import('./paginas/financeiro/caixa').then((m) => m.Caixa),
          },
          {
            path: 'comissoes',
            title: 'Comissões | Financeiro',
            loadComponent: () => import('./paginas/financeiro/comissoes').then((m) => m.Comissoes),
          },
          {
            path: 'despesas',
            canActivate: [exigePerfil('dono')],
            title: 'Despesas | Financeiro',
            loadComponent: () => import('./paginas/financeiro/despesas').then((m) => m.Despesas),
          },
        ],
      },
      {
        path: 'configuracoes',
        loadComponent: () => import('./paginas/configuracoes/configuracoes').then((m) => m.Configuracoes),
        children: [
          { path: '', pathMatch: 'full', redirectTo: 'servicos' },
          {
            path: 'servicos',
            title: 'Serviços | Configurações',
            loadComponent: () => import('./paginas/configuracoes/servicos/servicos').then((m) => m.Servicos),
          },
          {
            path: 'barbeiros',
            title: 'Barbeiros | Configurações',
            loadComponent: () => import('./paginas/configuracoes/barbeiros/barbeiros').then((m) => m.Barbeiros),
          },
          {
            path: 'barbeiros/:id',
            title: 'Barbeiro | Configurações',
            loadComponent: () => import('./paginas/configuracoes/barbeiros/barbeiro').then((m) => m.Barbeiro),
          },
          {
            path: 'produtos',
            title: 'Produtos | Configurações',
            loadComponent: () => import('./paginas/configuracoes/produtos/produtos').then((m) => m.Produtos),
          },
          {
            path: 'barbearia',
            canActivate: [exigePerfil('dono')],
            title: 'Barbearia e página | Configurações',
            loadComponent: () => import('./paginas/configuracoes/barbearia').then((m) => m.DadosBarbearia),
          },
        ],
      },
      // Endereços antigos
      { path: 'painel', redirectTo: 'agenda' },
      { path: 'servicos', redirectTo: 'configuracoes/servicos' },
      { path: 'barbeiros', redirectTo: 'configuracoes/barbeiros' },
    ],
  },
  { path: '**', redirectTo: 'agenda' },
];
