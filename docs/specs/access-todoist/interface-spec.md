# Interface Specification: Acesso e Conta Todoist

**Feature**: `access-todoist`
**Created**: 2026-08-17
**Status**: Draft
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
|---|---|---|---|---|---|
| SURF-WEB-ACCESS | WEB | Visitante, usuÃ¡rio | FULL | SolicitaÃ§Ã£o/verificaÃ§Ã£o passwordless, sessÃ£o e conexÃ£o Todoist | Senha, login social e mÃºltiplas contas Todoist |
| SURF-EMAIL-ACCESS | OTHER | Visitante | FULL | Link mÃ¡gico e PIN de uso Ãºnico | Marketing e preferÃªncias de e-mail |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
|---|---|---|---|
| SURF-WEB-ACCESS | `resources/js/AuthGate.vue`, `/auth/*`, `TodoistSetup.vue` | auditoria de cÃ³digo | Solicita link/PIN, consome token e seleciona projeto; falta gestÃ£o de sessÃµes. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
|---|---|---|---|---|---|
| INT-ACCESS-001 | SURF-WEB-ACCESS | SCREEN | MODIFIED | Acesso passwordless | abertura sem sessÃ£o |
| INT-ACCESS-002 | SURF-WEB-ACCESS | FLOW | MODIFIED | ConexÃ£o e estado Todoist | primeira sessÃ£o e configuraÃ§Ãµes |
| INT-ACCESS-003 | SURF-EMAIL-ACCESS | MESSAGE | MODIFIED | Mensagem de acesso | solicitaÃ§Ã£o vÃ¡lida |

## Interaction Details

### INT-ACCESS-001 â€” Acesso passwordless

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: permitir acesso seguro sem revelar se o e-mail jÃ¡ possui conta.
**Actors and Permissions**: visitante; usuÃ¡rio autenticado pode encerrar somente suas sessÃµes.
**Entry and Navigation**: rota pÃºblica; sucesso abre configuraÃ§Ã£o Todoist ou workspace; logout retorna ao acesso.
**Content and Data**: e-mail, opÃ§Ã£o de lembrar dispositivo, campo de PIN apÃ³s solicitaÃ§Ã£o e instruÃ§Ã£o sobre link.
**Actions and Behavior**: solicitar desafio; verificar link ou PIN; reenviar apÃ³s limite; encerrar sessÃ£o; gerenciar sessÃµes em configuraÃ§Ãµes.
**Validation and Feedback**: e-mail sintaticamente invÃ¡lido bloqueia envio; resposta de solicitaÃ§Ã£o Ã© neutra; link/PIN expirado indica novo acesso; rate limit informa espera.
**Responsive/Adaptive Behavior**: formulÃ¡rio de coluna Ãºnica em telefone; teclado de e-mail/PIN adequado; sem gesto obrigatÃ³rio.
**Accessibility**: labels associados, foco no erro/etapa seguinte, anÃºncio de envio, PIN navegÃ¡vel por teclado e contraste suficiente.
**Localization**: `pt-BR`, e-mail e data formatados pelo locale; textos preparados para i18n.
**Components and Design System**: campos, botÃ£o primÃ¡rio, alerta e toast compartilhados.
**Integration and Contracts**: contratos de sessÃ£o/acesso; nunca mostrar token na UI apÃ³s consumo.
**Telemetry**: solicitaÃ§Ã£o, verificaÃ§Ã£o, expiraÃ§Ã£o e falha por categoria; sem e-mail ou token em telemetria.
**Wireframe Requirement**: N/A
**Wireframe**: N/A â€” alteraÃ§Ã£o de formulÃ¡rio linear sem mudanÃ§a estrutural.

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | e-mail e consentimento de dispositivo | solicitar acesso | processing |
| loading | N/A â€” aÃ§Ã£o local | N/A | N/A |
| empty | N/A â€” formulÃ¡rio possui propÃ³sito | N/A | N/A |
| ready | etapa link/PIN explicada | verificar, reenviar quando permitido | success/error |
| processing | botÃ£o bloqueado e progresso anunciado | cancelar navegaÃ§Ã£o | success/error |
| success | confirmaÃ§Ã£o breve | continuar | setup/workspace |
| validation-error | campo e mensagem associados | corrigir | initial |
| remote-error | mensagem neutra e retry | tentar novamente | processing |
| offline | aviso de indisponibilidade | retentar apÃ³s rede | initial |
| access-denied | N/A â€” visitante pode solicitar desafio | N/A | N/A |
| partial-stale | N/A â€” nÃ£o hÃ¡ leitura parcial | N/A | N/A |

### INT-ACCESS-002 â€” ConexÃ£o e estado Todoist

**Surface**: SURF-WEB-ACCESS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: conectar, selecionar projeto, reconectar, desconectar e explicar impacto.
**Actors and Permissions**: usuÃ¡rio autenticado, restrito Ã  prÃ³pria conta.
**Entry and Navigation**: primeiro acesso, estado desconectado e configuraÃ§Ãµes.
**Content and Data**: estado da integraÃ§Ã£o, conta mascarada quando disponÃ­vel, projeto ativo e aÃ§Ãµes autorizadas.
**Actions and Behavior**: iniciar autorizaÃ§Ã£o; selecionar projeto; reconectar; desconectar com confirmaÃ§Ã£o que descreve arquivamento/indisponibilidade; trocar conta somente apÃ³s confirmaÃ§Ã£o.
**Validation and Feedback**: callback recusado, token revogado e indisponibilidade distinguem retry de reconexÃ£o; nunca expor segredo.
**Responsive/Adaptive Behavior**: lista/projeto em painel ou modal de largura adaptada.
**Accessibility**: diÃ¡logo de confirmaÃ§Ã£o com foco retido, retorno de foco e nomes acessÃ­veis.
**Localization**: nomes externos sÃ£o conteÃºdo; mensagens sÃ£o localizÃ¡veis.
**Components and Design System**: painel, seletor, diÃ¡logo destrutivo e badges de estado.
**Integration and Contracts**: status/projetos/conexÃ£o da conta; callback fora da SPA retorna a estado compreensÃ­vel.
**Telemetry**: inÃ­cio/conclusÃ£o/recusa OAuth, desconexÃ£o e erro categorizados sem token.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-access-002.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | conta nÃ£o conectada | conectar | loading |
| loading | consulta da conta/projetos | aguardar | ready/error |
| empty | conta sem projetos | atualizar/desconectar | loading |
| ready | conta e projeto ativos | selecionar, reconectar, desconectar | processing |
| processing | aÃ§Ã£o bloqueada com progresso | aguardar | success/error |
| success | confirmaÃ§Ã£o e estado atualizado | abrir workspace | ready |
| validation-error | seleÃ§Ã£o invÃ¡lida explicada | corrigir | ready |
| remote-error | erro OAuth/API acionÃ¡vel | tentar novamente | loading |
| offline | integraÃ§Ã£o indisponÃ­vel | retentar | loading |
| access-denied | sessÃ£o expirada | entrar novamente | initial |
| partial-stale | Ãºltimo estado marcado como desatualizado | reconciliar | loading |

### INT-ACCESS-003 â€” Mensagem de acesso

**Surface**: SURF-EMAIL-ACCESS
**Surface Type**: OTHER
**Change Type**: MODIFIED
**Purpose**: fornecer link e PIN de uso Ãºnico sem revelar dados adicionais.
**Actors and Permissions**: destinatÃ¡rio do e-mail.
**Content and Data**: origem identificÃ¡vel, link, PIN, expiraÃ§Ã£o e instruÃ§Ã£o de seguranÃ§a.
**Entry and Navigation**: enviado apÃ³s solicitaÃ§Ã£o; link retorna ao fluxo web de verificaÃ§Ã£o.
**Actions and Behavior**: abrir link ou copiar PIN; informar que solicitaÃ§Ãµes nÃ£o reconhecidas podem ser ignoradas.
**Validation and Feedback**: expiraÃ§Ã£o e consumo Ãºnico sÃ£o informados no destino web, sem revelar dados no e-mail.
**Responsive/Adaptive Behavior**: conteÃºdo legÃ­vel em clientes de e-mail de telefone e desktop.
**Accessibility**: texto alternativo, link descritivo e PIN em texto selecionÃ¡vel.
**Localization**: pt-BR inicial.
**Components and Design System**: template transacional com tipografia e cores do produto.
**Integration and Contracts**: desafio passwordless; nenhum token Ã© persistido ou exibido depois de usado.
**Telemetry**: entrega/falha por categoria; nenhum conteÃºdo do desafio em logs.
**Wireframe Requirement**: N/A
**Wireframe**: N/A â€” mensagem linear.

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | N/A â€” envio Ã© disparado pela web | N/A | N/A |
| loading | N/A â€” cliente de e-mail controla carregamento | N/A | N/A |
| empty | N/A â€” mensagem sempre tem conteÃºdo | N/A | N/A |
| ready | link, PIN e expiraÃ§Ã£o | abrir/copiar | web |
| processing | N/A â€” consumo ocorre na web | N/A | N/A |
| success | N/A â€” sucesso exibido na web | N/A | N/A |
| validation-error | N/A â€” validado na web | N/A | N/A |
| remote-error | N/A â€” falha de entrega Ã© exibida na web | N/A | N/A |
| offline | N/A â€” controlado pelo cliente | N/A | N/A |
| access-denied | N/A â€” desafio nÃ£o autoriza e-mail | N/A | N/A |
| partial-stale | N/A â€” mensagem nÃ£o consulta estado | N/A | N/A |

## Cross-Surface Rules

Link e PIN representam o mesmo desafio; o primeiro consumo vence. E-mail inicia a jornada e web exibe o resultado; nenhuma superfÃ­cie revela existÃªncia de conta.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
|---|---|---|---|---|
| INT-ACCESS-001 | US-1, US-3 | FR-001â€“004, FR-008 | SC-001â€“002 | acesso/sessÃ£o |
| INT-ACCESS-002 | US-2, US-3 | FR-005â€“008 | SC-002â€“003 | integraÃ§Ã£o Todoist |
| INT-ACCESS-003 | US-1 | FR-001â€“002 | SC-001 | acesso |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
|---|---|---|---|
| INT-ACCESS-002 | REQUIRED | wireframes/int-access-002.md | Estado da conta/projeto e confirmaÃ§Ã£o |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: pending artifact
- Accessibility requirements resolved: yes
- Contract mappings verified: pending plan-contract artifact
- Placeholders or open decisions remaining: 0
