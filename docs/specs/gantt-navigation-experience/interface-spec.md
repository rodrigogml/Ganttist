# Interface Specification: NavegaÃ§Ã£o e ExperiÃªncia Gantt

**Feature**: `gantt-navigation-experience`
**Created**: 2026-08-17
**Status**: Draft
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
|---|---|---|---|---|---|
| SURF-WEB-OPERATIONS | WEB | Planejador | FULL | busca, filtros, navegaÃ§Ã£o temporal, zoom, seleÃ§Ã£o, responsividade e acessibilidade | minimapa e filtros salvos |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
|---|---|---|---|
| SURF-WEB-OPERATIONS | `App.vue`, `workspace.ts` | auditoria | filtros/zoom simples; sem busca, virtualizaÃ§Ã£o ou suporte completo a teclado/touch. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
|---|---|---|---|---|---|
| INT-NAV-001 | SURF-WEB-OPERATIONS | SCREEN | MODIFIED | NavegaÃ§Ã£o de Ã¡rvore e timeline | workspace |
| INT-NAV-002 | SURF-WEB-OPERATIONS | PANEL | NEW | Busca e filtros | barra do sistema/atalho |

## Interaction Details

### INT-NAV-001 â€” NavegaÃ§Ã£o de Ã¡rvore e timeline

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: permitir explorar cronograma grande mantendo linhas, tempo, seleÃ§Ã£o e contexto alinhados.
**Actors and Permissions**: dono do Gantt.
**Entry and Navigation**: workspace; atalhos para hoje, item selecionado e resultado de busca.
**Content and Data**: Ã¡rvore, breadcrumbs, escala diÃ¡ria/semanal/mensal, indicador de hoje, grupos, barras, conectores e contexto de item oculto.
**Actions and Behavior**: expandir/recolher, rolar, ajustar divisor, zoom, ir para hoje/item, selecionar mÃºltiplos e abrir painel.
**Validation and Feedback**: zoom/scroll nÃ£o modifica dados; item fora da janela Ã© focalizado; carga progressiva mantÃ©m feedback de posiÃ§Ã£o.
**Responsive/Adaptive Behavior**: desktop sincroniza painÃ©is e divisor; tablet prioriza timeline; telefone alterna Ã¡rvore/timeline, usa controles de zoom e alternativa a hover/drag.
**Accessibility**: Ã¡rvore com semÃ¢ntica/teclado, foco roving, atalhos documentados, alternativas textuais para conectores, respeito a movimento reduzido e alvos touch.
**Localization**: cabeÃ§alhos temporais e datas no locale.
**Components and Design System**: renderer prÃ³prio virtualizado, Ã¡rvore, toolbar, breadcrumb e seleÃ§Ã£o compartilhada.
**Integration and Contracts**: consome janela/projeÃ§Ã£o do workspace; preferÃªncias visuais nÃ£o alteram regras de negÃ³cio.
**Telemetry**: zoom, navegaÃ§Ã£o, seleÃ§Ã£o e degradaÃ§Ã£o de renderizaÃ§Ã£o; sem dados de conteÃºdo.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-nav-001.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | estrutura reservada | aguardar | loading |
| loading | linhas/timeline skeleton | navegar para trÃ¡s | ready/error |
| empty | projeto sem itens ou filtro vazio | limpar filtro/voltar | ready |
| ready | janela virtualizada interativa | navegar/selecionar/zoom | loading |
| processing | foco/janela em movimento indicado | cancelar foco | ready |
| success | item/foco alcanÃ§ado | continuar | ready |
| validation-error | N/A â€” navegaÃ§Ã£o nÃ£o persiste | N/A | N/A |
| remote-error | falha de carregamento | recarregar | loading |
| offline | Ãºltimo estado marcado | retentar | loading |
| access-denied | sem projeto/sessÃ£o | voltar | initial |
| partial-stale | projeÃ§Ã£o desatualizada | reconciliar | loading |

### INT-NAV-002 â€” Busca e filtros

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: localizar tarefa e reduzir ruÃ­do sem remover contexto, grupos ou relaÃ§Ãµes do modelo.
**Actors and Permissions**: dono do Gantt.
**Entry and Navigation**: botÃ£o/atalho de busca, toolbar e URL/deep link quando aplicÃ¡vel.
**Content and Data**: termo, resultados, filtros ativos, contagens, estado de concluÃ­das e indicador de relaÃ§Ã£o que cruza item oculto.
**Actions and Behavior**: pesquisar, aplicar/remover filtros, focalizar resultado, revelar contexto e inspecionar dependÃªncia oculta.
**Validation and Feedback**: resultado vazio Ã© distinto de erro; filtro nÃ£o altera dados; filtros ativos sempre visÃ­veis e removÃ­veis.
**Responsive/Adaptive Behavior**: popover desktop, painel/folha telefone; atalho substituÃ­do por botÃ£o acessÃ­vel no touch.
**Accessibility**: foco entra no campo, resultados anunciados, navegaÃ§Ã£o por setas/Enter, filtros como controles nomeados.
**Localization**: busca por termos apresentados; textos localizÃ¡veis.
**Components and Design System**: campo de busca, chips, lista de resultados, badge e inspector de relaÃ§Ã£o.
**Integration and Contracts**: consulta/projeÃ§Ã£o paginÃ¡vel; UI nÃ£o omite silenciosamente ponta de dependÃªncia.
**Telemetry**: busca, filtro, resultado, foco e inspeÃ§Ã£o de oculto.
**Wireframe Requirement**: N/A
**Wireframe**: N/A â€” painel complementar da toolbar.

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | campo/filtros fechados | abrir | ready |
| loading | resultados consultados | cancelar | ready/error |
| empty | sem resultados com termo/filtro | limpar/editar | ready |
| ready | resultados e filtros | focalizar/aplicar/remover | loading |
| processing | foco/revelaÃ§Ã£o em curso | aguardar | success |
| success | resultado focalizado | continuar | ready |
| validation-error | N/A â€” texto livre | N/A | N/A |
| remote-error | busca indisponÃ­vel | retry | loading |
| offline | pesquisa local/Ãºltimo estado sinalizado | retentar | loading |
| access-denied | sem workspace | voltar | initial |
| partial-stale | resultados podem estar antigos | reconciliar | loading |

## Cross-Surface Rules

Filtros e preferÃªncias alteram somente visibilidade local. RelaÃ§Ãµes e grupos continuam no modelo; toda ponta oculta precisa de indicaÃ§Ã£o e caminho para inspeÃ§Ã£o.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
|---|---|---|---|---|
| INT-NAV-001 | US-3 | FR-005â€“008 | SC-003 | workspace/preferÃªncias |
| INT-NAV-002 | US-1â€“2 | FR-001â€“004 | SC-001â€“002 | busca/filtros |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
|---|---|---|---|
| INT-NAV-001 | REQUIRED | wireframes/int-nav-001.md | Desktop e adaptaÃ§Ã£o telefone |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: pending contracts stage
- Placeholders or open decisions remaining: 0
