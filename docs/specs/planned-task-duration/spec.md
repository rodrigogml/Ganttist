# Feature Specification: Duração Planejada da Tarefa

**Feature**: `planned-task-duration`
**Created**: 2026-09-21
**Status**: Draft

## User Scenarios & Testing

### User Story 1 - Estimar trabalho sem agendar o início (Priority: P1)

Como planejador, quero informar a duração prevista de uma tarefa mesmo sem data de início ou fim, para estimar o esforço e permitir que ela participe corretamente do planejamento futuro.

**Why this priority**: elimina a perda da estimativa quando a data ainda é incerta, que é o problema central da feature.

**Independent Test**: criar uma tarefa sem datas, informar duração de cinco dias úteis e confirmar que a estimativa permanece visível e é usada nas projeções, sem preencher uma data planejada automaticamente.

**Acceptance Scenarios**:

1. **Given** uma tarefa sem datas planejadas, **When** o planejador informa uma duração válida, **Then** a duração é preservada e as duas datas planejadas permanecem vazias.
2. **Given** uma tarefa com duração e sem datas planejadas, **When** ela não possui bloqueio `FS`, **Then** seu status permanece aberto e sua projeção usa a duração sem transformar sua data virtual em data planejada.

---

### User Story 2 - Planejar pelo prazo de entrega (Priority: P1)

Como planejador, quero informar duração e data final sem informar início, para prever quando o trabalho precisa começar e analisar relações que restringem seu término.

**Why this priority**: permite modelar compromissos de entrega quando o início depende de decisões ou relações ainda em aberto.

**Independent Test**: informar fim em uma sexta-feira e duração de três dias úteis sem início; confirmar que o início considerado é calculado retroativamente, respeitando o calendário, sem gravar início planejado.

**Acceptance Scenarios**:

1. **Given** uma tarefa sem início, com fim planejado e duração válida, **When** o planejamento é calculado, **Then** o sistema calcula seu início considerado para trás a partir do fim, em dias úteis.
2. **Given** a mesma tarefa participa de relação `FF` ou `SF`, **When** a relação é avaliada, **Then** sua duração é considerada na restrição de término e na projeção resultante.

---

### User Story 3 - Manter os parâmetros coerentes ao editar (Priority: P1)

Como planejador, quero que início, fim e duração sejam conciliados previsivelmente ao editar uma tarefa, para não deixar uma estimativa contradizer as datas planejadas.

**Why this priority**: a tarefa pode ter três informações relacionadas e precisa manter uma única versão coerente do planejamento.

**Independent Test**: editar separadamente duração, fim e início de uma tarefa com os três valores; confirmar a correção esperada após cada edição e a rejeição de valores inválidos.

**Acceptance Scenarios**:

1. **Given** início e fim planejados, **When** o fim é alterado, **Then** a duração é recalculada pelo calendário do projeto.
2. **Given** início planejado e duração, **When** a duração é alterada, **Then** o fim planejado é corrigido para preservar a duração útil.
3. **Given** início, fim e duração, **When** o início é alterado, **Then** o fim é corrigido para preservar a duração informada.
4. **Given** fim e duração sem início, **When** a duração ou o fim é alterado, **Then** os valores permanecem válidos e o início considerado é recalculado, sem preencher início planejado.

---

### User Story 4 - Confiar no Gantt e nos indicadores (Priority: P2)

Como gestor, quero que duração planejada afete datas calculadas, caminho crítico, folga, grupos e status, para que o Gantt represente o esforço previsto mesmo quando faltam datas explícitas.

**Why this priority**: estimativas que não chegam ao cálculo induzem decisões de cronograma incorretas.

**Independent Test**: alterar a duração de uma predecessora e confirmar a atualização determinística das sucessoras, do intervalo de grupo e da criticidade, sem alteração implícita das datas planejadas que não foram editadas.

**Acceptance Scenarios**:

1. **Given** uma tarefa com duração explícita e dependentes, **When** sua duração muda, **Then** as datas consideradas, folgas, criticidade e status afetados são recalculados.
2. **Given** uma seção com descendentes de duração explícita, **When** o intervalo derivado é calculado, **Then** ele usa as projeções dessas tarefas.

### Edge Cases

- Duração zero, negativa, decimal, ausente ou fora do intervalo de 1 a 3650 dias úteis é rejeitada sem alterar a tarefa.
- O cálculo de duração e de datas respeita dias úteis, exceções e timezone do calendário do projeto.
- Uma data final anterior à inicial continua inválida quando ambas são planejadas explicitamente.
- Ao limpar uma data, a duração explícita e a outra data permanecem válidas quando existentes.
- Uma tarefa concluída preserva sua data efetiva de conclusão e não é deslocada por nova duração ou dependência.
- Quando uma restrição torna incompatíveis duração e fim planejado, o sistema deve expor a violação de planejamento, sem reduzir silenciosamente a duração nem regravar a data final.

## Requirements

### Functional Requirements

- **FR-001**: O sistema DEVE permitir que uma tarefa possua uma duração planejada inteira de 1 a 3650 dias úteis, independentemente de possuir início ou fim planejados.
- **FR-002**: O sistema DEVE permitir a combinação duração + fim planejado sem exigir início planejado.
- **FR-003**: Quando início e fim planejados estiverem presentes, o sistema DEVE manter a duração coerente com o calendário do projeto.
- **FR-004**: Ao alterar duração com início planejado, o sistema DEVE corrigir o fim planejado; ao alterar fim com início planejado, DEVE corrigir a duração.
- **FR-005**: Ao alterar início quando houver duração planejada, o sistema DEVE corrigir o fim planejado para preservar a duração.
- **FR-006**: O sistema DEVE distinguir datas planejadas explicitamente de datas consideradas; nenhum cálculo pode preencher, substituir ou persistir uma data planejada sem comando explícito do usuário.
- **FR-007**: Uma tarefa com fim planejado e duração, mas sem início planejado, DEVE receber início considerado retroativo baseado no calendário e na duração.
- **FR-008**: Duração planejada DEVE participar de todas as projeções de dependência `FS`, `SS`, `FF` e `SF`, de grupos, folga e caminho crítico.
- **FR-009**: Alterar início, fim, duração, calendário, dependência, conclusão ou hierarquia relevante DEVE recalcular as projeções afetadas de forma determinística.
- **FR-010**: O status calculado DEVE obedecer à precedência `CONCLUÍDA > BLOQUEADA > AGENDADA > ATRASADA > EM_ANDAMENTO > ABERTA`; duração isolada não transforma uma tarefa aberta em tarefa em andamento.
- **FR-011**: A interface DEVE explicar que duração é contada em dias úteis e informar ao usuário quando uma restrição de relacionamento tornar incompatíveis sua duração e seu prazo planejado.
- **FR-012**: Criação, edição, duplicação, leitura por API e carregamento do workspace DEVEM preservar a duração planejada sem alterar o significado das tarefas legadas sem duração explícita.

> Decisões de infraestrutura: N/A (feature de domínio local, sem scheduler, tokens ou nova integração externa).

### Key Entities

- **Duração planejada**: estimativa explícita de dias úteis necessários para concluir uma tarefa; pode existir com ou sem datas planejadas.
- **Início e fim planejados**: compromissos explicitamente informados pelo planejador, que podem estar ausentes de forma independente.
- **Data considerada**: data usada pelo planejamento e Gantt após aplicar duração, calendário, relações e política de projeção; não substitui a data planejada.
- **Violação de planejamento**: situação visível em que as restrições aplicáveis não permitem cumprir simultaneamente a duração e o prazo planejados.

## Success Criteria

### Measurable Outcomes

- **SC-001**: 100% das tarefas podem preservar uma duração válida sem que o usuário informe data de início ou fim.
- **SC-002**: Em todos os cenários de início/fim/duração, o sistema não persiste intervalo com fim anterior ao início nem duração inválida.
- **SC-003**: Para um mesmo calendário, data de referência e grafo de dependências, 100% das projeções de datas, status, folga e criticidade são determinísticas.
- **SC-004**: Alterar duração de uma tarefa atualiza, no primeiro recarregamento do workspace, todas as projeções afetadas sem preencher datas planejadas que estavam ausentes.
