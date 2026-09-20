# Feature Specification: DependÃªncias e Caminho CrÃ­tico

**Feature**: `dependencies-critical-path`
**Created**: 2026-08-17
**Status**: Draft

## Clarifications

### Session 2026-09-20

- Q: Como uma dependencia recebida por uma secao afeta as tarefas internas? -> A: A secao passa a ter uma janela temporal derivada. Toda tarefa descendente considera o limite de inicio/desbloqueio herdado da secao junto de sua propria data planejada e de suas dependencias diretas; ela so e deslocada quando esse limite for mais restritivo. A secao nao desloca indiscriminadamente todas as tarefas.

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

### User Story 3 - Usar secoes como marcos de precedencia (Priority: P2)

Como planejador, quero relacionar secoes e tarefas para que o intervalo derivado de uma secao possa restringir o inicio ou o termino de outro item.

**Independent Test**: relacionar uma secao a uma tarefa e uma tarefa a uma secao, verificando o intervalo derivado, a precedencia e a validacao da hierarquia.

### User Story 4 - Respeitar a janela de uma secao (Priority: P1)

Como planejador, quero que uma tarefa dentro de uma secao respeite o desbloqueio calculado para a secao sem perder uma data planejada que ja seja posterior, para que o cronograma preserve as restricoes realmente aplicaveis.

**Independent Test**: aplicar uma relacao que desbloqueie uma secao em data posterior a uma tarefa interna e confirmar o deslocamento; repetir com uma tarefa cuja data planejada ja seja posterior e confirmar que ela nao muda.

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
- **FR-005**: Tarefas e secoes DEVEM poder ser predecessoras ou sucessoras em relacoes intraprojeto, preservando a semantica FS, SS, FF e SF.
- **FR-006**: O sistema DEVE calcular caminho crÃ­tico e folga sobre atividades executÃ¡veis, respeitando calendÃ¡rio e grupos derivados.
- **FR-007**: A criticidade DEVE ser recalculada apÃ³s mudanÃ§as que alterem rede, datas, calendÃ¡rio, conclusÃ£o ou simulaÃ§Ã£o.
- **FR-008**: RelaÃ§Ãµes DEVEM permanecer compreensÃ­veis mesmo quando uma ponta estiver oculta por filtro ou recolhimento.
- **FR-009**: Somente uma relação `FS` com predecessora não concluída DEVE produzir status `BLOCKED`; `SS`, `FF` e `SF` continuam aplicando suas restrições temporais sem bloquear o status de disponibilidade.
- **FR-010**: Para projeção, uma predecessora `FS` concluída libera pela data efetiva de conclusão; uma não concluída projeta desbloqueio no primeiro dia útil posterior ao seu deadline considerado.
- **FR-011**: O inicio derivado de uma secao DEVE ser um limite minimo herdado por todas as tarefas descendentes; cada tarefa DEVE usar o maior valor entre sua data base, limites de dependencias diretas e limites de todas as secoes ancestrais.
- **FR-012**: A data planejada de tarefa continua valida apos uma relacao de secao; nenhuma tarefa DEVE ser deslocada quando sua propria data calculada ja satisfaz a janela derivada da secao.
- **FR-013**: Limites de termino de secao DEVEM ser satisfeitos sem alterar tarefas internas que ja possam terminar antes do termino derivado da secao; o sistema DEVE selecionar e explicar os impactos necessarios de forma deterministica.
- **FR-014**: Criar, editar, mover ou excluir secao/tarefa DEVE validar novamente relacoes afetadas, impedindo ciclos e relacoes proibidas pela hierarquia.

### Key Entities

- **Janela de secao**: limites temporais derivados de relacoes recebidas por uma secao e do intervalo calculado de seus descendentes; nao e uma data persistida editavel.
- **Limite herdado**: restricao de inicio ou termino que uma tarefa recebe de cada secao ancestral, combinada com as restricoes diretas da tarefa.

- **DependÃªncia**: relaÃ§Ã£o direcional entre predecessor e sucessor, com tipo de precedÃªncia.
- **Grafo de planejamento**: conjunto acÃ­clico de atividades e relaÃ§Ãµes de um Gantt.
- **Folga**: margem temporal calculada para uma atividade.
- **Caminho crÃ­tico**: rota que define o tÃ©rmino global do planejamento.

## Success Criteria

- **SC-001**: 100% das tentativas de introduzir ciclo, autodependÃªncia ou duplicidade sÃ£o bloqueadas.
- **SC-002**: Para os cenÃ¡rios matemÃ¡ticos aprovados, 100% dos tipos de precedÃªncia produzem a restriÃ§Ã£o esperada em dias Ãºteis.
- **SC-003**: Todo item crÃ­tico Ã© identificÃ¡vel no workspace sem ocultar as demais tarefas.
