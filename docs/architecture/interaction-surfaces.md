# Interaction Surface Architecture

**Created**: 2026-08-17
**Last Updated**: 2026-08-17
**Status**: Approved
**Sources**: briefing, constitution, specification v1.0 and feature plans.

## Surface Catalog

| Surface ID | Type | Users | Platforms and Form Factors | Product Coverage | Technology, Language and Runtime | Delivery Strategy | Design System | Module/Repository | Decision Status |
|---|---|---|---|---|---|---|---|---|---|
| SURF-WEB-OPERATIONS | WEB | UsuÃ¡rio autenticado | Chrome/Chromium, Safari e Firefox atuais; desktop, widescreen/4K, tablet e telefone | MVP completo, com adaptaÃ§Ã£o por viewport | Vue 3, TypeScript, navegador moderno | SPA responsiva, mesma aplicaÃ§Ã£o | Tailwind e componentes prÃ³prios | `resources/js`, `resources/css` | Approved |
| SURF-WEB-ACCESS | WEB | Visitante e usuÃ¡rio autenticado | Navegador moderno, desktop/tablet/telefone | MVP | Vue 3, TypeScript, navegador moderno | Fluxo web responsivo | Componentes prÃ³prios compartilhados | `resources/js/AuthGate.vue`, `resources/js/TodoistSetup.vue` | Approved |
| SURF-EMAIL-ACCESS | OTHER | Visitante | Cliente de e-mail capaz de abrir link web | MVP | E-mail HTML e link/cÃ³digo de uso Ãºnico | Mensagem transacional | Template de e-mail do produto | `resources/views/emails` | Approved |
| SURF-TODOIST | OTHER | Todoist | OAuth, API e webhook HTTPS | MVP | Adapter PHP e contratos HTTP | IntegraÃ§Ã£o assÃ­ncrona/recuperÃ¡vel | N/A | `app/Infrastructure/Todoist`, `app/Contracts` | Approved |

## Cross-Surface Decisions

### Capability and Parity Policy

A web operacional cobre o domÃ­nio completo do MVP. Desktop prioriza densidade e atalhos; tablet e telefone preservam as mesmas aÃ§Ãµes de negÃ³cio com controles adaptados a toque, sem paridade de layout. E-mail serve exclusivamente para acesso. Todoist nÃ£o Ã© uma interface humana do produto.

### Shared Domain and Contracts

Datas de planejamento sÃ£o civis `YYYY-MM-DD`; APIs usam JSON versionado e a atualizaÃ§Ã£o servidorâ†’navegador usa eventos unidirecionais. O core Ã© a autoridade de calendÃ¡rio, precedÃªncia, grupos, criticidade e reagendamento; o Todoist Ã© a autoridade de campos nativos.

### Shared Code Strategy

Componentes visuais e tipos compartilhados pertencem Ã  SPA. O domÃ­nio de scheduling nÃ£o Ã© compartilhado com o navegador: o cliente envia intenÃ§Ãµes validadas e recebe projeÃ§Ãµes autorizadas. O adapter Ã© a Ãºnica fronteira da integraÃ§Ã£o Todoist.

### Accessibility and Input Baseline

Desktop deve suportar teclado; dispositivos touch devem ter alvos adequados e alternativa para gestos imprecisos. Estados de foco, seleÃ§Ã£o, erro, sincronizaÃ§Ã£o e operaÃ§Ãµes destrutivas devem ser perceptÃ­veis e operÃ¡veis sem cor como Ãºnico sinal.

### Localization and Content

Idioma inicial `pt-BR`; conteÃºdo e formataÃ§Ã£o nÃ£o devem ser espalhados de forma que impeÃ§a i18n. Locale e timezone sÃ£o independentes.

## Decision History

| Date | Surface ID | Decision | Rationale | Source |
|---|---|---|---|---|
| 2026-08-17 | SURF-WEB-OPERATIONS | Uma SPA responsiva para a operaÃ§Ã£o | O cliente aprovou uma aplicaÃ§Ã£o Ãºnica com comportamento adaptado | EspecificaÃ§Ã£o Â§33A.5 e Â§18H |
| 2026-08-17 | SURF-TODOIST | Adapter dedicado | Protege a autoridade do domÃ­nio e centraliza falhas externas | ConstituiÃ§Ã£o I e EspecificaÃ§Ã£o Â§33A.22 |
