# Sistema de Gestão para Barbearia

SaaS de agendamento e gestão para barbearias. Piloto na própria barbearia; depois, venda por assinatura.
**Especificação vigente:** `Sistema de Gestão para Barbearia - v4.docx`. As etapas do projeto estão na seção 7 desse documento.

> **Para retomar o trabalho:** leia este arquivo inteiro. A seção [Onde paramos](#onde-paramos) diz
> o próximo passo e a seção [O que falta](#o-que-falta) traz a lista completa, na ordem do documento v4.
>
> **Para rodar o sistema na sua máquina:** veja [Como iniciar o projeto](#como-iniciar-o-projeto).

| Pasta | Conteúdo |
|---|---|
| `api/` | Laravel 13 (PHP 8.3+): API REST (Sanctum), página pública da barbearia (Blade), fila e agendador |
| `web/` | Angular 22: painel administrativo (dono, recepção, barbeiro) |


## Como iniciar o projeto

Terminal 1 — API (http://localhost:8000)
cd api
php artisan serve

Terminal 2 — Painel (http://localhost:4200)
cd web
npm start

Para acessar:
- Painel: http://localhost:4200
  - dono: dono@demo.test / demo1234
  - barbeiro: barbeiro@demo.test / demo1234
- Página pública da barbearia: http://localhost:8000/barbearia-demo


---

## Onde paramos

*Atualizado em 21/10/2026 · sistema publicado na HostGator · 108 testes na API*

- **Publicado** em `https://app.abianchitech.com/saas/barbearia` (painel em `/painel`, página de cada barbearia em `/{slug}`).
  O site principal (`abianchitech.com`, `public_html`) não foi alterado. Banco MySQL ligado e cadastro testado.
  Detalhes em [Publicação](#publicação-hostgator).
- **Falta conferir em produção:** permissões (755 pastas, 644 arquivos), cron permanente do `schedule:run`, AutoSSL do
  subdomínio `app`, envio de logo/foto (link `storage`) e um agendamento de ponta a ponta pela página da barbearia.
- **WhatsApp:** está em **modo de teste** (`WHATSAPP_DRIVER=log`), em que as mensagens ficam registradas mas não saem.
  O dono da barbearia está criando a conta na Meta e enviando para aprovação os 2 modelos de mensagem. Os textos
  estão em Configurações › WhatsApp no painel.
- **Quando a Meta aprovar:** informar no painel o **ID do número** e o **token permanente**. No `.env` do servidor,
  colocar `WHATSAPP_APP_SECRET`, trocar para `WHATSAPP_DRIVER=cloud`, rodar `config:clear` e `config:cache` e cadastrar o webhook
  `https://app.abianchitech.com/saas/barbearia/api/webhooks/whatsapp` (campo `messages`) com o `WHATSAPP_VERIFY_TOKEN` do `.env`.
- **Pendentes da publicação:** a raiz `app.abianchitech.com/saas/barbearia` ainda mostra a página padrão do Laravel
  (redirecionar ou fazer a landing page de vendas) e o cadastro pelo painel está aberto a qualquer pessoa.
- **Próximo passo:** (1) trocar a página padrão do Laravel na raiz `/saas/barbearia` por um redirecionamento
  ou página "em breve"; (2) fechar o cadastro aberto; (3) começar a medir a taxa de faltas da barbearia piloto.
  Para subir qualquer mudança, siga [Publicar uma atualização](#publicar-uma-atualização).

---

## O que já foi feito

### Base
- Multiempresa desde o início: todas as tabelas têm `empresa_id`, com filtro automático (veja [Multiempresa](#multiempresa)).
- Login por token, perfis **dono**, **recepção** e **barbeiro**, com permissões por rota.
- Datas gravadas em UTC e exibidas no fuso da barbearia.
- Exclusão lógica (soft delete) nos cadastros.

### Cadastros (Configurações)
- **Serviços:** preço e duração.
- **Barbeiros:**
  - serviços que realiza, com preço e duração próprios se quiser;
  - expediente semanal, foto e especialidades;
  - comissão e tipo de vínculo (CLT, parceiro, autônomo).
- **Clientes:** busca, consentimento de WhatsApp registrado (LGPD) e telefone único por barbearia.
- **Bloqueios de agenda:** folgas, férias e feriados.
- **Produtos e estoque:**
  - compras com fornecedor, nota, lote e validade;
  - edição de compras e aviso do próximo vencimento.
- **Barbearia e página:** dados, endereço (preenchido pelo CEP), redes sociais, cor, logo, capa e **galeria de fotos** (até 12).

### Agenda
- Agenda do dia em colunas por barbeiro (tela inicial), com linha da hora atual e cores por situação.
- Agendar e remarcar só em horários livres (serviço `Disponibilidade`), com proteção contra dois agendamentos no mesmo horário.
- **Encaixe:** coloca o cliente no próximo horário vago, ignorando o expediente, mas nunca por cima de outro cliente.
- Busca de cliente agendado e status: agendado, confirmado, em atendimento, concluído, cancelado e faltou.
- Valor previsto do dia oculto por padrão, porque a tela fica à vista dos clientes.

### Financeiro
- **Comanda por atendimento:** serviços, produtos, desconto, várias formas de pagamento, comissão e baixa de estoque.
- **Caixa:**
  - abertura, suprimento, sangria e fechamento com diferença;
  - limite de dinheiro na gaveta;
  - histórico.
- **Comissões por período:** comissão menos vales e repasses. O barbeiro vê só a dele.
- **Despesas:** contas a pagar com vencimento e atraso.
- **Relatórios (só o dono):**
  - faturamento, ticket médio, taxa de faltas e ocupação por barbeiro;
  - mais vendidos e horários de pico;
  - clientes sumidos, com atalho para chamar no WhatsApp.

### Página pública da barbearia — `/{slug}` (Blade, gerada no servidor)
- **Visual:** escuro/premium com animações:
  - topo com foto em zoom e poste de barbeiro;
  - faixa rolante com os serviços;
  - cardápio de serviços, equipe e galeria com ampliação;
  - mapa, botão de WhatsApp e botão fixo "Agendar" no celular.
- **Fotos:** enquanto a barbearia não envia as próprias, usa fotos ilustrativas do Unsplash
  (`api/public/img/pagina/CREDITOS.txt`).
- **Agendamento online:** serviço(s) → barbeiro ou "sem preferência" → dia e horário → nome e WhatsApp → confirmação.
  Entra na agenda com origem `link_publico`.
- **Proteções:**
  - até 60 dias à frente;
  - **1 horário por semana e 4 por mês por WhatsApp** (`LimiteAgendamentoCliente`); cancelado não conta e a recepção não tem limite;
  - 10 agendamentos por hora por IP;
  - endereços reservados (`api`, `a`, `login`…).
- Meta tags Open Graph para a prévia do link no WhatsApp e no Instagram.

### WhatsApp (em modo de teste)
- **Confirmação** ao marcar ou remarcar e **lembretes** 24 h e 2 h antes (comando `whatsapp:lembretes`, a cada 5 min).
  Só para clientes com consentimento, e cada mensagem sai uma única vez.
- **Envio:** pela fila, com novas tentativas. `EnviadorTeste` (modo de teste) ou `EnviadorCloudApi` (envio real, Cloud API).
- **Webhook `/api/webhooks/whatsapp`:**
  - verificação da Meta e assinatura `X-Hub-Signature-256`;
  - status de entrega (só avança) e eventos repetidos ignorados;
  - resposta **SIM** confirma o horário e **PARAR** registra que o cliente não quer mais mensagens.
- **Link do cliente `/a/{token}`:**
  - confirmar presença;
  - remarcar com o mesmo barbeiro e serviços;
  - cancelar, respeitando a antecedência mínima (padrão 2 h) e o limite semanal/mensal.
- **Painel, Configurações › WhatsApp:**
  - liga o envio e os lembretes;
  - conexão com a Meta (o token fica criptografado e nunca volta para o painel);
  - modelos para aprovar e histórico das mensagens.

**Testes:** 108 testes na API (`php artisan test`), todos passando.

---

## O que falta

Na ordem das etapas do documento v4 (seção 7). ✅ = feito · ⏳ = em andamento · ⬜ = a fazer.

### Etapa 1 — MVP (piloto na própria barbearia)
- ✅ Base multiempresa, login, cadastros, agenda com disponibilidade e bloqueios
- ✅ Página da barbearia com agendamento online
- ⏳ **WhatsApp real:** código pronto, falta a conta da Meta, os modelos aprovados e a publicação com HTTPS
- ✅ **Publicação na HostGator** em `app.abianchitech.com/saas/barbearia` (veja [Publicação](#publicação-hostgator))
- ⬜ Medir a taxa de faltas antes e depois (é o principal argumento de venda)

### Etapa 2 — Operação
- ✅ Comanda, pagamentos (dinheiro, cartão, Pix manual), caixa, comissões, encaixe
- ⬜ **Lista de espera:** avisar automaticamente o próximo da fila quando um horário for liberado (usa o WhatsApp)
- ⬜ **Fila de chegada:** atendimento sem hora marcada, integrado à agenda
- ⬜ **Política de cancelamento com sinal via Pix** opcional, por gateway (Asaas, Mercado Pago ou Pagar.me)
- ⬜ Pix por QR Code integrado ao gateway (hoje o Pix é só registrado como forma de pagamento)
- ⬜ Contas a receber (hoje só existem contas a pagar)

### Etapa 3 — Fidelização
- ⬜ Mensagens de retorno ("faz 30 dias do seu último corte") e de aniversário
- ⬜ Avaliação pós-atendimento, convite para avaliar no Google e avaliações na página
- ⬜ Programa de fidelidade (10º corte grátis ou pontos)
- ⬜ Clube de assinatura do cliente (planos, cobrança recorrente, controle de uso)
- ⬜ Preferências do cliente (tipo de corte, máquina, alergias), em parte já existe no campo de observações
- ⬜ LGPD: exportar e excluir os dados do cliente quando ele pedir
- ⬜ Visão do barbeiro no celular: agenda do dia, comissões e histórico do cliente que vai atender

### Etapa 4 — Validação externa (beta)
- ⬜ Importação de clientes por planilha (Excel/CSV)
- ⬜ Onboarding guiado (configuração em poucos minutos)
- ⬜ 3 a 5 barbearias parceiras

### Etapa 5 — SaaS comercial
- ⬜ Landing page de vendas do SaaS, cadastro self-service, planos e cobrança recorrente
- ⬜ Cota de mensagens do WhatsApp por plano (a contagem mensal já aparece no painel)
- ⬜ Multiunidade (filiais), domínio próprio da barbearia, NFS-e, central de ajuda
- ⬜ Migração para VPS quando o volume exigir (fila com Supervisor, Redis)

### Pequenos pendentes e ideias
- Textos de chamada da página editáveis pelo painel ("Mais que um corte…", "Mãos de mestre")
- Limites do agendamento online (1 por semana e 4 por mês) configuráveis pelo painel; hoje são constantes em `LimiteAgendamentoCliente`
- Antes de a conta da Meta ficar pronta: botão na agenda para enviar a confirmação manualmente por `wa.me`
- Avisar a recepção quando o cliente cancelar ou remarcar pelo link
- Laravel Boost: `api/CLAUDE.md` pede para instalar, mas não foi instalado (seria uma dependência nova)

---

## Como iniciar o projeto

Pré-requisitos: PHP 8.3+, Composer, Node.js 22+ e npm.

### No dia a dia (já instalado)

Abra dois terminais na pasta do projeto:

```powershell
# Terminal 1: API e página pública (http://localhost:8000)
cd api
php artisan serve

# Terminal 2: painel (http://localhost:4200)
cd web
npm start
```

Opcional, em um 3º terminal: `cd api; php artisan schedule:work` roda o agendador (lembretes do WhatsApp e fila).
Em modo de teste as mensagens só ficam registradas e não são enviadas.

### Primeira vez (máquina nova ou clone novo)

Faça uma vez antes do passo anterior:

```powershell
# API
cd api
composer install                  # no Windows: composer.ps1 install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed  # cria o banco SQLite com os dados de demonstração
php artisan storage:link          # logos e fotos enviadas

# Painel
cd ../web
npm install
```

### Acessos

- **Painel:** http://localhost:4200
  - dono: `dono@demo.test` / `demo1234`
  - barbeiro: `barbeiro@demo.test` / `demo1234`
  - os dados estão em `api/database/seeders/DatabaseSeeder.php`
- **Página pública da demo:** http://localhost:8000/barbearia-demo

### Outros comandos úteis

- **Recomeçar o banco com os dados de demonstração:** `cd api; php artisan migrate:fresh --seed`. Isso apaga tudo o que está no banco local.
- **Processar só a fila:** `php artisan queue:work`. Nos testes a fila é síncrona.
- **Banco:** em desenvolvimento é SQLite (`api/database/database.sqlite`). Para usar MySQL, ajuste `DB_*` no `api/.env`.

### Observações do ambiente (Windows)
- `composer.bat` e `vendor/bin/pint` dão "Acesso negado". Use `composer.ps1` e `php vendor/bin/pint`.
- Não salvar `.php` com `Set-Content -Encoding utf8` (PowerShell 5.1): ele grava BOM e quebra o PHP.

## Testes

```bash
cd api && php artisan test
cd web && npx ng test --watch=false
```

## Multiempresa

Cada barbearia é uma `empresa`. Os models com o trait `PertenceAEmpresa` são filtrados automaticamente pela
barbearia definida em `EmpresaAtual` e recebem o `empresa_id` ao serem criados. Quem define a barbearia:

| Middleware | Onde | Como |
|---|---|---|
| `empresa` | área logada (`auth:sanctum`) | pelo usuário logado |
| `empresa.publica` | página `/{slug}` e `/api/publico/{slug}/…` | pelo endereço da barbearia |
| `agendamento.token` | link do cliente `/a/{token}` e `/api/a/{token}/…` | pelo código do agendamento |
| — | jobs, comando `whatsapp:lembretes` e webhook | definem manualmente com `EmpresaAtual::definir()` |

Para restringir uma rota por perfil, use `->middleware('perfil:dono,recepcao')`.

## Publicação (HostGator)

Detalhes na seção 4 do documento v4. O site principal (`abianchitech.com`, pasta `public_html`) **não é alterado**:
o sistema fica todo no subdomínio `app`, em `app.abianchitech.com/saas/barbearia`.

| Endereço | O que é |
|---|---|
| `app.abianchitech.com/saas/barbearia/painel` | Painel Angular (dono, recepção, barbeiro) |
| `app.abianchitech.com/saas/barbearia/{slug}` | Página de cada barbearia com agendamento online |
| `app.abianchitech.com/saas/barbearia/a/{token}` | Link do cliente |
| `app.abianchitech.com/saas/barbearia/api` | API |

| Pasta no servidor (`/home4/adri0667`) | Conteúdo |
|---|---|
| `saas/barbearia` | Laravel (código, `vendor`, `.env`), fora da pasta pública |
| `app.abianchitech.com/saas/barbearia` | Conteúdo de `api/public` + link `storage` |
| `app.abianchitech.com/saas/barbearia/painel` | Painel compilado (`web/dist/web/browser`) |

- **Banco MySQL:** `adri0667_barbearia`, usuário `adri0667_abianchi`.
- **`index.php` público:** os caminhos apontam para `__DIR__.'/../../../saas/barbearia/'` em vez de `__DIR__.'/../'`.
  O `.htaccess` público precisa de `RewriteBase /saas/barbearia/`.
- **PHP 8.3:** escolhido no MultiPHP Manager **só para o subdomínio `app.abianchitech.com`** (o site principal não muda).
  Não usar `AddHandler` no `.htaccess`: na HostGator ele fez todo `.php` da pasta responder 404.
- **Permissões:** 755 nas pastas e 644 nos arquivos (o Extract do cPanel deixou 777/666).
- **Fotos enviadas:** link `app.abianchitech.com/saas/barbearia/storage` → `/home4/adri0667/saas/barbearia/storage/app/public`
  (o `php artisan storage:link` não serve nessa estrutura).
- **Cron do cPanel, a cada minuto:** `/opt/cpanel/ea-php83/root/usr/bin/php /home4/adri0667/saas/barbearia/artisan schedule:run`.
  Ele roda os lembretes a cada 5 min e processa a fila (`queue:work --stop-when-empty`).
- **`.env` de produção** (em `/home4/adri0667/saas/barbearia`):
  - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://app.abianchitech.com/saas/barbearia` e `DB_*` do MySQL;
  - `FRONTEND_URL=https://app.abianchitech.com` e `QUEUE_CONNECTION=database`;
  - `WHATSAPP_*` (veja `.env.example`).
- **Painel:** `web/src/environments/environment.ts` aponta para `https://app.abianchitech.com/saas/barbearia/api` e o build de
  produção usa `baseHref` `/saas/barbearia/painel/` (`angular.json`). Compilar com `npx ng build`; o `.htaccess` das rotas vem de `web/public`.
- **Depois do upload:** rodar `php artisan migrate --force`, `config:cache` e `route:cache`.
- **Primeiro acesso:** cadastrar a barbearia e o dono pela tela "Cadastro" do painel (não usar `--seed` em produção).
- **HTTPS:** AutoSSL do subdomínio `app`. A Meta exige HTTPS no webhook.
- **Pacotes de publicação:** ficam na pasta `deploy/`, que está no `.gitignore` porque contém o `.env` de produção.

### Publicar uma atualização

Sem SSH: os pacotes são montados no PC e enviados pelo Gerenciador de Arquivos do cPanel.

1. **Sistema (Laravel):** copiar `api/` para `deploy/Barbearia/barbearia` (sem `.env`, `database.sqlite`, `storage/logs`,
   `storage/app/public/*` e `tests/`), rodar `composer.ps1 install --no-dev --optimize-autoloader` nessa cópia e compactar
   com `tar -a -c -f barbearia-sistema.zip barbearia` (o `Compress-Archive` do PowerShell 5.1 grava `\` nos caminhos e quebra no Linux).
   No servidor, extrair em `/home4/adri0667/saas/` por cima. O `.env` do servidor não é tocado.
2. **Painel:** `cd web; npx ng build` e enviar o conteúdo de `web/dist/web/browser` para
   `/home4/adri0667/app.abianchitech.com/saas/barbearia/painel/`.
3. **Público (`api/public`):** só se mudou algo em `api/public`. Usar `deploy/Barbearia/app-saas-barbearia`, que já tem o
   `index.php` e o `.htaccess` ajustados para o servidor; não copiar os de `api/public` por cima deles.
4. **Migrations e cache:** criar no Cron Jobs uma tarefa "Uma vez por minuto", esperar 2 minutos e **excluí-la**:
   ```
   cd /home4/adri0667/saas/barbearia && /opt/cpanel/ea-php83/root/usr/bin/php artisan config:clear > deploy.log 2>&1 ; /opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force >> deploy.log 2>&1 ; /opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache >> deploy.log 2>&1 ; /opt/cpanel/ea-php83/root/usr/bin/php artisan route:cache >> deploy.log 2>&1 ; /opt/cpanel/ea-php83/root/usr/bin/php artisan view:clear >> deploy.log 2>&1
   ```
   O `deploy.log` é sobrescrito a cada minuto em que a tarefa roda; erros ficam em `storage/logs/laravel-AAAA-MM-DD.log`.
5. **Conferir:** `https://app.abianchitech.com/saas/barbearia/up` deve mostrar "Application up".

Depois de mudar o `.env` no servidor, sempre rodar `config:clear` e `config:cache`: com o cache ligado, o Laravel ignora o `.env`.
