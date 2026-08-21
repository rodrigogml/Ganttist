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
| INT-WORKSPACE-002 | SURF-WEB-OPERATIONS | DIRECT MANIPULATION | NEW | Resize temporal do timeblock | hover, foco ou toque no timeblock |
| INT-WORKSPACE-003 | SURF-WEB-OPERATIONS | DIRECT MANIPULATION | NEW | Criação gráfica de dependência | endpoint externo do timeblock |

## Interaction Details

### INT-WORKSPACE-001 — Workspace de projeto

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: apresentar uma Ãºnica projeÃ§Ã£o confiÃ¡vel de Ã¡rvore e cronograma de um projeto selecionado.
**Actors and Permissions**: usuÃ¡rio autenticado, dono do Gantt.
**Entry and Navigation**: seleÃ§Ã£o de projeto abre o workspace; busca, histÃ³rico e configuraÃ§Ãµes retornam preservando foco/contexto.
**Content and Data**: barra do sistema; resumo; Ã¡rvore por seÃ§Ãµes/tarefas/subtarefas; timeline; grupos derivados; campos explícitos do Todoist; data, deadline e desbloqueio considerados; status calculado; estado de sincronizaÃ§Ã£o.
**Actions and Behavior**: expandir/recolher, selecionar item, abrir painel, navegar no tempo, filtrar por `COMPLETED`, `BLOCKED`, `SCHEDULED`, `LATE` ou `OPENED`, abrir configuraÃ§Ãµes e solicitar recarga. Grupos nÃ£o sÃ£o editÃ¡veis diretamente; status calculado não é editável.
**Validation and Feedback**: respostas exibem somente projeÃ§Ã£o autorizada; projeto vazio, integraÃ§Ã£o ausente e falha de sincronizaÃ§Ã£o tÃªm explicaÃ§Ã£o e aÃ§Ã£o de recuperaÃ§Ã£o.
**Responsive/Adaptive Behavior**: desktop mostra Ã¡rvore e timeline lado a lado; tablet reduz colunas; telefone usa Ã¡rvore e timeline/painel em Ã¡reas alternÃ¡veis, preservando seleÃ§Ã£o.
**Accessibility**: estrutura de Ã¡rvore navegÃ¡vel por teclado, foco visÃ­vel, relaÃ§Ã£o entre linha e barra, sem depender apenas de cor; alvos touch adequados.
**Localization**: `pt-BR`; datas respeitam locale e timezone de planejamento.
**Components and Design System**: barra do sistema, Ã¡rvore virtualizada, timeline, badge de estado, painel e mensagens compartilhadas.
**Integration and Contracts**: projeÃ§Ã£o de workspace e eventos de atualizaÃ§Ã£o; cliente nÃ£o calcula grupos, datas consideradas, desbloqueio, status ou criticidade. O contrato distingue `start`/`finish` explícitos de `considered_start`/`considered_deadline` calculados.
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

### INT-WORKSPACE-002 — Resize temporal do timeblock

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: permitir alterar início ou deadline diretamente na escala diária sem confundir resize com movimento.
**Actors and Permissions**: usuário autenticado, dono do Gantt; somente tarefas comuns não concluídas.
**Entry and Navigation**: hover/foco revela grips internos; em touch, primeiro toque seleciona/revela controles. O foco retorna ao grip após sucesso ou cancelamento.
**Content and Data**: timeblock original; grips inicial/final; ghost com início/deadline previstos; limite temporal efetivo; datas civis no fuso do projeto.
**Actions and Behavior**: `ew-resize` nas bordas; centro mantém movimento. Grip direito altera deadline com mínimo no início. Grip esquerdo altera início entre limite temporal e deadline; sem deadline, fixa a antiga borda direita como novo prazo. Snap ocorre em cada coluna diária. Escape cancela. Próximo às bordas do viewport, autoscroll preserva o gesto.
**Validation and Feedback**: limite atingido produz resistência visual e anúncio da data mínima/máxima; tarefa concluída, seção e resumo não exibem grips; falha Todoist restaura o original e oferece retry; sucesso reconcilia toda a projeção.
**Responsive/Adaptive Behavior**: desktop usa hover/foco e hit area interna de 10–12 px; touch usa controle visível após seleção com alvo mínimo ampliado sem alterar a geometria da barra.
**Accessibility**: grips são botões/separadores focáveis com nome “Ajustar início/fim de …”, `aria-valuetext` da data e setas para passos de um dia; Enter/Espaço inicia/confirma e Escape cancela; ghost é anunciado por região `aria-live` sem depender de cor.
**Localization**: `pt-BR`, datas civis no fuso do projeto.
**Components and Design System**: timeblock existente, grip interno, ghost semitransparente e toast compartilhado.
**Integration and Contracts**: `PUT /api/v1/tasks/{id}/dates` com intent `MOVE`, `RESIZE_START` ou `RESIZE_END`; resposta contém campos nativos persistidos e é seguida por reload/evento de workspace.
**Telemetry**: modo, duração em dias, cancelamento, limite atingido e resultado; sem título da tarefa.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-workspace-timeblock-gestures.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | grips ocultos até hover, foco ou seleção touch | focar/selecionar tarefa | ready |
| loading | controles indisponíveis enquanto o workspace carrega | aguardar/cancelar navegação | ready/remote-error |
| empty | nenhum timeblock ou grip | voltar/atualizar | loading |
| ready | grips internos no timeblock elegível | mover, focar ou iniciar resize | processing |
| processing | somente ghost quantizado e datas previstas | mover, confirmar ou cancelar | success/validation-error/remote-error |
| success | projeção reconciliada e confirmação breve | continuar | ready |
| validation-error | ghost removido, original preservado e limite explicado | corrigir/repetir | ready |
| remote-error | original restaurado e falha Todoist explicada | tentar novamente | processing/ready |
| offline | escrita indisponível e último estado preservado | retentar após rede | loading |
| access-denied | sessão/projeto indisponível | entrar/selecionar projeto | initial |
| partial-stale | último snapshot identificado; commit bloqueado | reconciliar | loading |

### INT-WORKSPACE-003 — Criação gráfica de dependência

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: criar relações FS/SS/FF/SF conectando eventos de início e fim dos timeblocks.
**Actors and Permissions**: usuário autenticado, dono do Gantt.
**Entry and Navigation**: endpoints externos aparecem no timeblock em hover/foco; iniciado o drag, endpoints elegíveis aparecem nas demais tarefas.
**Content and Data**: porta de origem, portas de destino, linha SVG até o ponteiro/target, badge do tipo inferido e motivo para target inválido.
**Actions and Behavior**: cursor `crosshair`; origem é predecessora e destino é sucessora. início→início=SS, início→fim=SF, fim→início=FS e fim→fim=FF. Drop válido persiste imediatamente, reconcilia a projeção e oferece desfazer; Escape ou drop fora cancela.
**Validation and Feedback**: autorrelação, duplicidade, ciclo, item externo, seção e grupo como sucessor ficam desabilitados e são revalidados no servidor. Erro mantém o grafo anterior. Durante commit, novo gesto é bloqueado.
**Responsive/Adaptive Behavior**: desktop usa drag preciso; touch amplia portas após seleção e mantém autoscroll. Em viewport estreito, relação também pode ser criada pelo formulário acessível existente no editor.
**Accessibility**: portas focáveis nomeadas “Conectar início/fim de …”; Enter/Espaço escolhe origem e destino; targets inválidos expõem `aria-disabled` e motivo; tipo e resultado são anunciados.
**Localization**: siglas FS/SS/FF/SF permanecem canônicas e possuem descrição localizada em tooltip/aria-label.
**Components and Design System**: portas externas, overlay SVG compartilhado, badge de tipo e toast com ação Desfazer.
**Integration and Contracts**: reutiliza `POST /api/v1/dependencies` e `DELETE /api/v1/dependencies/{id}`; servidor é autoridade para escopo, grupos, duplicidade e ciclo.
**Telemetry**: tipo, origem por endpoint, cancelamento, validação e resultado; IDs/títulos não entram em telemetria.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-workspace-timeblock-gestures.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | portas ocultas até hover, foco ou seleção touch | focar/selecionar tarefa | ready |
| loading | nenhuma porta interativa | aguardar/cancelar navegação | ready/remote-error |
| empty | nenhum endpoint | voltar/atualizar | loading |
| ready | portas externas somente no timeblock ativo | escolher origem | processing |
| processing | overlay, tipo inferido e destinos válidos/invalidos | escolher destino ou cancelar | success/validation-error/remote-error |
| success | relação desenhada, projeção reconciliada e ação Desfazer | continuar/desfazer | ready/processing |
| validation-error | target inválido e motivo anunciado; grafo preservado | escolher outro destino/cancelar | processing/ready |
| remote-error | preview removido e grafo anterior preservado | tentar novamente | processing/ready |
| offline | commit bloqueado e snapshot preservado | retentar após rede | loading |
| access-denied | sessão/projeto indisponível | entrar/selecionar projeto | initial |
| partial-stale | portas desabilitadas até reconciliar | reconciliar | loading |

## Cross-Surface Rules

Desktop, tablet e telefone compartilham o mesmo estado de negÃ³cio; apenas a disposiÃ§Ã£o e o mÃ©todo de entrada variam. Toda alteraÃ§Ã£o chega por projeÃ§Ã£o atualizada, nÃ£o por mutaÃ§Ã£o local persistente.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
|---|---|---|---|---|
| INT-WORKSPACE-001 | US-1â€“3 | FR-001â€“017 | SC-001â€“005 | workspace/eventos |
| INT-WORKSPACE-002 | US-4 | FR-018â€“023, FR-026â€“027 | SC-006, SC-008 | task dates/workspace/eventos |
| INT-WORKSPACE-003 | US-4 | FR-023â€“027 | SC-007â€“008 | dependencies/workspace/eventos |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
|---|---|---|---|
| INT-WORKSPACE-001 | REQUIRED | wireframes/int-workspace-001.md | Hierarquia e timeline responsiva |
| INT-WORKSPACE-002 | REQUIRED | wireframes/int-workspace-timeblock-gestures.md | Grips internos, ghost e limites |
| INT-WORKSPACE-003 | REQUIRED | wireframes/int-workspace-timeblock-gestures.md | Portas externas, linha e targets |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: pending contracts stage
- Placeholders or open decisions remaining: 0
