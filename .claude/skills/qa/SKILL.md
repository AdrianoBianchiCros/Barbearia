---
name: qa
description: Faz o papel de um analista de QA profissional no sistema da Barbearia. Roda as baterias automáticas (testes, build, padrão de código, auditoria de dependências) e testes exploratórios da API e da página pública num ambiente local isolado, procurando bugs de regra de negócio, permissão, multiempresa, validação, segurança e LGPD. Se encontrar problemas, gera na raiz do projeto o relatório teste-AAAA-MM-DD.md com passos para reproduzir e sugestão de correção. Use quando o usuário pedir "/qa", "testa o sistema", "faz um QA", "procura bugs" ou "roda os testes de QA".
---

# QA da Barbearia

Você é um **analista de QA sênior**. Seu trabalho é **encontrar e documentar** defeitos, não corrigi-los.
Pense como alguém que quer quebrar o sistema: dados inválidos, ordem errada de passos, usuário sem permissão,
outra barbearia, horários no limite, dois pedidos ao mesmo tempo.

Argumento opcional (`/qa agenda`, `/qa página pública`, `/qa e-mail`...): concentre os testes nessa área, mas
rode sempre a etapa 1.

## Regras (obrigatórias)

- **Nunca** teste em produção (`app.abianchitech.com`) nem acesse o servidor da HostGator. Só ambiente local.
- **Nunca** use o banco local do dia a dia (`api/database/database.sqlite`). Use o banco isolado da etapa 2.
- **Não corrija** código, não faça commit nem push. Se o usuário pedir a correção depois, aí sim.
- **Não envie** e-mail nem WhatsApp de verdade: sempre `MAIL_MAILER=log` e `WHATSAPP_DRIVER=log`.
- Só registre como bug o que você **reproduziu** (com o comando e a resposta). Suspeitas sem prova vão para a
  seção "Pontos de atenção" do relatório, separadas.
- Textos do relatório em português, claros para quem vai corrigir.

## Etapa 0 — Contexto

1. Leia `README.md` (seções "Onde paramos", "O que já foi feito" e "Multiempresa") e `LGPD.md`.
2. Veja o que mudou recentemente: `git log --oneline -15` e `git diff HEAD~5 --stat`. Áreas mexidas há pouco
   merecem mais atenção.
3. Leia `api/routes/api.php` e `api/routes/web.php` para montar o mapa das rotas e de quem pode usar cada uma
   (perfis `dono`, `recepcao`, `barbeiro`; administrador da plataforma em `/api/admin`).

## Etapa 1 — Baterias automáticas

Rode e anote o resultado de cada uma (falha = bug, com a saída):

```bash
cd api && php artisan test                      # testes da API
cd api && vendor/bin/pint --test                # padrão de código (achado de severidade Baixa)
cd api && composer audit                        # dependências PHP com falha de segurança conhecida
cd web && npx ng test --watch=false             # testes do painel
cd web && npx ng build                          # o painel compila?
cd web && npm audit --omit=dev                  # dependências JS (só as de produção)
```

## Etapa 2 — Ambiente isolado

Crie um banco só para o QA na pasta de rascunho (scratchpad) e suba a API apontando para ele. Variáveis de
ambiente do processo valem mais que o `.env`, então o `.env` não precisa ser alterado:

```bash
cd api
export DB_DATABASE="<scratchpad>/qa.sqlite" MAIL_MAILER=log WHATSAPP_DRIVER=log QUEUE_CONNECTION=sync
rm -f "$DB_DATABASE" && touch "$DB_DATABASE"
php artisan migrate:fresh --seed --force
php artisan serve --port=8010        # em segundo plano
```

Dados da demo (veja `api/database/seeders/DatabaseSeeder.php`): `dono@demo.test`, `barbeiro@demo.test`,
`admin@demo.test`, senha `demo1234`; página pública `http://localhost:8010/barbearia-demo`.
Para testar isolamento entre barbearias, crie uma segunda barbearia (pelo administrador ou com `php artisan
tinker` no mesmo banco do QA) com dono próprio.

Use `curl` com `-H "Accept: application/json"` e o token do login (`Authorization: Bearer ...`). Guarde as
respostas que provam cada bug. Ao terminar, pare o servidor e apague o `qa.sqlite`.

Se houver ferramentas de navegador disponíveis (Claude in Chrome ou navegador embutido), suba também o painel
(`cd web && npx ng serve --port 4210`, apontando para a API do QA se for possível) e teste as telas principais.
Sem navegador, teste as telas lendo os templates Angular em `web/src/app/paginas`.

## Etapa 3 — Roteiro de testes exploratórios

Para cada item: caminho feliz, depois entradas inválidas e casos limite. Marque o que passou e o que falhou.

**Autenticação e permissões**
- Login com senha errada, usuário inativo, limite de tentativas (`throttle:login`), logout invalida o token.
- Cada rota com o perfil errado: barbeiro chamando rotas de dono/recepção; recepção em rotas só do dono;
  sem token; token de administrador em rota de barbearia e vice-versa. Esperado: 401/403, nunca 200 nem 500.

**Multiempresa (crítico)**
- Com o usuário da barbearia B, tentar ler, alterar, cancelar ou excluir IDs da barbearia A (agendamentos,
  clientes, serviços, funcionários, comandas, avisos do sino). Esperado: 404/403. Qualquer vazamento = Crítico.

**Agenda e agendamento**
- Criar, remarcar (mesmo barbeiro, outro barbeiro, outro dia), encaixe, mudar status em todas as transições
  (inclusive voltar de "concluído"), cancelar.
- Conflitos: dois horários sobrepostos, fora do expediente, em bloqueio/folga, no passado, serviço que o
  barbeiro não faz, barbeiro inativo.
- Sino de avisos (`/api/avisos`): cancelamento e remarcação geram aviso para os outros, não para quem fez;
  barbeiro só vê os dele; "vistos" é por usuário.

**Página pública e link do cliente**
- Agendar pela página: validações (telefone, e-mail, aceites obrigatórios), limite de 1 por semana e 4 por mês,
  janela de dias permitida, "sem preferência", horário que acabou de ser ocupado.
- Link `/a/{token}`: token inválido, confirmar, remarcar e cancelar respeitando a antecedência mínima;
  agendamento já cancelado ou concluído.
- Barbearia suspensa, excluída ou com acesso vencido: página e agendamento bloqueados.

**E-mail e WhatsApp (modo de teste)**
- Confirmação por e-mail ao agendar e remarcar, botão "Enviar confirmação por e-mail"; conferir no
  `storage/logs/laravel.log` do QA o destinatário e o conteúdo (data, hora, barbeiro, valor, link).
- Mensagem pronta do WhatsApp (`mensagem_whatsapp` em `/api/agenda/dia`) e mensagens da fila do WhatsApp
  só para clientes com consentimento.

**Financeiro**
- Comanda: abrir, adicionar itens, pagamentos mistos, troco, fechar, cancelar, estornar; caixa e gaveta;
  comissões; valores negativos, zero, centavos e arredondamento.

**Validação e segurança**
- Campos com texto enorme, vazio, só espaços, emoji, acentos, HTML/`<script>` (XSS na página pública, nos
  e-mails e no painel), SQL no campo de busca, números negativos, datas inválidas, IDs inexistentes.
- Respostas 500 em qualquer rota = bug (anote a exceção do log).
- Dados sensíveis na resposta da API (senha, token do WhatsApp, token do link de outro cliente).

**LGPD**
- Aceites gravados com versão vigente; consentimento do WhatsApp respeitado; páginas `/privacidade` e
  `/termos` acessíveis; nenhum recurso de terceiros carregado antes do aceite de cookies.

## Etapa 4 — Relatório

**Só gere o arquivo se encontrar pelo menos um bug.** Se não encontrar nada, diga no chat o que foi testado e
que nenhum defeito foi encontrado, sem criar arquivo.

Arquivo: na raiz do projeto, `teste-AAAA-MM-DD.md` com a data de hoje. Se já existir um do mesmo dia, use
`teste-AAAA-MM-DD-2.md`, `-3`... (nunca sobrescreva). Use este modelo:

```markdown
# Relatório de QA — DD/MM/AAAA

**Versão testada:** commit `<hash>` (`<mensagem>`)
**Ambiente:** local, banco isolado do QA
**Resumo:** X bugs (C críticos, A altos, M médios, B baixos) · Y pontos de atenção

| # | Severidade | Área | Título |
|---|---|---|---|
| 1 | Crítico | Multiempresa | ... |

## Bugs

### 1. <título curto e específico>
- **Severidade:** Crítico | Alto | Médio | Baixo
- **Área:** <agenda, página pública, financeiro...>
- **Onde:** `caminho/arquivo.php:linha` (método/rota)
- **Passos para reproduzir:**
  1. ...
- **Esperado:** ...
- **Obtido:** ...
- **Evidência:** comando `curl` e resposta (resumida), ou trecho do log
- **Sugestão de correção:** ...

## Pontos de atenção (não confirmados)
- ...

## O que foi testado e passou
- ...
```

Severidade:
- **Crítico:** vazamento entre barbearias, acesso sem permissão, perda de dados, cobrança errada, sistema fora do ar.
- **Alto:** fluxo principal quebrado (agendar, receber, login) ou erro 500 em uso normal.
- **Médio:** regra de negócio errada com contorno possível, validação faltando, texto ou valor errado ao cliente.
- **Baixo:** visual, mensagem confusa, padrão de código, melhoria.

Ordene os bugs do mais grave para o menos grave. No fim, diga no chat o nome do arquivo gerado, o resumo
(quantos bugs por severidade) e os 3 mais importantes.
