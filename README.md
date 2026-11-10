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

*Atualizado em 10/11/2026 · sistema publicado na HostGator · 137 testes na API · 6 testes no painel*

- **Farol da mensalidade no menu (10/11/2026):** logo abaixo de "Minha página", o dono vê "Vence em dd/mm/aaaa" com
  os dias que faltam e uma luz: **verde** com mais de 15 dias, **amarelo** de 15 a 6 dias e **vermelho** com 5 dias ou
  menos (e depois de vencer). Com o menu recolhido, aparece só a luz. Barbearia sem prazo não mostra o farol.
  Cores nos tokens `--farol-*` de `web/src/styles.scss`.
- **Venda do SaaS: administrador e prazo de acesso (10/11/2026):** acabou o "Cadastre sua barbearia". Agora quem
  cadastra é o administrador da plataforma, em **`/painel/admin`** (login próprio, tabela `administradores`):
  - **Nova barbearia:** dados, dono (nome e e-mail), período (**teste de 7 dias, 1, 3, 6 ou 12 meses**), forma de
    pagamento, valor e **contrato** (PDF/foto, guardado em `storage/app/private/contratos`, nunca público). O sistema
    gera uma **senha provisória** do dono, mostrada uma vez, com botão para copiar a mensagem para o WhatsApp.
  - **Detalhe:** renovar (o novo período começa no dia seguinte ao vencimento atual, ou hoje se já venceu),
    suspender/reativar na hora, gerar nova senha do dono e histórico das contratações com download do contrato.
  - **Regra do prazo** (`empresas.acesso_ate` = último dia pago; `config/assinatura.php`): faixa de aviso para o
    dono a partir de 10 dias antes; **5 dias de carência** com faixa vermelha; depois disso o login e a API recusam
    (`codigo: acesso_vencido`), a sessão aberta é encerrada e o login explica o motivo. A página pública continua no ar,
    mas sem agendamento online; o link do cliente confirma e cancela, mas não remarca; lembretes do WhatsApp param.
    **Nenhum dado é apagado:** renovou, volta tudo.
  - **E-mail ao dono** 10, 3 e 1 dia antes, no dia e no 1º dia de carência (`assinaturas:avisos`, diário às 9h).
  - Barbearias que já existiam (o piloto) ficam **sem prazo** até o administrador registrar um plano.
  - **Decisões tomadas sem confirmação do dono do produto** (fáceis de mudar): 5 dias de carência, página pública
    no ar sem agendamento, bloqueio para todos os perfis, teste de 7 dias, valor digitado a cada contratação.
  - **Para usar em produção:** criar o administrador uma vez por SSH:
    `cd /home4/adri0667/saas/barbearia && /opt/cpanel/ea-php83/root/usr/bin/php artisan admin:criar SEU@EMAIL "Seu Nome"` (a senha é
    pedida sem aparecer na tela; pelo Terminal do cPanel ou SSH). O mesmo comando troca a senha de um administrador existente.
- **Login do painel: "olho" e "Esqueci a senha" (10/11/2026, publicado):** botão de olho para ver a senha
  digitada (`web/src/app/layout/ver-senha.ts`, também na tela de nova senha). Link "Esqueci a senha" logo abaixo do
  campo de senha → tela `/esqueci-senha` (informa o e-mail cadastrado) → e-mail com link para
  `/redefinir-senha?token=…&email=…` → nova senha → volta ao login com aviso. A API (`POST /api/auth/esqueci-senha` e
  `/api/auth/redefinir-senha`) avisa "E-mail não encontrado para recuperar a senha" (decisão do produto: revela quem tem
  conta, mitigado pelo limite de 10 pedidos por hora por IP), recusa usuário ou barbearia desativados, pede para
  aguardar 1 min entre pedidos e responde 503 se o envio falhar (erro no `laravel.log`). O link vale 60 min e a troca
  encerra as sessões abertas.
- **E-mail em produção (10/11/2026):** `MAIL_*` e `PAINEL_URL` configurados no `.env` com a conta
  `nao-responda@abianchitech.com`, mas o SMTP recusava o login ("535 Incorrect authentication data") e o Webmail
  avisou que o envio do domínio está bloqueado até reconfigurar o **DKIM** (cPanel › Entregabilidade de e-mail ›
  Reparar DKIM e SPF). **Pendente:** reparar o DKIM, testar envio pelo Webmail e depois o "Esqueci a senha".
- **Página da barbearia, "Sobre a casa" (10/11/2026):** uma foto só, larga e centralizada, com o carimbo giratório
  no canto inferior esquerdo (antes eram duas fotos sobrepostas).

- **Link "Minha página" no menu (09/11/2026):** logo abaixo de Configurações, com ícone de globo, a descrição
  "Agendamento online dos clientes" e a seta de link externo. Abre em nova aba a página pública da barbearia
  (`app.abianchitech.com/saas/barbearia/{slug}`). Se o dono mudar o endereço em Configurações › Barbearia e página,
  o link do menu já acompanha (`AuthService.atualizarEmpresa`).
- **Novo visual do painel (publicado), 09/11/2026:** o painel Angular (`web/`) seguiu o sistema de design
  "Dub" (skill `skill-saas`): fundo branco, estrutura por bordas de 1px, fonte Inter servida pelo próprio painel
  (`web/src/fontes/`), texto em tons de cinza e um único azul de destaque (`#2563eb`). Botão principal preto (um por
  tela), botões secundários com contorno, campos com borda preta de 1px, menu ativo com fundo azul claro, abas em
  pílula, etiquetas de situação tingidas com ponto, aviso flutuante branco. O tema escuro automático foi removido.
  Os tokens ficam no topo de `web/src/styles.scss`; os nomes antigos (`--cor-*`, `--st-*`) apontam para eles.
- **Modo noturno opcional (publicado), 09/11/2026:** botão de lua/sol no rodapé do menu, ao lado de "Sair".
  O padrão continua claro; a escolha fica salva no navegador (`barbearia.tema`, `TemaService`) e um script no
  `web/src/index.html` aplica o tema antes de a página desenhar. A paleta escura é o bloco `:root[data-tema='escuro']`
  em `styles.scss` (mesmos tokens, valores invertidos; botão principal branco, que inverte para fundo preto e texto branco ao passar o mouse).
  **A página pública da barbearia (Blade, visual escuro) e a vitrine não foram alteradas.**

- **LGPD — etapa 1 feita (publicada):** Política de privacidade e Termos de uso, aceite registrado no cadastro
  e no agendamento online (com declaração de maioridade ou responsável), fontes no próprio servidor, mapa do Google só
  com consentimento, aviso de cookies com "Recusar" e rodapé com CNPJ. **Acompanhamento completo e próximos passos em
  [`LGPD.md`](LGPD.md).** Falta informar razão social, endereço, contato e encarregado de dados (`PLATAFORMA_*` no `.env`).

- **09/11/2026:** produção ficou fora do ar (erro 500 no cadastro, no login e nas páginas) porque o MySQL passou a
  recusar a senha do `.env`. Resolvido trocando a senha do usuário `adri0667_abianchi` no cPanel e colocando-a
  entre aspas no `.env`. Veja a nota em [Publicação](#publicação-hostgator).

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
  (redirecionar ou fazer a landing page de vendas). O cadastro aberto foi fechado em 10/11/2026 (área do administrador).
- **Próximo passo:** (1) criar o administrador em produção (`admin:criar`, veja acima) e registrar o plano do piloto;
  (2) trocar a página padrão do Laravel na raiz `/saas/barbearia` por um redirecionamento ou página "em breve";
  (3) começar a medir a taxa de faltas da barbearia piloto.
  Para subir qualquer mudança, basta dar push na `main` (publicação automática, veja abaixo).
- **Guia de primeiros passos (publicado):** guia de primeiros passos da agenda com três botões quadrados
  (serviços, barbeiros, clientes). Some quando os três cadastros existem. Em 09/11/2026 o rótulo passou a
  "PASSO 01/02/03" numa linha só (antes quebrava letra por letra por conflito com a classe `.passo-numero`) e as telas
  de Configurações e Clientes ganharam o botão "← Voltar aos primeiros passos" enquanto o guia estiver incompleto
  (`VoltarPassos`, sinal `guiaPendente` no `AgendaService`). No primeiro acesso do dono abre um popup de boas-vindas
  (`boas-vindas.ts`) com os três passos e o botão "Começar"; depois de fechado não volta (guardado no navegador por usuário).
- **Publicação automática funcionando** (`.github/workflows/publicar.yml`): a cada push na `main`, o GitHub roda os testes
  e envia para a HostGator por SSH/rsync. **Em 09/11/2026:** chave `barbearia-deploy` autorizada (precisou ser
  acrescentada à mão em `~/.ssh/authorized_keys`: o "Autorizar" do cPanel mostrou "authorized" mas não gravou a linha),
  Secrets e `PUBLICAR_AUTOMATICO` cadastrados, testes passando no GitHub (a suíte `Unit` vazia do `phpunit.xml` quebrava
  o PHPUnit lá). A publicação ainda falhava no primeiro SSH; o workflow passou a remover CRLF dos Secrets (chave colada
  pelo navegador vem com quebras do Windows) e ganhou o passo "Testar conexão com a HostGator", que diz o motivo da falha.
  Em 09/11/2026 o secret `SSH_KNOWN_HOSTS` foi trocado pela linha da chave ED25519 do servidor
  (`SHA256:rkkb7e8Vl3tHYC7CyLycOZZB7gcuVOukA/VJ6xKH+Ok`) e a **primeira publicação automática deu certo**.
  Se o passo de conexão falhar com "não conseguiu abrir conexão", é o firewall da HostGator barrando o IP daquela
  máquina do GitHub (costuma acontecer depois de várias falhas de SSH): rodar de novo (Re-run) ou esperar 30–60 min. Detalhes em [Publicação automática](#publicação-automática-github-actions).

---

## O que já foi feito

### Base
- Multiempresa desde o início: todas as tabelas têm `empresa_id`, com filtro automático (veja [Multiempresa](#multiempresa)).
- Login por token, perfis **dono**, **recepção** e **barbeiro**, com permissões por rota.
- **Senha:** botão de olho para ver o que foi digitado e "Esqueci a senha" com link por e-mail (vale 60 min).
- Datas gravadas em UTC e exibidas no fuso da barbearia.
- **Visual do painel** no sistema de design "Dub" (`skill-saas`): tokens de cor, raio, sombra e fonte em
  `web/src/styles.scss`; nenhum valor visual solto nas telas. **Modo noturno** opcional pelo botão de lua/sol no menu.
- Exclusão lógica (soft delete) nos cadastros.

### Venda do SaaS — área do administrador (`/painel/admin`)
- Administradores da plataforma em tabela própria (`administradores`), criados por `php artisan admin:criar`;
  token de administrador não entra no painel das barbearias e vice-versa.
- Cadastro da barbearia com dono (senha provisória), período, pagamento, valor e contrato anexado (disco privado).
- Prazo de acesso (`empresas.acesso_ate`) e histórico em `assinaturas`; renovar, suspender, reativar, nova senha do dono.
- Situações: sem prazo, em dia, vencendo (≤ 10 dias), carência (até 5 dias depois) e bloqueada; suspensa pelo administrador.
- Aviso no painel e por e-mail ao dono; bloqueio do login, da API e do agendamento online após a carência.

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
- **Primeiros passos:** barbearia nova vê três botões (cadastrar serviços, barbeiros e clientes), cada um marcado
  quando feito. Depois dos três, o guia some e esses cadastros ficam só no menu Configurações (`primeiros_passos` em `/api/agenda/dia`).
  No primeiro acesso, o dono vê um popup de boas-vindas com os três passos, que pode ser fechado.
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

### LGPD (detalhes em [`LGPD.md`](LGPD.md))
- Política de privacidade (`/privacidade`) e Termos de uso (`/termos`), com versão.
- Aceites guardados na tabela `aceites` (documento, versão, data, IP e navegador) no agendamento online. O dono aceita
  os Termos pelo contrato assinado (o autocadastro, que registrava o aceite, saiu em 10/11/2026).
- Sem terceiros sem consentimento: fontes locais, mapa do Google só após aceite, aviso de cookies com "Recusar".
- Rodapé com CNPJ e links legais na página da barbearia, no login do painel e na vitrine.

**Testes:** 137 testes na API (`php artisan test`) e 6 no painel (`npx ng test --watch=false`), todos passando.

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
- ✅ **Área do administrador:** cadastro da barbearia com contrato, período pago (teste, 1, 3, 6, 12 meses),
  renovação, suspensão, avisos de vencimento e bloqueio depois da carência (10/11/2026)
- ⬜ Landing page de vendas do SaaS e cobrança recorrente automática por gateway (hoje o pagamento é registrado à mão)
  (cumprindo arrependimento de 7 dias, cancelamento pelo mesmo canal, aviso de renovação e preço total: veja `LGPD.md`)
- ⬜ Tela "Minha senha" no painel (hoje o dono troca a senha provisória só por "Esqueci a senha", que depende do e-mail)
- ⬜ Cota de mensagens do WhatsApp por plano (a contagem mensal já aparece no painel)
- ⬜ Multiunidade (filiais), domínio próprio da barbearia, NFS-e, central de ajuda
- ⬜ Migração para VPS quando o volume exigir (fila com Supervisor, Redis)

### LGPD — etapas 2 e 3
- ⬜ Consentimento de marketing separado, "Meus dados" (acesso e exclusão), limpeza automática pelos prazos de guarda,
  descadastro e plano de incidentes (ANPD em 3 dias úteis). Lista completa em [`LGPD.md`](LGPD.md).

### Pequenos pendentes e ideias
- **E-mail em produção:** reparar o DKIM de `abianchitech.com` (o envio do domínio está bloqueado) para o "Esqueci a senha" enviar de verdade
- Visual "Dub": conferir no navegador o painel de agendamento, a comanda e os relatórios com dados; decidir se a fonte
  Satoshi (títulos ≥ 36px) entra no painel (hoje os títulos usam Inter) e se a vitrine e as telas legais seguem o mesmo visual
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
  - administrador da plataforma: http://localhost:4200/admin com `admin@demo.test` / `demo1234`
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
| `app.abianchitech.com/saas/sistemas` | Vitrine com os SaaS da ABianchi Tech (`vitrine/index.html`, página estática). Para incluir um sistema, editar a lista `SISTEMAS` no arquivo e subir de novo |

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
  - `PAINEL_URL=https://app.abianchitech.com/saas/barbearia/painel` (link do e-mail de "Esqueci a senha");
  - e-mail por uma conta criada no cPanel (ex.: `nao-responda@abianchitech.com`): `MAIL_MAILER=smtp`,
    `MAIL_HOST=mail.abianchitech.com`, `MAIL_PORT=465`, `MAIL_SCHEME=smtps`, `MAIL_USERNAME` e `MAIL_PASSWORD` (entre aspas **simples**: nas duplas, um `$` na senha some e o SMTP responde "535 Incorrect authentication data")
    dessa conta, `MAIL_FROM_ADDRESS` igual ao usuário e `MAIL_FROM_NAME="Barbearia"`;
  - `WHATSAPP_*` (veja `.env.example`);
  - `DB_PASSWORD` **entre aspas** (`DB_PASSWORD="..."`). Em 09/11/2026 o banco recusou o acesso
    (`SQLSTATE[HY000] [1045] Access denied`) e o cadastro, o login e as páginas deram erro 500. Só voltou depois de
    trocar a senha no cPanel e colocá-la entre aspas no `.env`.
  - Se mudar o `.env` e nada mudar, apague `bootstrap/cache/config.php` (equivale a `config:clear`).
- **Painel:** `web/src/environments/environment.ts` aponta para `https://app.abianchitech.com/saas/barbearia/api` e o build de
  produção usa `baseHref` `/saas/barbearia/painel/` (`angular.json`). Compilar com `npx ng build`; o `.htaccess` das rotas vem de `web/public`.
  Esse `.htaccess` também manda o navegador sempre conferir o `index.html` (sem isso, depois de uma atualização o painel
  abria a versão antiga até apertar F5).
- **Depois do upload:** rodar `php artisan migrate --force`, `config:cache` e `route:cache`.
- **Primeiro acesso:** cadastrar a barbearia e o dono pela tela "Cadastro" do painel (não usar `--seed` em produção).
- **HTTPS:** AutoSSL do subdomínio `app`. A Meta exige HTTPS no webhook.
- **Pacotes de publicação:** ficam na pasta `deploy/`, que está no `.gitignore` porque contém o `.env` de produção.

### Publicação automática (GitHub Actions)

O workflow `.github/workflows/publicar.yml` roda a cada push na `main` (ou à mão, em Actions › Publicar na HostGator › Run workflow):

1. **Testes** da API e do painel. Se algum falhar, nada é enviado.
2. Monta o Laravel (`composer install --no-dev`) e o painel (`ng build`) no GitHub.
3. Coloca o sistema em manutenção (`artisan down`) e envia por **rsync** só o que mudou:
   - `api/` → `/home4/adri0667/saas/barbearia` (com `--delete`; nunca toca em `.env`, `storage/`, `bootstrap/cache/` e `public/`);
   - `api/public/` → pasta pública (sem `--delete`, porque o painel fica dentro dela; `index.php`, `.htaccess` e `storage` do servidor ficam intactos);
   - `web/dist/web/browser/` → `.../painel/` (com `--delete`).
4. Roda `config:clear`, `package:discover`, `migrate --force`, `config:cache`, `route:cache` e `view:clear`, tira da manutenção
   (mesmo se algo falhar) e confere `/up`.

**Para ligar (uma vez, depois que a HostGator liberar o SSH):**

1. No PC, criar uma chave só para a publicação: `ssh-keygen -t ed25519 -f barbearia-deploy -N "" -C "github-actions"`.
2. No cPanel › **Acesso SSH** › Gerenciar chaves SSH › **Importar chave**: colar o conteúdo de `barbearia-deploy.pub` e **autorizar** a chave.
3. Testar do PC: `ssh -i barbearia-deploy -p PORTA adri0667@SERVIDOR "which rsync"` (porta e servidor informados pela HostGator).
4. Gerar a impressão digital do servidor: `ssh-keyscan -p PORTA SERVIDOR`.
5. No GitHub › repositório › Settings › Secrets and variables › Actions:
   - **Secrets:** `SSH_HOST` (servidor), `SSH_PORT` (porta), `SSH_USER` (`adri0667`), `SSH_KEY` (conteúdo inteiro de
     `barbearia-deploy`, a chave privada) e `SSH_KNOWN_HOSTS` (saída do `ssh-keyscan`);
   - **Variables:** `PUBLICAR_AUTOMATICO` = `true`.
6. Rodar à mão uma vez (Actions › Publicar na HostGator › Run workflow) e acompanhar. A primeira envia o `vendor` inteiro e demora mais.
7. Apagar os arquivos `barbearia-deploy` do PC depois de guardar a chave no GitHub.

Enquanto `PUBLICAR_AUTOMATICO` não for `true`, o push só roda os testes.

### Publicar uma atualização (manual)

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
