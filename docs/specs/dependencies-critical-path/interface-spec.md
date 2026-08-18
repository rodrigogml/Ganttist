# Interface Specification: DependÃªncias e Caminho CrÃ­tico

**Feature**: `dependencies-critical-path`
**Created**: 2026-08-17
**Status**: Draft
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
|---|---|---|---|---|---|
| SURF-WEB-OPERATIONS | WEB | Planejador | FULL | criaÃ§Ã£o, inspeÃ§Ã£o, alteraÃ§Ã£o/remoÃ§Ã£o e criticidade | lag/lead, relaÃ§Ãµes entre projetos e grupo sucessor |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
|---|---|---|---|
| SURF-WEB-OPERATIONS | `App.vue`, `DependencyController` | auditoria | seletor e SVG bÃ¡sicos; validaÃ§Ã£o/projeÃ§Ã£o de criticidade incompletas. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
|---|---|---|---|---|---|
| INT-DEPS-001 | SURF-WEB-OPERATIONS | FLOW | MODIFIED | Criar e inspecionar dependÃªncia | barra/painel de tarefa |
| INT-DEPS-002 | SURF-WEB-OPERATIONS | VISUALIZATION | MODIFIED | Criticidade e relaÃ§Ãµes ocultas | workspace/timeline |

## Interaction Details

### INT-DEPS-001 â€” Criar e inspecionar dependÃªncia

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: permitir definir precedÃªncia vÃ¡lida e compreender seu impacto antes da persistÃªncia.
**Actors and Permissions**: dono do Gantt.
**Entry and Navigation**: ponto de conexÃ£o da barra, painel de tarefa ou aÃ§Ã£o de seleÃ§Ã£o; retorno ao item selecionado.
**Content and Data**: predecessor/sucessor, tipo FS/SS/FF/SF, resumo de impacto, estado da relaÃ§Ã£o e aviso de grupo.
**Actions and Behavior**: escolher pontas/tipo, prÃ©-validar, confirmar, alterar tipo ou remover apÃ³s confirmaÃ§Ã£o quando houver impacto; grupo somente pode ser origem.
**Validation and Feedback**: bloquear mesma tarefa, duplicata, ciclo, item de outro Gantt, grupo sucessor e relaÃ§Ã£o invÃ¡lida; explicar motivo e preservar seleÃ§Ã£o.
**Responsive/Adaptive Behavior**: arraste no desktop quando preciso; painel guiado e alternativa de seleÃ§Ã£o no touch/teclado.
**Accessibility**: alternativa completa ao arraste, lista de opÃ§Ãµes rotulada, mensagens de validaÃ§Ã£o anunciadas e foco na falha.
**Localization**: tipos usam rÃ³tulo expandido e sigla consistente; datas no locale.
**Components and Design System**: conectores SVG, seletor de relaÃ§Ã£o, diÃ¡logo de remoÃ§Ã£o e badge de validaÃ§Ã£o.
**Integration and Contracts**: comando de dependÃªncia e projeÃ§Ã£o de impacto; backend Ã© autoridade de validaÃ§Ã£o.
**Telemetry**: tentativa, bloqueio por categoria, criaÃ§Ã£o, ediÃ§Ã£o e remoÃ§Ã£o; sem tÃ­tulo de tarefa.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-deps-001.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | nenhuma relaÃ§Ã£o em ediÃ§Ã£o | iniciar seleÃ§Ã£o | loading |
| loading | opÃ§Ãµes/projeÃ§Ã£o sendo carregadas | cancelar | ready/error |
| empty | tarefa sem candidatos vÃ¡lidos | fechar | ready |
| ready | candidatos e tipo selecionÃ¡veis | validar/criar/remover | processing |
| processing | comando pendente | aguardar/cancelar se ainda nÃ£o enviado | success/error |
| success | relaÃ§Ã£o desenhada e confirmaÃ§Ã£o | continuar | ready |
| validation-error | motivo de bloqueio no fluxo | corrigir | ready |
| remote-error | falha de comando | retentar | ready |
| offline | escrita desabilitada e aviso | retentar | loading |
| access-denied | sem Gantt/sessÃ£o | voltar | initial |
| partial-stale | relaÃ§Ãµes alteradas externamente | reconciliar | loading |

### INT-DEPS-002 â€” Criticidade e relaÃ§Ãµes ocultas

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: mostrar caminho crÃ­tico, folga e relaÃ§Ãµes relevantes sem perder contexto por filtro/recolhimento.
**Actors and Permissions**: dono do Gantt.
**Entry and Navigation**: workspace, filtros, item selecionado e navegaÃ§Ã£o de relaÃ§Ã£o.
**Content and Data**: indicador crÃ­tico, folga, conectores, reticÃªncia/contagem de ponta oculta e caminho para revelar item.
**Actions and Behavior**: inspecionar relaÃ§Ã£o, revelar extremidade oculta, focalizar predecessor/sucessor e alternar densidade visual.
**Validation and Feedback**: criticidade Ã© somente leitura e marcada como calculada; estado parcial informa que aguarda reconciliaÃ§Ã£o.
**Responsive/Adaptive Behavior**: desktop mantÃ©m conectores; touch usa lista/inspector como alternativa a alvos pequenos.
**Accessibility**: relaÃ§Ã£o disponÃ­vel como texto navegÃ¡vel, nÃ£o apenas linha SVG; criticidade tem nome acessÃ­vel.
**Localization**: labels de folga e estado localizÃ¡veis.
**Components and Design System**: badge crÃ­tico, inspector de relaÃ§Ã£o, reticÃªncia e lista adaptada.
**Integration and Contracts**: consome projeÃ§Ã£o de grafo/folga; nÃ£o calcula na UI.
**Telemetry**: inspeÃ§Ã£o, revelaÃ§Ã£o e densidade.
**Wireframe Requirement**: N/A
**Wireframe**: N/A â€” extensÃ£o da timeline estruturada.

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | nenhuma projeÃ§Ã£o carregada | aguardar | loading |
| loading | conectores/indicadores reservados | navegar | ready/error |
| empty | sem relaÃ§Ãµes ou criticidade aplicÃ¡vel | criar relaÃ§Ã£o | ready |
| ready | indicadores e inspector disponÃ­veis | inspecionar/revelar | loading |
| processing | reconciliaÃ§Ã£o marcada | aguardar | success/error |
| success | projeÃ§Ã£o recalculada | continuar | ready |
| validation-error | N/A â€” sem escrita neste item | N/A | N/A |
| remote-error | erro de projeÃ§Ã£o | recarregar | loading |
| offline | Ãºltimo grafo marcado | retentar | loading |
| access-denied | sem workspace | voltar | initial |
| partial-stale | cÃ¡lculo desatualizado identificado | reconciliar | loading |

## Cross-Surface Rules

RelaÃ§Ã£o, tipo e criticidade tÃªm a mesma semÃ¢ntica em todos os form factors; touch e teclado nunca dependem de conectores visuais. Nenhuma relaÃ§Ã£o Ã© persistida sÃ³ porque a UI a desenhou.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
|---|---|---|---|---|
| INT-DEPS-001 | US-1, US-3 | FR-001â€“005 | SC-001â€“002 | dependÃªncias |
| INT-DEPS-002 | US-2 | FR-006â€“008 | SC-003 | workspace/grafo |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
|---|---|---|---|
| INT-DEPS-001 | REQUIRED | wireframes/int-deps-001.md | Alternativa a drag e validaÃ§Ã£o |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: pending contracts stage
- Placeholders or open decisions remaining: 0
