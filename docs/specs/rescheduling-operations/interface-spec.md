# Interface Specification: OperaÃ§Ãµes e Reagendamento

**Feature**: `rescheduling-operations`
**Created**: 2026-08-17
**Status**: Draft
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
|---|---|---|---|---|---|
| SURF-WEB-OPERATIONS | WEB | Planejador | FULL | ediÃ§Ã£o, simulaÃ§Ã£o, aplicaÃ§Ã£o, rota e recuperaÃ§Ã£o | undo genÃ©rico |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
|---|---|---|---|
| SURF-WEB-OPERATIONS | `App.vue`, `Schedule*Controller` | auditoria | ghost/aplicar simples, sem operaÃ§Ã£o recuperÃ¡vel. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
|---|---|---|---|---|---|
| INT-OPS-001 | SURF-WEB-OPERATIONS | FLOW | MODIFIED | SimulaÃ§Ã£o e confirmaÃ§Ã£o de reagendamento | mover/editar tarefa, calendÃ¡rio ou dependÃªncia |
| INT-OPS-002 | SURF-WEB-OPERATIONS | DIALOG | NEW | Inserir/excluir tarefa em rota | aÃ§Ã£o destrutiva/contextual |

## Interaction Details

### INT-OPS-001 â€” SimulaÃ§Ã£o e confirmaÃ§Ã£o de reagendamento

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: mostrar toda a cascata calculada antes da persistÃªncia manual e acompanhar a aplicaÃ§Ã£o/recuperaÃ§Ã£o.
**Actors and Permissions**: dono do Gantt.
**Entry and Navigation**: drag/resize, ediÃ§Ã£o de data, calendÃ¡rio ou dependÃªncia; retorno preserva viewport e seleÃ§Ã£o.
**Content and Data**: intenÃ§Ã£o inicial, ghosts, antes/depois, duraÃ§Ã£o preservada, relaÃ§Ãµes afetadas, itens concluÃ­dos imutÃ¡veis, resumo e identificador da operaÃ§Ã£o.
**Actions and Behavior**: revisar, confirmar, cancelar; no modo automÃ¡tico mostrar aplicaÃ§Ã£o iniciada; abrir detalhes de falha/retry; nunca aplicar o ghost apenas por fechar o painel.
**Validation and Feedback**: core bloqueia calendÃ¡rio/precedÃªncia invÃ¡lidos; confirmaÃ§Ã£o Ã© obrigatÃ³ria no modo manual; falha parcial identifica itens concluÃ­dos, pendentes e aÃ§Ã£o de recuperaÃ§Ã£o.
**Responsive/Adaptive Behavior**: desktop mostra ghosts/tabela lateral; telefone usa resumo em folha com foco em contagem/impacto e alternativa a drag.
**Accessibility**: resumo textual de cada alteraÃ§Ã£o, foco no diÃ¡logo, teclas para confirmar/cancelar, status de progresso anunciado e nÃ£o dependente de animaÃ§Ã£o.
**Localization**: datas civis no locale e termos â€œsimulaÃ§Ã£oâ€, â€œpendenteâ€, â€œrecuperarâ€ localizÃ¡veis.
**Components and Design System**: ghost, painel de impacto, diÃ¡logo de confirmaÃ§Ã£o, lista de itens e badge de operaÃ§Ã£o.
**Integration and Contracts**: criar/consultar operaÃ§Ã£o e projeÃ§Ã£o calculada; UI nÃ£o envia rede/duraÃ§Ã£o como fonte de verdade.
**Telemetry**: origem, tamanho da cascata, confirmar/cancelar, falha/retry; sem conteÃºdo de tarefa.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-ops-001.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | nenhuma operaÃ§Ã£o ativa | iniciar alteraÃ§Ã£o | loading |
| loading | cÃ¡lculo solicitado | cancelar solicitaÃ§Ã£o | ready/error |
| empty | nenhuma mudanÃ§a necessÃ¡ria | fechar | ready |
| ready | ghosts e resumo revisÃ¡veis | confirmar/cancelar | processing |
| processing | operaÃ§Ã£o e itens com progresso | acompanhar/retry quando elegÃ­vel | success/error |
| success | resultado e link de histÃ³rico | continuar | ready |
| validation-error | intenÃ§Ã£o invÃ¡lida explicada | ajustar | ready |
| remote-error | falha temporÃ¡ria/definitiva distinguida | retry/diagnÃ³stico | processing/ready |
| offline | escrita nÃ£o iniciada e aviso | retentar | loading |
| access-denied | sessÃ£o/Gantt invÃ¡lido | voltar | initial |
| partial-stale | snapshot mudou antes de aplicar | recalcular | loading |

### INT-OPS-002 â€” Inserir/excluir tarefa em rota

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: executar mudanÃ§a estrutural deliberada sem quebrar relaÃ§Ãµes silenciosamente.
**Actors and Permissions**: dono do Gantt.
**Entry and Navigation**: menu contextual/painel de tarefa.
**Content and Data**: relaÃ§Ãµes de entrada/saÃ­da, opÃ§Ã£o de continuidade, preview ghost e impacto.
**Actions and Behavior**: escolher manter ou nÃ£o continuidade; confirmar exclusÃ£o/inserÃ§Ã£o; cancelar sem mudanÃ§a.
**Validation and Feedback**: destino invÃ¡lido/ciclo bloqueado; confirmaÃ§Ã£o destrutiva obrigatÃ³ria; falha parcial abre operaÃ§Ã£o.
**Responsive/Adaptive Behavior**: diÃ¡logo central desktop, folha telefone.
**Accessibility**: tÃ­tulo/descriÃ§Ã£o de impacto associados, foco retido, confirmaÃ§Ã£o nÃ£o preselecionada.
**Localization**: mensagens de impacto localizÃ¡veis.
**Components and Design System**: diÃ¡logo destrutivo, preview e lista de relaÃ§Ãµes.
**Integration and Contracts**: comando composto e resultado de operaÃ§Ã£o.
**Telemetry**: tipo, continuidade, confirmaÃ§Ã£o/cancelamento e resultado.
**Wireframe Requirement**: N/A
**Wireframe**: N/A â€” diÃ¡logo derivado do padrÃ£o de operaÃ§Ã£o.

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | opÃ§Ã£o ainda nÃ£o aberta | abrir | loading |
| loading | impacto sendo consultado | cancelar | ready/error |
| empty | sem relaÃ§Ãµes afetadas | confirmar/cancelar | ready |
| ready | opÃ§Ãµes e preview | confirmar/cancelar | processing |
| processing | comando em aplicaÃ§Ã£o | acompanhar | success/error |
| success | rota atualizada | continuar | ready |
| validation-error | regra violada | corrigir | ready |
| remote-error | operaÃ§Ã£o falhou | retry/diagnÃ³stico | ready |
| offline | aÃ§Ã£o bloqueada | retentar | loading |
| access-denied | sessÃ£o invÃ¡lida | voltar | initial |
| partial-stale | rota mudou | recalcular | loading |

## Cross-Surface Rules

SimulaÃ§Ã£o Ã© visualizaÃ§Ã£o, nÃ£o persistÃªncia. Todo resultado final Ã© comunicado por estado de operaÃ§Ã£o e projeÃ§Ã£o atualizada; operaÃ§Ãµes compostas nunca sÃ£o tratadas como sucesso binÃ¡rio quando houver falha parcial.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
|---|---|---|---|---|
| INT-OPS-001 | US-1â€“2 | FR-001â€“006, FR-008 | SC-001â€“003 | operaÃ§Ãµes/simulaÃ§Ã£o |
| INT-OPS-002 | US-3 | FR-007â€“008 | SC-003 | operaÃ§Ãµes/rota |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
|---|---|---|---|
| INT-OPS-001 | REQUIRED | wireframes/int-ops-001.md | Ghost, resumo e confirmaÃ§Ã£o |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: pending contracts stage
- Placeholders or open decisions remaining: 0
