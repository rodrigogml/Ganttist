# Interface Specification: Auditoria e Rastreabilidade

**Feature**: `audit-traceability`
**Created**: 2026-08-17
**Status**: Draft
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
|---|---|---|---|---|---|
| SURF-WEB-OPERATIONS | WEB | Planejador | FULL | histÃ³rico por projeto/tarefa, filtros, operaÃ§Ã£o e cadeia causal | exportaÃ§Ã£o e undo genÃ©rico |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
|---|---|---|---|
| SURF-WEB-OPERATIONS | sem painel de histÃ³rico | auditoria | tabelas iniciais existem, mas nÃ£o hÃ¡ consulta/auditoria funcional. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
|---|---|---|---|---|---|
| INT-AUDIT-001 | SURF-WEB-OPERATIONS | PANEL | NEW | HistÃ³rico de projeto e tarefa | barra/painel de item/operaÃ§Ã£o |

## Interaction Details

### INT-AUDIT-001 â€” HistÃ³rico de projeto e tarefa

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: permitir entender o que mudou, origem, responsÃ¡vel, impacto e resultado de uma operaÃ§Ã£o sem carregar o histÃ³rico inteiro junto com o Gantt.
**Actors and Permissions**: dono do Gantt; somente dados do prÃ³prio usuÃ¡rio/projeto.
**Entry and Navigation**: barra do sistema, painel de tarefa, operaÃ§Ã£o/falha e deep link de evento; item relacionado abre no workspace.
**Content and Data**: linha temporal paginada, data/hora, aÃ§Ã£o, origem, sujeito, antes/depois resumidos, cadeia causal, estado de operaÃ§Ã£o e filtros por perÃ­odo/tarefa/tipo/origem.
**Actions and Behavior**: filtrar, paginar, expandir evento, navegar a tarefa/operaÃ§Ã£o e retentar quando o evento representa falha elegÃ­vel. Eventos nÃ£o podem ser editados.
**Validation and Feedback**: filtro invÃ¡lido explica correÃ§Ã£o; falta de resultados Ã© distinta de erro; exclusÃ£o/retencÃ£o segue polÃ­tica e nÃ£o permite restaurar evento pela UI.
**Responsive/Adaptive Behavior**: painel lateral/tabela desktop; lista cronolÃ³gica e filtros em folha no telefone; detalhes abrem em sobreposiÃ§Ã£o.
**Accessibility**: timeline tem equivalente de lista, filtros rotulados, detalhes anunciam mudanÃ§a, datas/origens possuem texto e nÃ£o sÃ³ Ã­cones.
**Localization**: datas/timestamps no timezone do usuÃ¡rio, termos de origem e aÃ§Ã£o localizÃ¡veis.
**Components and Design System**: timeline/lista, chips de filtro, paginaÃ§Ã£o, painel de detalhe e badge de operaÃ§Ã£o.
**Integration and Contracts**: consulta paginada de histÃ³rico e detalhes de operaÃ§Ã£o; dados sensÃ­veis e segredos nunca sÃ£o enviados ao cliente.
**Telemetry**: abertura, filtro, paginaÃ§Ã£o, expansÃ£o e navegaÃ§Ã£o correlata; nÃ£o enviar before/after completo.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-audit-001.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | painel fechado/sem consulta | abrir | loading |
| loading | lista skeleton | fechar | ready/error |
| empty | nenhum evento para filtro | ajustar/limpar | ready |
| ready | eventos paginados e filtros | filtrar/expandir/navegar | loading |
| processing | prÃ³ximo lote/detalhe carregando | aguardar | success/error |
| success | detalhe ou pÃ¡gina adicional disponÃ­vel | continuar | ready |
| validation-error | filtro invÃ¡lido explicado | corrigir | ready |
| remote-error | consulta indisponÃ­vel | retry | loading |
| offline | Ãºltimo histÃ³rico marcado como parcial | retentar | loading |
| access-denied | sem projeto/sessÃ£o | voltar | initial |
| partial-stale | eventos recentes aguardam reconciliaÃ§Ã£o | atualizar | loading |

## Cross-Surface Rules

Auditoria funcional Ã© imutÃ¡vel: correÃ§Ãµes aparecem como novos eventos relacionados. HistÃ³rico nÃ£o substitui logs tÃ©cnicos e nÃ£o expÃµe segredos ou dados de outros usuÃ¡rios.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
|---|---|---|---|---|
| INT-AUDIT-001 | US-1â€“3 | FR-001â€“008 | SC-001â€“003 | histÃ³rico/operaÃ§Ãµes |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
|---|---|---|---|
| INT-AUDIT-001 | REQUIRED | wireframes/int-audit-001.md | Filtros, timeline e detalhe |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: pending contracts stage
- Placeholders or open decisions remaining: 0
