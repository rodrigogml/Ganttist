# Feature Specification: DependÃªncias e Caminho CrÃ­tico

**Feature**: `dependencies-critical-path`
**Created**: 2026-08-17
**Status**: Draft

## Interface Coverage

| Surface | Type | Actors | Coverage | Functional Behavior | Excluded or Deferred Behavior |
|---|---|---|---|---|---|
| Workspace operacional | Web/Mobile Web | Planejador | FULL | Cria, inspeciona, altera e remove relaÃ§Ãµes; identifica criticidade | RelaÃ§Ãµes entre projetos, lag/lead e grupos sucessores |

## User Scenarios & Testing

### User Story 1 - Definir a sequÃªncia de trabalho (Priority: P1)

Como planejador, quero conectar tarefas por precedÃªncia para que o cronograma respeite a ordem real de execuÃ§Ã£o.

**Independent Test**: criar cada tipo de relaÃ§Ã£o entre duas atividades planejadas e verificar a restriÃ§Ã£o temporal observÃ¡vel.

**Acceptance Scenarios**:

1. **Given** duas tarefas no mesmo Gantt, **When** o usuÃ¡rio cria relaÃ§Ã£o FS, SS, FF ou SF vÃ¡lida, **Then** a relaÃ§Ã£o Ã© exibida e influencia a data mÃ­nima aplicÃ¡vel.
2. **Given** tentativa de criar ciclo, autodependÃªncia ou relaÃ§Ã£o duplicada, **When** o usuÃ¡rio confirma, **Then** a relaÃ§Ã£o nÃ£o Ã© criada e o motivo Ã© informado.

### User Story 2 - Identificar rota crÃ­tica (Priority: P1)

Como planejador, quero ver quais atividades definem a data final para priorizar riscos do projeto.

**Independent Test**: abrir uma rede de atividades com uma rota sem folga e confirmar indicaÃ§Ã£o coerente de criticidade e folga.

### User Story 3 - Usar grupos como marcos de precedÃªncia (Priority: P2)

Como planejador, quero usar um grupo como predecessor quando seu escopo inteiro precisar terminar antes de uma atividade.

**Independent Test**: relacionar grupo a atividade comum e verificar referÃªncia ao fim derivado do grupo; tentar grupo como sucessor e confirmar bloqueio.

### Edge Cases

- MÃºltiplas predecessoras aplicam a restriÃ§Ã£o mais forte.
- RelaÃ§Ãµes com item filtrado ou grupo recolhido permanecem detectÃ¡veis e inspecionÃ¡veis.
- Atividades concluÃ­das preservam a data efetiva de conclusÃ£o para as regras de precedÃªncia e criticidade aplicÃ¡veis.

## Requirements

### Functional Requirements

- **FR-001**: O sistema DEVE suportar FS, SS, FF e SF entre atividades do mesmo Gantt.
- **FR-002**: O sistema DEVE bloquear ciclos, autodependÃªncias, duplicatas e relaÃ§Ãµes proibidas.
- **FR-003**: Cada nova relaÃ§Ã£o DEVE ser validada antes de persistir e informar restriÃ§Ãµes ou inconsistÃªncias existentes.
- **FR-004**: MÃºltiplas predecessoras DEVEM resultar na data mais restritiva compatÃ­vel com o calendÃ¡rio.
- **FR-005**: Grupos DEVEM ser aceitos apenas como predecessores de atividades comuns.
- **FR-006**: O sistema DEVE calcular caminho crÃ­tico e folga sobre atividades executÃ¡veis, respeitando calendÃ¡rio e grupos derivados.
- **FR-007**: A criticidade DEVE ser recalculada apÃ³s mudanÃ§as que alterem rede, datas, calendÃ¡rio, conclusÃ£o ou simulaÃ§Ã£o.
- **FR-008**: RelaÃ§Ãµes DEVEM permanecer compreensÃ­veis mesmo quando uma ponta estiver oculta por filtro ou recolhimento.
- **FR-009**: Somente uma relação `FS` com predecessora não concluída DEVE produzir status `BLOCKED`; `SS`, `FF` e `SF` continuam aplicando suas restrições temporais sem bloquear o status de disponibilidade.
- **FR-010**: Para projeção, uma predecessora `FS` concluída libera pela data efetiva de conclusão; uma não concluída projeta desbloqueio no primeiro dia útil posterior ao seu deadline considerado.

### Key Entities

- **DependÃªncia**: relaÃ§Ã£o direcional entre predecessor e sucessor, com tipo de precedÃªncia.
- **Grafo de planejamento**: conjunto acÃ­clico de atividades e relaÃ§Ãµes de um Gantt.
- **Folga**: margem temporal calculada para uma atividade.
- **Caminho crÃ­tico**: rota que define o tÃ©rmino global do planejamento.

## Success Criteria

- **SC-001**: 100% das tentativas de introduzir ciclo, autodependÃªncia ou duplicidade sÃ£o bloqueadas.
- **SC-002**: Para os cenÃ¡rios matemÃ¡ticos aprovados, 100% dos tipos de precedÃªncia produzem a restriÃ§Ã£o esperada em dias Ãºteis.
- **SC-003**: Todo item crÃ­tico Ã© identificÃ¡vel no workspace sem ocultar as demais tarefas.
