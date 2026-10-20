# Sistema de Gestão para Barbearia

SaaS de agendamento e gestão para barbearias. Piloto na própria barbearia; depois, venda por assinatura.
**Especificação vigente:** `Sistema de Gestão para Barbearia - v4.docx`. As etapas do projeto estão na seção 7 desse documento.

> **Para retomar o trabalho:** leia este arquivo inteiro. A seção [Onde paramos](#onde-paramos) diz
> o próximo passo e a seção [O que falta](#o-que-falta) traz a lista completa, na ordem do documento v4.

| Pasta | Conteúdo |
|---|---|
| `api/` | Laravel 13 (PHP 8.3+): API REST (Sanctum), página pública da barbearia (Blade), fila e agendador |
| `web/` | Angular 22: painel administrativo (dono, recepção, barbeiro) |

---

## Onde paramos

*Atualizado em 19/10/2026 · último commit: limite de 1 horário por semana e 4 por mês*

- **Etapa 1 (MVP):** faltam a **publicação na HostGator** e **ligar o WhatsApp real**. Todo o resto está pronto.
- **WhatsApp:** está em **modo de teste** (`WHATSAPP_DRIVER=log`), em que as mensagens ficam registradas mas não saem.
  O dono da barbearia está criando a conta na Meta e enviando para aprovação os 2 modelos de mensagem. Os textos
  estão em Configurações › WhatsApp no painel.
- **Próximo passo: publicar na HostGator.** Antes, o dono precisa conferir no cPanel:
  - o **acesso SSH**;
  - o **intervalo mínimo do cron**;
  - o **domínio** que será usado.

  O webhook da Meta e o link do cliente só funcionam com o sistema no ar com HTTPS.
- **Depois de publicar:** quando a Meta aprovar, informar no painel o **ID do número** e o **token permanente**. No `.env`,
  colocar `WHATSAPP_APP_SECRET` e `WHATSAPP_VERIFY_TOKEN`, trocar para `WHATSAPP_DRIVER=cloud` e cadastrar o webhook
  `https://DOMINIO/api/webhooks/whatsapp` (campo `messages`).

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
- ⬜ **Publicação na HostGator** (passo a passo na seção 4 do v4 e em [Publicação](#publicação-hostgator))
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

## Rodando localmente

Pré-requisitos: PHP 8.3+, Composer, Node.js 22+ e npm.

```bash
# API (http://localhost:8000)
cd api
composer install
cp .env.example .env        # na primeira vez
php artisan key:generate    # na primeira vez
php artisan migrate:fresh --seed
php artisan storage:link    # na primeira vez (logos e fotos enviadas)
php artisan serve

# Painel (http://localhost:4200), em outro terminal
cd web
npm install
npm start
```

- **Página pública da demo:** http://localhost:8000/barbearia-demo
- **Login do painel:** `dono@demo.test` / `demo1234` (dono) ou `barbeiro@demo.test` / `demo1234`. Veja `api/database/seeders/DatabaseSeeder.php`.
- **Fila e lembretes em ambiente local:** `php artisan schedule:work` (roda o agendador) ou `php artisan queue:work`.
  Nos testes a fila é síncrona.
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

Detalhes na seção 4 do documento v4. Resumo:

- **`seudominio.com.br`** → Laravel. O `public_html` recebe o conteúdo de `api/public` e o resto do Laravel
  fica em `/home/USUARIO/api`. Atende a página de cada barbearia (`/nome-da-barbearia`), o link do cliente (`/a/…`) e a API (`/api`).
- **`app.seudominio.com.br`** → painel Angular compilado (`ng build`), com `.htaccess` que manda as rotas para o `index.html`.
- **Cron do cPanel, a cada minuto:** `php /home/USUARIO/api/artisan schedule:run`, com o PHP 8.3 (`ea-php83`).
  Ele roda os lembretes a cada 5 min e processa a fila (`queue:work --stop-when-empty`).
- **`.env` de produção:**
  - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` e `DB_*` do MySQL;
  - `QUEUE_CONNECTION=database`;
  - `WHATSAPP_*` (veja `.env.example`).
- **Antes do build do painel:** ajustar `web/src/environments/environment.ts` (URL da API) e `FRONTEND_URL`.
- **Depois do upload:** rodar `php artisan migrate --force`, `config:cache`, `route:cache` e `storage:link`.
- **Domínio e painel com HTTPS:** ativar o AutoSSL. A Meta exige HTTPS no webhook.
