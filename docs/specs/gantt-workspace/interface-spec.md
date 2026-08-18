# Interface Specification: Workspace de Projeto Gantt

**Feature**: `gantt-workspace`
**Created**: 2026-08-17
**Status**: Draft
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
|---|---|---|---|---|---|
| SURF-WEB-OPERATIONS | WEB | UsuÃ¡rio autenticado | FULL | seleÃ§Ã£o de projeto, Ã¡rvore, timeline e estados do workspace | mÃºltiplos projetos em uma sÃ³ timeline |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
|---|---|---|---|
| SURF-WEB-OPERATIONS | `resources/js/App.vue`, `WorkspaceController` | auditoria | Ã¡rvore/timeline bÃ¡sica com fixture e projeÃ§Ã£o simplificada. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
|---|---|---|---|---|---|
| INT-WORKSPACE-001 | SURF-WEB-OPERATIONS | SCREEN | MODIFIED | Workspace de projeto | projeto selecionado |

## Interaction Details

### INT-WORKSPACE-001 â€” Workspace de projeto

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: apresentar uma Ãºnica projeÃ§Ã£o confiÃ¡vel de Ã¡rvore e cronograma de um projeto selecionado.
**Actors and Permissions**: usuÃ¡rio autenticado, dono do Gantt.
**Entry and Navigation**: seleÃ§Ã£o de projeto abre o workspace; busca, histÃ³rico e configuraÃ§Ãµes retornam preservando foco/contexto.
**Content and Data**: barra do sistema; resumo; Ã¡rvore por seÃ§Ãµes/tarefas/subtarefas; timeline; grupos derivados; tarefas sem data; estado de sincronizaÃ§Ã£o.
**Actions and Behavior**: expandir/recolher, selecionar item, abrir painel, navegar no tempo, abrir filtros/configuraÃ§Ãµes e solicitar recarga. Grupos nÃ£o sÃ£o editÃ¡veis diretamente.
**Validation and Feedback**: respostas exibem somente projeÃ§Ã£o autorizada; projeto vazio, integraÃ§Ã£o ausente e falha de sincronizaÃ§Ã£o tÃªm explicaÃ§Ã£o e aÃ§Ã£o de recuperaÃ§Ã£o.
**Responsive/Adaptive Behavior**: desktop mostra Ã¡rvore e timeline lado a lado; tablet reduz colunas; telefone usa Ã¡rvore e timeline/painel em Ã¡reas alternÃ¡veis, preservando seleÃ§Ã£o.
**Accessibility**: estrutura de Ã¡rvore navegÃ¡vel por teclado, foco visÃ­vel, relaÃ§Ã£o entre linha e barra, sem depender apenas de cor; alvos touch adequados.
**Localization**: `pt-BR`; datas respeitam locale e timezone de planejamento.
**Components and Design System**: barra do sistema, Ã¡rvore virtualizada, timeline, badge de estado, painel e mensagens compartilhadas.
**Integration and Contracts**: projeÃ§Ã£o de workspace e eventos de atualizaÃ§Ã£o; cliente nÃ£o calcula grupos, estados ou criticidade.
**Telemetry**: abertura, tempo atÃ© pronto, projeto vazio, erro, recarga e alternÃ¢ncia de viewport; sem tÃ­tulos de tarefa.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-workspace-001.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | estrutura reservada e contexto de projeto | cancelar/navegar | loading |
| loading | skeleton de Ã¡rvore/timeline | cancelar navegaÃ§Ã£o | ready/error |
| empty | projeto sem tarefas, sem estrutura artificial | voltar/atualizar | loading |
| ready | Ã¡rvore e timeline alinhadas | navegar/selecionar/editar permitido | processing/loading |
| processing | operaÃ§Ã£o especÃ­fica marcada sem bloquear leitura | acompanhar/cancelar quando permitido | success/error |
| success | confirmaÃ§Ã£o breve e projeÃ§Ã£o atualizada | continuar | ready |
| validation-error | aÃ§Ã£o invÃ¡lida explicada no item | corrigir | ready |
| remote-error | integraÃ§Ã£o indisponÃ­vel e retry | recarregar/reconectar | loading |
| offline | Ãºltimo estado sinalizado | retentar apÃ³s rede | loading |
| access-denied | sessÃ£o/projeto indisponÃ­vel | entrar/selecionar projeto | initial |
| partial-stale | Ãºltimo snapshot e horÃ¡rio marcados | reconciliar | loading |

## Cross-Surface Rules

Desktop, tablet e telefone compartilham o mesmo estado de negÃ³cio; apenas a disposiÃ§Ã£o e o mÃ©todo de entrada variam. Toda alteraÃ§Ã£o chega por projeÃ§Ã£o atualizada, nÃ£o por mutaÃ§Ã£o local persistente.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
|---|---|---|---|---|
| INT-WORKSPACE-001 | US-1â€“3 | FR-001â€“007 | SC-001â€“003 | workspace/eventos |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
|---|---|---|---|
| INT-WORKSPACE-001 | REQUIRED | wireframes/int-workspace-001.md | Hierarquia e timeline responsiva |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: pending contracts stage
- Placeholders or open decisions remaining: 0
