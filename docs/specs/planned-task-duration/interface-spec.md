# Interface Specification: Duração Planejada da Tarefa

**Feature**: `planned-task-duration`
**Created**: 2026-09-21
**Status**: Draft
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
|---|---|---|---|---|---|
| SURF-WEB-OPERATIONS | WEB | proprietário, editor e leitor | FULL | editar e consultar duração no editor; atualizar duração pelos gestos temporais e visualizar projeção/violação | nenhuma nova superfície nativa ou de integração |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
|---|---|---|---|
| SURF-WEB-OPERATIONS | rota `/projects/{id}/tasks` e `/projects/{id}/gantt`; `ProjectPlanningPage.vue` | editor em `resources/js/ProjectPlanningPage.vue` e timeblock com resize na mesma tela | o editor expõe início e fim, e os gestos enviam as duas datas; duração não é editável nem apresentada como intenção distinta. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
|---|---|---|---|---|---|
| INT-WEB-001 | SURF-WEB-OPERATIONS | PANEL | MODIFIED | Planejamento no editor de tarefa | criar tarefa, abrir tarefa ou selecionar Editar |
| INT-WEB-002 | SURF-WEB-OPERATIONS | DIRECT MANIPULATION | MODIFIED | Gestos temporais do Gantt | mover barra ou usar grip de início/fim |

## Interaction Details

### INT-WEB-001 — Planejamento no editor de tarefa

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: permitir informar e revisar duração planejada sem exigir datas, deixando clara a diferença entre valores planejados e projeções calculadas.
**Actors and Permissions**: proprietário e editor podem alterar; leitor visualiza duração, datas e projeções sem controles de escrita.
**Entry and Navigation**: o drawer existente abre pela criação ou edição de uma tarefa; fechar, cancelar e salvar preservam os comportamentos atuais e devolvem foco ao acionador que abriu o drawer.
**Content and Data**: após início e fim planejados, exibir `Duração planejada`, campo numérico inteiro e unidade fixa `dias úteis`, seguida de texto de ajuda curto. A projeção calculada continua abaixo e mostra início/fim considerados; quando houver conflito, mostra estado e motivo de violação. O campo mostra a duração explícita, enquanto a projeção pode mostrar a duração resolvida sem convertê-la em valor editável persistido.
**Actions and Behavior**: informar duração sem datas é permitido. Alterar duração com início corrige fim; alterar fim com início corrige duração; alterar início com duração corrige fim. Limpar início preserva fim e duração; limpar fim preserva início e duração. Ctrl+Enter ou Cmd+Enter confirma o drawer pelo `v-default-form`; o `DefaultSubmitButton` é a única ação de confirmação. Salvar envia a intenção causal e, após resposta, recarrega o workspace autorizado.
**Validation and Feedback**: o campo aceita somente inteiro de 1 a 3650 e informa `Informe uma duração inteira entre 1 e 3650 dias úteis` no próprio campo. Erro do servidor preserva o rascunho, associa a mensagem ao campo ou à combinação de planejamento e não fecha o drawer. Sucesso mostra o toast existente e a projeção reconciliada. Violação de prazo não impede salvar: aparece na projeção com explicação e orientação para revisar prazo, duração ou relação.
**Responsive/Adaptive Behavior**: início e fim ocupam a mesma grade. Duração ocupa a linha imediatamente seguinte, por inteiro, em todos os form factors. O drawer mantém rolagem, ações de cancelar/salvar acessíveis e não exige hover.
**Accessibility**: rótulo visível e programático, `min=1`, `max=3650`, `step=1`, unidade textual fora do valor e descrição de ajuda associada ao campo. Mensagem de erro usa associação semântica e região de anúncio; a violação também tem texto, não apenas cor. Tab segue título, dados, datas, duração, conclusão, projeção e ações; foco permanece no campo inválido após falha e retorna ao acionador ao fechar. Zoom de 200%, teclado físico e leitores de tela preservam a sequência e a unidade.
**Localization**: interface em pt-BR; rótulo canônico `Duração planejada`, unidade `dias úteis` e datas civis no timezone do projeto. Valores são inteiros sem separador decimal.
**Components and Design System**: reutilizar `DateInput`, drawer, grade de formulário, resumo de projeção, `v-default-form`, `DefaultSubmitButton` e tokens existentes. O campo numérico não introduz nova cor primária; erros usam a semântica de erro já usada pelo editor.
**Integration and Contracts**: consome o workspace e envia `plannedDurationWorkdays` e `planningDriver` pelos endpoints de tarefa definidos em `contracts/tasks-api.md`. A SPA não calcula datas corrigidas, duração resolvida ou violação de planejamento.
**Telemetry**: registrar tipo de intenção (`start`, `finish`, `duration`), sucesso, erro de validação e presença de violação, sem título, ID, datas ou duração da tarefa.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-001.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | drawer fechado | criar ou editar tarefa | loading |
| loading | campos indisponíveis enquanto o workspace carrega | fechar | ready ou remote-error |
| empty | nova tarefa com duração vazia e ajuda visível | preencher ou cancelar | ready |
| ready | valores planejados e projeção autorizada visíveis | editar, limpar, salvar ou cancelar | processing ou validation-error |
| processing | salvar indisponível; rascunho permanece legível | aguardar | success ou remote-error |
| success | toast breve e valores/projeção reconciliados | continuar ou fechar | ready |
| validation-error | erro associado ao campo ou combinação; rascunho preservado | corrigir e salvar | ready |
| remote-error | toast de erro e rascunho preservado | tentar novamente ou cancelar | processing ou ready |
| offline | rascunho preservado e salvar indisponível após falha de conectividade | retentar quando online ou cancelar | ready |
| access-denied | drawer torna-se somente leitura ou fecha conforme sessão global | autenticar ou voltar | initial |
| partial-stale | último snapshot identificado; salvar bloqueado até recarregar | recarregar ou cancelar | loading |

### INT-WEB-002 — Gestos temporais do Gantt

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: manter a duração planejada coerente quando o usuário move ou redimensiona uma barra temporal.
**Actors and Permissions**: proprietário e editor podem usar os gestos em tarefas-folha não concluídas; leitor, seção e tarefa concluída não recebem controles de alteração.
**Entry and Navigation**: hover ou foco revela grips na barra; em touch, selecionar a tarefa revela controles ampliados. Escape cancela e o foco retorna ao grip ou à barra que iniciou o gesto.
**Content and Data**: a barra, o ghost e a prévia exibem início/fim planejados resultantes; a duração planejada é preservada ou recalculada conforme a intenção do gesto. A projeção posterior é sempre recebida do workspace.
**Actions and Behavior**: mover barra preserva duração e envia driver `start`; resize pelo início preserva duração e corrige fim, enviando driver `start`; resize pelo fim recalcula duração e envia driver `finish`. Gestos não preenchem início planejado para tarefa ancorada apenas em fim; essa tarefa não oferece movimento/resize até que um início explícito exista, mas permanece editável pelo drawer. Enter ou Espaço inicia/confirma; setas alteram um dia civil; Escape cancela sem persistência.
**Validation and Feedback**: limites de dependência, calendário e intervalo inválido mantêm o original, removem o ghost e são anunciados. A resposta do servidor substitui a prévia local; erro preserva o intervalo salvo. Violação resultante é apresentada na projeção do editor ao abrir a tarefa e por indicador textual acessível no timeblock.
**Responsive/Adaptive Behavior**: desktop preserva grips e ponteiro; touch aumenta a área de toque e mantém alternativa completa pelo editor. Em qualquer largura, o gesto não exige precisão para alterar a duração porque o campo numérico permanece disponível.
**Accessibility**: grips focáveis têm nomes de início/fim, `aria-valuetext` da data e anúncio da duração resolvida; não dependem da cor do ghost. O indicador de violação possui texto alternativo e não reduz a área útil do timeblock abaixo do mínimo atual.
**Localization**: dias e datas seguem pt-BR e timezone do projeto; `dias úteis` é usado nas mensagens de duração.
**Components and Design System**: reutilizar timeblock, grips, ghost, toast e diretiva de gestos existentes; não criar novo modo de navegação.
**Integration and Contracts**: atualiza a tarefa pelo contrato `tasks-api.md`; o driver do gesto identifica a intenção e o reload do workspace fornece valores normalizados e derivados.
**Telemetry**: registrar modo de gesto, driver, cancelamento, falha e sucesso; excluir identificadores, títulos, datas e valores de duração.
**Wireframe Requirement**: OPTIONAL
**Wireframe**: N/A — a geometria da barra e dos grips existentes não muda; apenas a semântica de persistência é ampliada.

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | grips ocultos até foco, hover ou seleção touch | focar ou selecionar | ready |
| loading | barra legível, sem gesto de escrita | aguardar ou navegar | ready ou remote-error |
| empty | N/A — não há timeblock quando não há tarefa projetada | abrir editor ou voltar | initial |
| ready | barra elegível com controles apropriados | mover, resize ou abrir editor | processing |
| processing | ghost único, prévia de datas e commit bloqueado para outro gesto | confirmar ou cancelar | success, validation-error ou remote-error |
| success | ghost removido, workspace reconciliado e toast breve | continuar | ready |
| validation-error | intervalo original restaurado e motivo anunciado | ajustar ou abrir editor | ready |
| remote-error | intervalo original restaurado e toast de erro | tentar novamente ou abrir editor | ready |
| offline | gesto cancela sem escrita e informa indisponibilidade | retentar após rede | ready |
| access-denied | controles são removidos | autenticar ou voltar | initial |
| partial-stale | gesto de commit bloqueado até reconciliar | recarregar | loading |

## Cross-Surface Rules

### Navigation and Parity

Há apenas a superfície web operacional. O drawer é a alternativa completa e obrigatória para toda alteração disponível por gesto; touch não precisa reproduzir a precisão do ponteiro para ter paridade de negócio.

### Shared Content and Terminology

`Duração planejada` é uma estimativa explícita em `dias úteis`. `Início planejado` e `Fim planejado` são intenções persistidas. `Início considerado` e `Fim considerado` são projeções do core. `Violação de planejamento` significa que prazo, duração e restrições não podem ser atendidos simultaneamente.

### Shared Accessibility and Input

Leitor vê valores e projeção, sem confirmação editável. Editor/proprietário recebem o mesmo feedback por campo, toast e texto de projeção. Todo gesto tem alternativa por teclado e pelo campo do drawer; Ctrl+Enter ou Cmd+Enter só confirma o formulário com foco, respeitando `v-default-form`.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
|---|---|---|---|---|
| INT-WEB-001 | US-001, US-002, US-003, US-004 | FR-001 a FR-007, FR-010 a FR-012 | SC-001 a SC-004 | contracts/tasks-api.md |
| INT-WEB-002 | US-003, US-004 | FR-004 a FR-006, FR-008 a FR-010 | SC-002 a SC-004 | contracts/tasks-api.md |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
|---|---|---|---|
| INT-WEB-001 | REQUIRED | wireframes/int-web-001.md | Campo, unidade, ajuda, projeção e reflow. |
| INT-WEB-002 | OPTIONAL | N/A | Sem alteração estrutural na barra existente. |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: yes
- Placeholders or open decisions remaining: 0
