# Conversa de 21/10/2026 — Primeiros passos da agenda e publicação automática

Registro da conversa com o Claude Code para consulta posterior.

---

## 1. Tela inicial da barbearia nova (primeiros passos)

**Pedido:** a primeira tela de uma barbearia recém-cadastrada estava simples demais (uma lista com 3 links).
Trocar por três botões quadrados lado a lado, no tema do sistema: *Cadastre os serviços*, *Cadastre os barbeiros*
e *Cadastre os clientes*. Depois dos três cadastros, o guia não aparece mais; o acesso fica pelo menu Configurações.

**O que foi feito:**
- **API** (`api/app/Http/Controllers/AgendaController.php`): `/api/agenda/dia` passou a devolver
  `primeiros_passos: { servicos, barbeiros, clientes }` (se cada cadastro já existe).
- **Painel** (`web/src/app/paginas/agenda/agenda.ts` e `web/src/agenda.scss`):
  - três cartões quadrados com ícone, "Passo 1/2/3", título e uma linha de explicação;
  - efeito ao passar o mouse; passo concluído fica com ✓ verde e um pouco apagado;
  - o guia aparece enquanto faltar qualquer um dos três cadastros (antes só aparecia sem barbeiro);
    se já há barbeiros, a agenda aparece abaixo do guia;
  - no celular os cartões ficam um embaixo do outro.
- Removido o estilo antigo `.passos li` de `web/src/logado.scss`.
- **Testes:** 2 novos na API (`AgendaDiaTest`), total de 110, todos passando; 4 do painel passando.
- **Commit:** `634162f` — "Primeiros passos da agenda com três botões de cadastro". (Sem push.)

---

## 2. Publicação automática na HostGator a cada push

**Pergunta:** existe forma de o push no Git já atualizar a HostGator?

**Resposta:** sim, com **GitHub Actions**. Foram apresentadas duas opções (FTP ou SSH/rsync).
Escolhida **SSH com rsync** (o SSH precisava ser liberado pela HostGator e já foi).

**O que foi feito** — arquivo `.github/workflows/publicar.yml`:
1. A cada push na `main` (ou à mão em Actions › *Publicar na HostGator* › *Run workflow*) roda os **testes**
   da API e do painel. Se falharem, nada é enviado.
2. Monta o Laravel (`composer install --no-dev --no-scripts`) e o painel (`ng build`) no GitHub.
3. `artisan down` e envio por **rsync**:
   - `api/` → `/home4/adri0667/saas/barbearia` (com `--delete`; nunca toca em `.env`, `storage/`, `bootstrap/cache/`, `public/`);
   - `api/public/` → `/home4/adri0667/app.abianchitech.com/saas/barbearia` (sem `--delete`, pois o painel fica dentro;
     `index.php`, `.htaccess` e `storage` do servidor ficam intactos);
   - `web/dist/web/browser/` → `.../saas/barbearia/painel/` (com `--delete`).
4. `config:clear`, `package:discover`, `migrate --force`, `config:cache`, `route:cache`, `view:clear`, `queue:restart`.
5. `artisan up` sempre (mesmo se algo falhar) e conferência de `https://app.abianchitech.com/saas/barbearia/up`.

**Trava de segurança:** o envio só acontece com a variável do repositório `PUBLICAR_AUTOMATICO = true`.
Sem ela, o push só roda os testes.

O README ganhou a seção **"Publicação automática (GitHub Actions)"** com o passo a passo, e a seção manual
passou a se chamar "Publicar uma atualização (manual)".

**Ainda não commitado:** `.github/workflows/publicar.yml` e as mudanças do README.

---

## 3. Configuração do SSH (em andamento)

- **SSH liberado pela HostGator.** Usuário `adri0667`, porta `2222`, servidor `abianchitech.com` (respondeu, OpenSSH 9.9).
- Gerada uma chave só para a publicação (`barbearia-deploy`, ed25519) numa pasta temporária da sessão.
  **Chave pública** (pode ficar registrada aqui):
  ```
  ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAILMjEO2igAYSQkrPBuIiEKE6doLZRKGuReeidKNwVwtq github-actions-barbearia
  ```
  A chave privada **não** fica no projeto. Se a pasta temporária for apagada antes de ir para o GitHub,
  basta gerar outra: `ssh-keygen -t ed25519 -f barbearia-deploy -N "" -C "github-actions-barbearia"`
  e importar a nova pública no cPanel.

### Próximos passos

1. **cPanel › Acesso SSH › Gerenciar chaves SSH › Importar chave:** nome `barbearia-deploy`, colar a chave pública
   acima (campo da privada vazio). Depois **Gerenciar › Autorizar**.
2. Testar a conexão e conferir no servidor: `rsync` instalado, `/opt/cpanel/ea-php83/root/usr/bin/php` e as pastas
   `/home4/adri0667/saas/barbearia` e `/home4/adri0667/app.abianchitech.com/saas/barbearia`.
3. **GitHub › Settings › Secrets and variables › Actions:**
   - Secrets: `SSH_HOST` = `abianchitech.com`, `SSH_PORT` = `2222`, `SSH_USER` = `adri0667`,
     `SSH_KEY` = conteúdo da chave privada, `SSH_KNOWN_HOSTS` = saída de `ssh-keyscan -p 2222 abianchitech.com`;
   - Variable: `PUBLICAR_AUTOMATICO` = `true`.
4. Commit e push do workflow; rodar a primeira publicação à mão e acompanhar (a primeira envia o `vendor` inteiro).
5. Apagar a chave privada do PC depois de guardada no GitHub.
