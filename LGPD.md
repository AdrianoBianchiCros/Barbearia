# LGPD e defesa do consumidor — acompanhamento

Lista do que o sistema precisa cumprir para não ter problema com a lei (LGPD, Marco Civil, CDC e regras de
comércio eletrônico), o que já foi feito e o que falta. Pedido feito em 09/11/2026.

> **Importante:** os textos da Política de privacidade e dos Termos de uso foram redigidos pelo Claude como base.
> **Um advogado deve revisar** antes de considerar a versão final.

## Papéis (base para tudo o mais)

- **ABianchi Tech é controladora** dos dados dos donos e da equipe das barbearias (quem assina o sistema).
- **ABianchi Tech é operadora** dos dados dos clientes das barbearias. A **barbearia é a controladora** desses dados.
  Por isso, pedidos dos clientes finais chegam primeiro à barbearia, com a ABianchi dando suporte.

## Dados da empresa (rodapé e documentos)

Ficam no `.env` de produção (`PLATAFORMA_*`, veja `api/.env.example`) e em `api/config/plataforma.php`.
Na vitrine (`vitrine/index.html`) e no painel (`web/src/app/core/plataforma.ts`) estão escritos no próprio arquivo.

| Dado | Valor |
|---|---|
| CNPJ | 54.942.870/0001-09 |
| Razão social | **[falta informar]** |
| Endereço | **[falta informar]** |
| E-mail e telefone de contato | **[falta informar]** |
| Encarregado de dados (DPO): nome e e-mail | **[falta informar]** (pode ser o próprio dono; sugestão de e-mail: `privacidade@abianchitech.com`) |

## Prazos de guarda (definidos na Política de privacidade)

| Dado | Prazo |
|---|---|
| Cadastro do cliente (nome e WhatsApp) | Anonimizado após 2 anos sem atendimento |
| Atendimentos, pagamentos e comissões | 5 anos (legislação fiscal) |
| Histórico de mensagens do WhatsApp | 12 meses |
| Conta da barbearia e da equipe | Enquanto ativa; até 6 meses após o encerramento |
| Registros de aceite | Durante a relação + 5 anos |
| Registros de acesso (IP) | 6 meses (Marco Civil, art. 15) |

A **limpeza automática** desses prazos ainda não existe (etapa 2). Até lá, os prazos estão só no texto.

## Situação

✅ feito · ⏳ parcial · ⬜ a fazer · ➖ não se aplica ainda

### Etapa 1 — feita em 09/11/2026
- ✅ **Política de privacidade** (`/privacidade`) e **Termos de uso** (`/termos`), com versão e data
  (`api/resources/views/legal/`). A versão fica em `config/plataforma.php`; ao mudar o texto, trocar a data.
- ✅ **Aceite registrado** na tabela `aceites` (documento, versão, data e hora, IP, navegador e origem):
  - cadastro do dono no painel: checkbox obrigatório dos Termos e da Privacidade;
  - agendamento online: checkbox obrigatório da Privacidade + **declaração de maioridade ou de responsável legal**
    (dados de criança só com o responsável). Grava de novo só quando a versão do texto muda.
- ✅ **Nada de terceiros sem consentimento:** as fontes passaram a ser servidas pelo próprio sistema
  (`api/public/fonts`, `vitrine/fontes`); o mapa do Google só carrega depois de "Aceitar" ou "Mostrar mapa".
- ✅ **Aviso de cookies com botão Recusar** na página da barbearia (reabre pelo link "Cookies" do rodapé).
- ✅ **Rodapé com CNPJ, endereço, contato e links** na página da barbearia (dados dela + "Agendamento online por
  ABianchi Tech"), nas telas de login e cadastro do painel, nas páginas legais e na vitrine.
- ✅ Nenhum rastreador (analytics, pixel) no sistema. **Regra:** se um dia entrar, só depois do aceite no aviso de cookies.
- Testes: `api/tests/Feature/LgpdTest.php`.

### Etapa 2 — próxima
- ⬜ **Consentimento de marketing separado** (desmarcado por padrão), com registro próprio. Hoje só existe o do WhatsApp
  de lembretes, que é do agendamento.
- ⬜ **"Meus dados":** o titular pede acesso ou exclusão; no painel, o dono exporta ou exclui um cliente e a própria conta.
- ⬜ **Limpeza automática** pelos prazos de guarda (comando agendado que apaga ou anonimiza).
- ⬜ **Página de descadastro** (`/descadastro`) e link de descadastro em todo e-mail (o sistema ainda não envia e-mail;
  no WhatsApp a resposta PARAR já funciona).
- ⬜ **Plano de resposta a incidentes:** o que fazer, quem avisa e o modelo de comunicação à ANPD e aos titulares
  em até **3 dias úteis** (Resolução CD/ANPD nº 15/2024), mais um registro de incidentes.
- ⬜ Pedir novo aceite no painel quando a versão dos Termos ou da Privacidade mudar.

### Etapa 3 — junto com a cobrança (Etapa 5 do documento v4)
- ➖ **Arrependimento em 7 dias** nas contratações online, com devolução integral (CDC, art. 49).
- ➖ **Cancelar pelo mesmo lugar onde assinou**, sem burocracia.
- ➖ **Aviso antes da renovação automática.**
- ➖ **Preço total desde o início**, sem taxa escondida no checkout.

Os Termos de uso já prometem esses quatro pontos (seção 5); a cobrança precisa ser construída cumprindo-os.
