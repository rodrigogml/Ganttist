# Interaction Surface Architecture

**Created**: 2026-08-17
**Last Updated**: 2026-08-25
**Status**: Approved
**Sources**: briefing, constitution, specification v1.0 and feature plans.

## Surface Catalog

| Surface ID | Type | Users | Platforms and Form Factors | Product Coverage | Technology, Language and Runtime | Delivery Strategy | Design System | Module/Repository | Decision Status |
|---|---|---|---|---|---|---|---|---|---|
| SURF-WEB-OPERATIONS | WEB | Proprietário, editor e leitor | Chrome/Chromium, Safari e Firefox atuais; desktop, widescreen/4K, tablet e telefone | MVP completo: lista de projetos, workspace local, estrutura, tarefas, pessoas, membros e planejamento | Vue 3, TypeScript, navegador moderno | SPA responsiva, mesma aplicação | Tailwind e componentes próprios | `resources/js`, `resources/css` | Approved |
| SURF-WEB-ACCESS | WEB | Visitante e usuário autenticado | Navegador moderno, desktop/tablet/telefone | MVP | Vue 3, TypeScript, navegador moderno | Fluxo web responsivo | Componentes próprios compartilhados | `resources/js/AuthGate.vue` | Approved |
| SURF-EMAIL-ACCESS | OTHER | Visitante | Cliente de e-mail capaz de abrir link web | MVP | E-mail HTML e link/cÃ³digo de uso Ãºnico | Mensagem transacional | Template de e-mail do produto | `resources/views/emails` | Approved |

## Cross-Surface Decisions

### Capability and Parity Policy

A web operacional cobre o domínio completo do MVP. Desktop prioriza densidade e atalhos; tablet e telefone preservam as mesmas ações de negócio com controles adaptados a toque, sem paridade de layout. E-mail serve exclusivamente para acesso e aceite de convites. Integrações externas futuras não fazem parte da jornada obrigatória.

### Shared Domain and Contracts

Datas de planejamento são civis `YYYY-MM-DD`; APIs usam JSON versionado e a atualização servidor→navegador usa eventos unidirecionais. O core é a autoridade de calendário, precedência, criticidade e reagendamento; o Ganttist é a autoridade de todos os campos locais.

### Shared Code Strategy

Componentes visuais e tipos compartilhados pertencem à SPA. O domínio de scheduling não é compartilhado com o navegador: o cliente envia intenções validadas e recebe projeções autorizadas. Integrações futuras permanecem isoladas por adapters opcionais.

### Accessibility and Input Baseline

Desktop deve suportar teclado; dispositivos touch devem ter alvos adequados e alternativa para gestos imprecisos. Estados de foco, seleÃ§Ã£o, erro, sincronizaÃ§Ã£o e operaÃ§Ãµes destrutivas devem ser perceptÃ­veis e operÃ¡veis sem cor como Ãºnico sinal.

### Localization and Content

Idioma inicial `pt-BR`; conteÃºdo e formataÃ§Ã£o nÃ£o devem ser espalhados de forma que impeÃ§a i18n. Locale e timezone sÃ£o independentes.

## Decision History

| Date | Surface ID | Decision | Rationale | Source |
|---|---|---|---|---|
| 2026-08-17 | SURF-WEB-OPERATIONS | Uma SPA responsiva para a operaÃ§Ã£o | O cliente aprovou uma aplicaÃ§Ã£o Ãºnica com comportamento adaptado | EspecificaÃ§Ã£o Â§33A.5 e Â§18H |
| 2026-08-17 | SURF-TODOIST | Adapter dedicado | Protege a autoridade do domÃ­nio e centraliza falhas externas | ConstituiÃ§Ã£o I e EspecificaÃ§Ã£o Â§33A.22 |
| 2026-08-25 | SURF-WEB-OPERATIONS | Dashboard local de projetos substitui o gate Todoist | Projetos e tarefas são locais e não dependem de conexão externa | Briefing e Constituição v2.0.0 |
