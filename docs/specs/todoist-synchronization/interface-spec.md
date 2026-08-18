# Interface Specification: SincronizaÃ§Ã£o com Todoist

**Feature**: `todoist-synchronization`
**Created**: 2026-08-17
**Status**: Draft
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
|---|---|---|---|---|---|
| SURF-WEB-OPERATIONS | WEB | Planejador | FULL | estado, conflitos, atualizaÃ§Ã£o e recuperaÃ§Ã£o | ediÃ§Ã£o offline |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
|---|---|---|---|
| SURF-WEB-OPERATIONS | `App.vue`, SSE/controller | auditoria | reconecta e recarrega, mas nÃ£o expÃµe lifecycle/confllito completo. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
|---|---|---|---|---|---|
| INT-SYNC-001 | SURF-WEB-OPERATIONS | STATUS | MODIFIED | Estado de sincronizaÃ§Ã£o | barra do sistema/item/operacÃ£o |
| INT-SYNC-002 | SURF-WEB-OPERATIONS | DIALOG | NEW | Conflito e recuperaÃ§Ã£o | estado de conflito ou falha |

## Interaction Details

### INT-SYNC-001 â€” Estado de sincronizaÃ§Ã£o

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: tornar visÃ­vel se o Gantt/projeto estÃ¡ sincronizado, pendente, degradado ou desatualizado.
**Actors and Permissions**: dono do Gantt.
**Entry and Navigation**: barra do sistema, item afetado e detalhe de operaÃ§Ã£o; abre diagnÃ³stico quando necessÃ¡rio.
**Content and Data**: estado, Ãºltima reconciliaÃ§Ã£o, contagem de pendÃªncias/conflitos, mensagem acionÃ¡vel e origem da alteraÃ§Ã£o.
**Actions and Behavior**: recarregar/reconciliar, abrir operaÃ§Ã£o, reconectar integraÃ§Ã£o e navegar a item afetado; leitura continua com marcaÃ§Ã£o quando permitido.
**Validation and Feedback**: nenhuma escrita Ã© marcada como concluÃ­da antes de confirmaÃ§Ã£o; indisponibilidade externa diferencia falha temporÃ¡ria de revogaÃ§Ã£o.
**Responsive/Adaptive Behavior**: badge resumido no telefone com painel de detalhes; desktop mostra resumo contextual.
**Accessibility**: status textual alÃ©m de cor/Ã­cone, mudanÃ§as anunciadas sem roubar foco e aÃ§Ãµes de retry rotuladas.
**Localization**: termos de status e horÃ¡rio da Ãºltima atualizaÃ§Ã£o localizÃ¡veis.
**Components and Design System**: badge, banner degradado, painel de diagnÃ³stico e toast agrupado.
**Integration and Contracts**: consome eventos de workspace e consulta de estado; eventos nÃ£o carregam segredo.
**Telemetry**: transiÃ§Ã£o de estado, tempo pendente, retry e reconexÃ£o; sem dados de tarefa.
**Wireframe Requirement**: N/A
**Wireframe**: N/A â€” extensÃ£o da barra do sistema.

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | estado desconhecido | aguardar | loading |
| loading | verificando/reconciliando | navegar | ready/error |
| empty | sem pendÃªncias | continuar | ready |
| ready | sincronizado ou pendente identificado | abrir detalhe/reconciliar | processing |
| processing | operaÃ§Ã£o de sync em curso | acompanhar | success/error |
| success | estado sincronizado anunciado | continuar | ready |
| validation-error | N/A â€” sem formulÃ¡rio | N/A | N/A |
| remote-error | Todoist indisponÃ­vel/revogado explicado | retry/reconectar | loading |
| offline | leitura marcada e escrita bloqueada | retentar | loading |
| access-denied | integraÃ§Ã£o/sessÃ£o nÃ£o autorizada | reconectar/entrar | initial |
| partial-stale | Ãºltimo snapshot/hora e itens afetados | reconciliar | loading |

### INT-SYNC-002 â€” Conflito e recuperaÃ§Ã£o

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: impedir sobrescrita silenciosa e orientar recuperaÃ§Ã£o de falha parcial ou alteraÃ§Ã£o concorrente.
**Actors and Permissions**: dono do Gantt.
**Entry and Navigation**: badge de conflito, detalhe de operaÃ§Ã£o ou item afetado.
**Content and Data**: mudanÃ§a local/externa relevante, estado da operaÃ§Ã£o, itens aplicados/pendentes/falhos e prÃ³xima aÃ§Ã£o segura.
**Actions and Behavior**: reconciliar, recalcular, retentar item/operaÃ§Ã£o quando elegÃ­vel, descartar apenas intenÃ§Ã£o nÃ£o persistida e abrir histÃ³rico.
**Validation and Feedback**: aÃ§Ã£o sÃ³ Ã© oferecida conforme estado autorizado; nÃ£o hÃ¡ botÃ£o de â€œforÃ§arâ€ sem nova revalidaÃ§Ã£o.
**Responsive/Adaptive Behavior**: diÃ¡logo/painel desktop e folha telefone, com resumo primeiro.
**Accessibility**: foco no tÃ­tulo, comparaÃ§Ã£o em texto, ordem lÃ³gica e anÃºncio de resultado.
**Localization**: origem e datas localizÃ¡veis.
**Components and Design System**: diÃ¡logo de conflito, diff textual, lista de itens e links de histÃ³rico.
**Integration and Contracts**: operaÃ§Ã£o/reconciliaÃ§Ã£o e histÃ³rico correlato.
**Telemetry**: categoria de conflito, aÃ§Ã£o escolhida e resultado, sem conteÃºdo sensÃ­vel.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-sync-002.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | conflito ainda nÃ£o carregado | fechar | loading |
| loading | buscando detalhes | fechar | ready/error |
| empty | conflito jÃ¡ resolvido | voltar | ready |
| ready | comparaÃ§Ã£o e aÃ§Ãµes seguras | reconciliar/retry | processing |
| processing | recuperaÃ§Ã£o em andamento | acompanhar | success/error |
| success | resultado e histÃ³rico | continuar | ready |
| validation-error | aÃ§Ã£o nÃ£o elegÃ­vel | recarregar | ready |
| remote-error | recuperaÃ§Ã£o falhou | retry/diagnÃ³stico | ready |
| offline | recuperaÃ§Ã£o suspensa | retentar | loading |
| access-denied | conexÃ£o revogada | reconectar | initial |
| partial-stale | conflito mudou durante leitura | recarregar | loading |

## Cross-Surface Rules

AtualizaÃ§Ã£o externa sÃ³ altera a interface apÃ³s reconciliaÃ§Ã£o. â€œSincronizadoâ€ Ã© um estado explÃ­cito, nÃ£o uma impressÃ£o causada por sucesso visual local; conflitos tÃªm origem e cadeia causal acessÃ­veis.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
|---|---|---|---|---|
| INT-SYNC-001 | US-1â€“2 | FR-001â€“005, FR-008 | SC-002â€“003 | eventos/sync-status |
| INT-SYNC-002 | US-3 | FR-003â€“007 | SC-002â€“003 | operaÃ§Ã£o/reconciliaÃ§Ã£o |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
|---|---|---|---|
| INT-SYNC-002 | REQUIRED | wireframes/int-sync-002.md | Conflito e recuperaÃ§Ã£o |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: pending contracts stage
- Placeholders or open decisions remaining: 0
