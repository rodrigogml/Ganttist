# Feature Specification: OperaÃ§Ãµes e Reagendamento

**Feature**: `rescheduling-operations`
**Created**: 2026-08-17
**Status**: Draft

## Interface Coverage

| Surface | Type | Actors | Coverage | Functional Behavior | Excluded or Deferred Behavior |
|---|---|---|---|---|---|
| Workspace operacional | Web/Mobile Web | Planejador | FULL | Cria e edita tarefas, move/redimensiona datas, simula e confirma operaÃ§Ãµes | Undo genÃ©rico e planejamento em horas |
| Painel de ediÃ§Ã£o | Web/Mobile Web | Planejador | FULL | Edita dados permitidos e mostra impacto/sincronizaÃ§Ã£o | Campos prÃ³prios fora do escopo do MVP |

## User Scenarios & Testing

### User Story 1 - Ajustar uma atividade (Priority: P1)

Como planejador, quero mover ou redimensionar uma atividade em dias Ãºteis, preservando sua duraÃ§Ã£o quando aplicÃ¡vel.

**Independent Test**: mover uma barra e redimensionar suas bordas, verificando que o calendÃ¡rio e as regras de duraÃ§Ã£o sÃ£o respeitados.

### User Story 2 - Simular uma cascata antes de aplicar (Priority: P1)

Como planejador, quero prÃ©-visualizar as tarefas afetadas por uma alteraÃ§Ã£o para decidir se confirmo o reagendamento.

**Independent Test**: alterar atividade predecessora em modo manual e confirmar que ghosts mostram todas as consequÃªncias sem alterar dados persistidos; confirmar e verificar aplicaÃ§Ã£o.

### User Story 3 - Manter a rota ao alterar tarefas (Priority: P2)

Como planejador, quero inserir ou excluir uma tarefa no meio de uma rota preservando as dependÃªncias quando eu escolher continuidade.

**Independent Test**: excluir tarefa intermediÃ¡ria com continuidade e verificar reconstruÃ§Ã£o vÃ¡lida; repetir sem continuidade e verificar remoÃ§Ã£o das relaÃ§Ãµes afetadas.

### Edge Cases

- Escrita em dia bloqueado Ã© impedida ou encaixada conforme regra de calendÃ¡rio.
- ConclusÃ£o, reabertura e exclusÃ£o externa distinguem data efetiva, tarefa removida e tarefa movida de projeto.
- Falha parcial apÃ³s confirmaÃ§Ã£o deixa operaÃ§Ã£o identificÃ¡vel, recuperÃ¡vel e visÃ­vel.

## Requirements

### Functional Requirements

- **FR-001**: O usuÃ¡rio DEVE criar, editar, concluir, reabrir e excluir tarefas a partir do planejamento, preservando a autoridade dos dados nativos.
- **FR-002**: MovimentaÃ§Ã£o e redimensionamento DEVEM operar somente em dias inteiros vÃ¡lidos e respeitar calendÃ¡rio e precedÃªncias.
- **FR-003**: MudanÃ§a explÃ­cita de inÃ­cio DEVE preservar duraÃ§Ã£o Ãºtil e recalcular o fim; mudanÃ§a de fim DEVE recalcular duraÃ§Ã£o vÃ¡lida.
- **FR-004**: Modo manual DEVE apresentar simulaÃ§Ã£o antes de persistir alteraÃ§Ãµes calculadas; modo automÃ¡tico DEVE aplicar alteraÃ§Ãµes imediatamente e informar o resultado.
- **FR-005**: Reagendamento em cascata DEVE respeitar a ordem de precedÃªncia, preservar duraÃ§Ã£o e nÃ£o deslocar tarefa para trÃ¡s por dependÃªncia.
- **FR-006**: O usuÃ¡rio DEVE poder cancelar simulaÃ§Ã£o sem alterar dados persistidos.
- **FR-007**: ExclusÃ£o e inserÃ§Ã£o em rota DEVEM oferecer escolha explÃ­cita e preview quando afetarem relaÃ§Ãµes existentes.
- **FR-008**: O sistema DEVE mostrar estado de aplicaÃ§Ã£o, sucesso, falha temporÃ¡ria, falha definitiva e recuperaÃ§Ã£o de uma operaÃ§Ã£o composta.

### Key Entities

- **OperaÃ§Ã£o de planejamento**: intenÃ§Ã£o do usuÃ¡rio e seus itens afetados, incluindo estado e resultado.
- **SimulaÃ§Ã£o**: resultado nÃ£o persistido de uma alteraÃ§Ã£o e sua cascata.
- **Ghost**: representaÃ§Ã£o visual de mudanÃ§a proposta.
- **Continuidade de rota**: reconstruÃ§Ã£o opcional de precedÃªncias apÃ³s inserÃ§Ã£o ou remoÃ§Ã£o.

## Success Criteria

- **SC-001**: No modo manual, 100% das mudanÃ§as em cascata sÃ£o visÃ­veis antes da persistÃªncia.
- **SC-002**: Cancelar uma simulaÃ§Ã£o nÃ£o altera nenhuma data ou relaÃ§Ã£o persistida.
- **SC-003**: Em cenÃ¡rios aprovados de cascata, todas as tarefas afetadas preservam duraÃ§Ã£o Ãºtil e respeitam suas predecessoras apÃ³s aplicaÃ§Ã£o.
