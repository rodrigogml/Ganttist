# Interface Specification: CalendÃ¡rio e Datas das Tarefas

**Feature**: `calendar-task-dates`
**Created**: 2026-08-17
**Status**: Draft
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
|---|---|---|---|---|---|
| SURF-WEB-OPERATIONS | WEB | Planejador | FULL | calendÃ¡rio, datas, duraÃ§Ã£o, grupos e estados temporais | horas, fraÃ§Ãµes e templates |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
|---|---|---|---|
| SURF-WEB-OPERATIONS | `App.vue`, `WorkCalendar` | auditoria | datas editÃ¡veis, mas UI usa dias corridos e calendÃ¡rio fixo. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
|---|---|---|---|---|---|
| INT-CALENDAR-001 | SURF-WEB-OPERATIONS | PANEL | NEW | ConfiguraÃ§Ã£o de calendÃ¡rio | configuraÃ§Ãµes do Gantt |
| INT-CALENDAR-002 | SURF-WEB-OPERATIONS | PANEL | MODIFIED | Datas e estado temporal da tarefa | seleÃ§Ã£o de tarefa/barra |

## Interaction Details

### INT-CALENDAR-001 â€” ConfiguraÃ§Ã£o de calendÃ¡rio

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: permitir definir semana Ãºtil, exceÃ§Ãµes e polÃ­tica temporal do Gantt antes de confirmar impactos.
**Actors and Permissions**: dono do Gantt.
**Entry and Navigation**: configuraÃ§Ãµes do projeto; retorno preserva workspace.
**Content and Data**: dias da semana, exceÃ§Ãµes por data/descriÃ§Ã£o, timezone de planejamento, polÃ­tica de deadline, modo manual/automÃ¡tico e opÃ§Ã£o sobre tarefas sem data.
**Actions and Behavior**: adicionar/remover exceÃ§Ã£o; salvar; solicitar simulaÃ§Ã£o quando houver impacto; confirmar ou cancelar aplicaÃ§Ã£o.
**Validation and Feedback**: ao menos um dia Ãºtil; data vÃ¡lida; conflito de exceÃ§Ã£o explicado; modo manual nunca persiste impacto sem confirmaÃ§Ã£o.
**Responsive/Adaptive Behavior**: grade semanal vira lista no telefone; exceÃ§Ãµes em painel/pÃ¡gina dedicada; nÃ£o exige arraste.
**Accessibility**: checkbox com rÃ³tulo, tabela de exceÃ§Ãµes navegÃ¡vel, diÃ¡logo de impacto com foco e leitura por screen reader.
**Localization**: datas e nomes de dias pelo locale; timezone exibido explicitamente.
**Components and Design System**: formulÃ¡rio, calendÃ¡rio de exceÃ§Ãµes, badge de impacto, diÃ¡logo e toast.
**Integration and Contracts**: contrato de configuraÃ§Ã£o e simulaÃ§Ã£o; resultado do core Ã© a fonte de verdade.
**Telemetry**: alteraÃ§Ã£o de calendÃ¡rio, itens afetados, confirmaÃ§Ã£o/cancelamento e erro; sem tÃ­tulos.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-calendar-001.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | configuraÃ§Ã£o ainda nÃ£o carregada | fechar | loading |
| loading | formulÃ¡rio skeleton | fechar | ready/error |
| empty | calendÃ¡rio sem exceÃ§Ãµes | adicionar exceÃ§Ã£o | ready |
| ready | regras atuais editÃ¡veis | editar/simular/salvar | processing |
| processing | mudanÃ§a bloqueada com impacto | aguardar/cancelar simulaÃ§Ã£o | success/error |
| success | confirmaÃ§Ã£o e versÃ£o atualizada | voltar | ready |
| validation-error | erro no campo | corrigir | ready |
| remote-error | erro recuperÃ¡vel | tentar novamente | loading |
| offline | aviso, sem salvar | retentar | loading |
| access-denied | sem permissÃ£o/projeto | voltar | initial |
| partial-stale | configuraÃ§Ã£o mudou externamente | recarregar | loading |

### INT-CALENDAR-002 â€” Datas e estado temporal da tarefa

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: explicar e editar inÃ­cio/fim/duraÃ§Ã£o vÃ¡lidos sem confundir dado Todoist, valor derivado e referÃªncia virtual.
**Actors and Permissions**: dono do Gantt.
**Entry and Navigation**: painel de tarefa e seleÃ§Ã£o de barra; retorno ao workspace.
**Content and Data**: inÃ­cio, deadline, duraÃ§Ã£o Ãºtil, estado, calendÃ¡rio aplicÃ¡vel e aviso de nÃ£o planejada/virtual/inconsistÃªncia.
**Actions and Behavior**: definir/remover data, mover/redimensionar quando permitido e abrir simulaÃ§Ã£o; grupo Ã© somente leitura.
**Validation and Feedback**: dias bloqueados impedem/encaixam aÃ§Ã£o conforme configuraÃ§Ã£o; deadline invÃ¡lido explicado; ausÃªncia de data usa timeblock provisÃ³rio de um dia em hoje, visualmente distinto e sem persistÃªncia atÃ© uma aÃ§Ã£o explÃ­cita.
**Responsive/Adaptive Behavior**: painel lateral desktop, folha/modal telefone; entrada de data acessÃ­vel sem depender de drag.
**Accessibility**: campos rotulados, mensagens associadas, equivalentes de teclado para aÃ§Ãµes de barra.
**Localization**: datas civis em formato local, sem horÃ¡rio.
**Components and Design System**: painel, seletor de data, badge de estado e aviso de derivaÃ§Ã£o.
**Integration and Contracts**: intenÃ§Ã£o de ediÃ§Ã£o e projeÃ§Ã£o calculada; datas derivadas retornam identificadas.
**Telemetry**: ediÃ§Ã£o, bloqueio por calendÃ¡rio, simulaÃ§Ã£o e confirmaÃ§Ã£o.
**Wireframe Requirement**: N/A
**Wireframe**: N/A â€” extensÃ£o do painel de tarefa jÃ¡ estruturado.

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | nenhuma tarefa selecionada | selecionar | loading |
| loading | painel aguarda projeÃ§Ã£o | fechar | ready/error |
| empty | tarefa sem data identificada | definir data/simular | ready |
| ready | dados persistidos/derivados distintos | editar | processing |
| processing | prÃ©via ou persistÃªncia em andamento | cancelar quando manual | success/error |
| success | dados atualizados | continuar | ready |
| validation-error | regra temporal explicada | ajustar | ready |
| remote-error | falha externa | retentar | ready |
| offline | Ãºltimo dado marcado | retentar | loading |
| access-denied | tarefa fora do Gantt | voltar | initial |
| partial-stale | dado externo mais novo | reconciliar | loading |

## Cross-Surface Rules

Sem horas ou fraÃ§Ãµes. Grupos, criticidade e referÃªncias virtuais sÃ£o sempre identificados como derivados; somente o resultado confirmado pode alterar datas nativas.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
|---|---|---|---|---|
| INT-CALENDAR-001 | US-2 | FR-001â€“005 | SC-001â€“002 | calendÃ¡rio/simulaÃ§Ã£o |
| INT-CALENDAR-002 | US-1, US-3 | FR-003â€“008 | SC-001â€“003 | tarefa/projeÃ§Ã£o |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
|---|---|---|---|
| INT-CALENDAR-001 | REQUIRED | wireframes/int-calendar-001.md | Regras e preview de impacto |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: pending contracts stage
- Placeholders or open decisions remaining: 0
