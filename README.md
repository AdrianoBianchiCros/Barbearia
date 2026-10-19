# Sistema de Gestão para Barbearia

SaaS de agendamento e gestão para barbearias. Planejamento completo em
`Sistema de Gestão para Barbearia - v3.docx`.

| Pasta | Conteúdo |
|---|---|
| `api/` | Backend em Laravel 13 (PHP 8.3+) — API REST, autenticação por token (Sanctum) |
| `web/` | Frontend em Angular 22 — painel administrativo e, futuramente, link público de agendamento |

## Rodando localmente

Pré-requisitos: PHP 8.3+, Composer, Node.js 22+ e npm.

```bash
# API (http://localhost:8000)
cd api
composer install
cp .env.example .env        # na primeira vez
php artisan key:generate    # na primeira vez
php artisan migrate:fresh --seed
php artisan serve

# Frontend (http://localhost:4200), em outro terminal
cd web
npm install
npm start
```

O seeder cria a **Barbearia Demo** com serviços, dois barbeiros e expediente.
O usuário de teste está em `api/database/seeders/DatabaseSeeder.php`.

Em desenvolvimento o banco padrão é SQLite (`api/database/database.sqlite`). Para usar o MySQL
local, ajuste `DB_CONNECTION=mysql` e as variáveis `DB_*` no `api/.env`.

## Testes

```bash
cd api && php artisan test
cd web && npx ng test --watch=false
```

## Multiempresa

Cada barbearia é uma `empresa`. Os models com o trait `PertenceAEmpresa` são filtrados
automaticamente pela barbearia do usuário logado (middleware `empresa`) e recebem o
`empresa_id` ao serem criados. Toda rota da área logada deve usar
`->middleware(['auth:sanctum', 'empresa'])`; para restringir por perfil, use
`->middleware('perfil:dono,recepcao')`.

## Publicação (HostGator)

Ver a seção 4 do documento de planejamento (v4). Resumo:

- `seudominio.com.br` → Laravel (`public_html` recebe o conteúdo de `api/public`): página de cada
  barbearia em `/nome-da-barbearia` e a API em `/api`.
- `app.seudominio.com.br` → painel Angular compilado.
- Fila e lembretes processados pelo cron (PHP 8.3, `ea-php83`).

Ajuste a URL da API em `web/src/environments/environment.ts` e `FRONTEND_URL` no `.env` antes do
build de produção. Rode `php artisan storage:link` para servir logos e fotos enviadas.
