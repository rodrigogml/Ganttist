# Feature Specification: Workspace de Projeto Gantt

**Feature**: `gantt-workspace`
**Created**: 2026-08-17
**Status**: Draft

## Clarifications

### Session 2026-08-20

- Q: Quais tipos de dependência impedem uma tarefa de assumir estado disponível? -> A: Somente dependências FS bloqueiam até a conclusão da predecessora; SS, FF e SF restringem o planejamento, mas não o estado de disponibilidade.
- Q: Qual data usar quando uma predecessora concluída não possui data efetiva disponível? -> A: Usar, em ordem, o override do Ganttist, o `completed_at` do Todoist convertido para o fuso do projeto e, como último fallback, a data de hoje.
- Q: O incremento após o deadline considerado de uma predecessora FS não concluída usa dia civil ou calendário do projeto? -> A: Usar o próximo dia útil definido pelo calendário do projeto.
- Q: Como tratar o deadline quando o desbloqueio calculado desloca a data inicial? -> A: Oferecer uma política configurável por Gantt, usando por padrão `PRESERVE_DURATION` para deslocar o deadline calculado e preservar a duração útil, ou `PRESERVE_DEADLINE` para manter o prazo de entrega; os dois modos afetam apenas a projeção e não escrevem no Todoist.
- Q: Como projetar `PRESERVE_DEADLINE` quando a data de desbloqueio ultrapassa o deadline de origem? -> A: O deadline considerado deve ser no mínimo igual à data considerada; nesse caso ambos ficam na data de desbloqueio e formam um timeblock de um dia, sem alterar o deadline explícito no Todoist.

## Interface Coverage

| Surface | Type | Actors | Coverage | Functional Behavior | Excluded or Deferred Behavior |
|---|---|---|---|---|---|
| Workspace operacional | Web/Mobile Web | UsuÃ¡rio conectado | FULL | Escolhe projeto, abre Gantt e vÃª Ã¡rvore e timeline sincronizadas | MÃºltiplos projetos em um mesmo Gantt |
| SeleÃ§Ã£o inicial | Web/Mobile Web | UsuÃ¡rio conectado | FULL | Identifica ausÃªncia de integraÃ§Ã£o, projeto vazio e projetos disponÃ­veis | AdministraÃ§Ã£o de contas |

## User Scenarios & Testing

### User Story 1 - Criar e abrir um Gantt (Priority: P1)

Como usuÃ¡rio com conta conectada, quero escolher um projeto para abrir seu planejamento sem recriar suas tarefas.

**Independent Test**: selecionar um projeto com seÃ§Ãµes, tarefas e subtarefas e confirmar que um Ãºnico Gantt Ã© associado a ele para o usuÃ¡rio.

**Acceptance Scenarios**:

1. **Given** conta conectada e projetos disponÃ­veis, **When** o usuÃ¡rio escolhe um projeto, **Then** o sistema cria ou abre o Gantt correspondente e carrega sua hierarquia.
2. **Given** o mesmo projeto jÃ¡ associado ao usuÃ¡rio, **When** ele o escolhe novamente, **Then** abre o mesmo Gantt, sem duplicar configuraÃ§Ãµes ou dependÃªncias.

### User Story 2 - Entender a hierarquia e o tempo (Priority: P1)

Como planejador, quero ver seÃ§Ãµes, tarefas e subtarefas na ordem original, alinhadas Ã  representaÃ§Ã£o temporal quando houver planejamento.

**Independent Test**: abrir projeto com grupos, folhas e tarefas sem data e verificar ordem, expandir/recolher e representaÃ§Ã£o adequada de cada tipo.

**Acceptance Scenarios**:

1. **Given** uma tarefa sem data, **When** o Gantt Ã© exibido, **Then** ela permanece na posiÃ§Ã£o original da Ã¡rvore, Ã© identificada como nÃ£o programada e recebe somente um timeblock provisÃ³rio de um dia em hoje, sem persistÃªncia atÃ© uma aÃ§Ã£o explÃ­cita.
2. **Given** uma tarefa com descendentes planejados, **When** o Gantt Ã© exibido, **Then** ela aparece como resumo derivado e nÃ£o como atividade comum editÃ¡vel.

### User Story 3 - Trabalhar sem recarga completa (Priority: P2)

Como usuÃ¡rio, quero que a abertura, seleÃ§Ã£o e atualizaÃ§Ã£o visÃ­vel do Gantt preservem meu contexto de trabalho.

**Independent Test**: abrir um projeto, expandir uma ramificaÃ§Ã£o e receber uma alteraÃ§Ã£o aplicÃ¡vel sem recarregar a pÃ¡gina inteira.

### Edge Cases

- Projeto vazio informa que nÃ£o hÃ¡ tarefas sem criar estrutura artificial.
- Tarefa movida para outro projeto deixa de compor o Gantt anterior e requer tratamento explÃ­cito de suas relaÃ§Ãµes.
- Projeto indisponÃ­vel ou integraÃ§Ã£o desconectada apresenta estado recuperÃ¡vel, sem eliminar metadados locais.

## Requirements

### Functional Requirements

- **FR-001**: O sistema DEVE associar cada Gantt a exatamente um projeto externo por usuÃ¡rio.
- **FR-002**: O workspace DEVE preservar seÃ§Ãµes, ordem e hierarquia completa do projeto selecionado.
- **FR-003**: O usuÃ¡rio DEVE poder expandir e recolher nÃ­veis sem alterar a hierarquia de origem.
- **FR-004**: Tarefas sem data DEVEM permanecer visÃ­veis na Ã¡rvore e diferenciadas de tarefas planejadas.
- **FR-005**: Tarefas-pai DEVEM ser apresentadas como grupos derivados quando possuÃ­rem descendentes planejados.
- **FR-006**: O workspace DEVE informar estados de carregamento, vazio, degradaÃ§Ã£o e erro com aÃ§Ã£o de recuperaÃ§Ã£o quando possÃ­vel.
- **FR-007**: AlteraÃ§Ãµes de outros fluxos DEVEM atualizar o contexto aberto sem exigir recarga completa.
- **FR-008**: Somente uma dependência `FS` cuja predecessora não esteja concluída DEVE tornar a sucessora bloqueada; dependências `SS`, `FF` e `SF` DEVEM continuar restringindo datas conforme sua semântica, sem exigir conclusão da predecessora para liberar o estado da sucessora.
- **FR-009**: Para calcular o desbloqueio produzido por uma predecessora concluída, a data efetiva de conclusão DEVE ser resolvida, em ordem de precedência, pelo override próprio do Ganttist, pelo `completed_at` do Todoist convertido em data civil no fuso do projeto e pela data civil corrente quando as fontes anteriores estiverem ausentes.
- **FR-010**: A data de desbloqueio produzida por uma predecessora `FS` não concluída DEVE ser o primeiro dia útil do calendário do projeto posterior ao deadline considerado dessa predecessora.
- **FR-011**: Cada Gantt DEVE possuir política de projeção para deslocamentos por desbloqueio: `PRESERVE_DURATION`, padrão que desloca o deadline calculado pela mesma quantidade de dias úteis aplicada ao início, e `PRESERVE_DEADLINE`, que mantém o prazo de entrega como restrição da projeção.
- **FR-012**: Datas de início, desbloqueio e deadline produzidas por essas regras DEVEM ser exclusivamente calculadas para projeção e exibição; o sistema NÃO DEVE gravá-las automaticamente no Todoist, ficando uma eventual aplicação dos valores fora do escopo desta feature.
- **FR-013**: Antes de avaliar dependências, o core DEVE definir a data-base como a data explícita ou hoje quando ausente, e o deadline-base como o deadline explícito válido ou a data-base quando ausente ou anterior à data-base.
- **FR-014**: Para cada tarefa, a data de desbloqueio DEVE ser a maior data produzida por suas predecessoras `FS`: data efetiva de conclusão para predecessora concluída e primeiro dia útil posterior ao deadline considerado para predecessora não concluída.
- **FR-015**: A data considerada DEVE ser a maior entre a data-base e a data de desbloqueio. Em `PRESERVE_DURATION`, o deadline considerado DEVE preservar a duração útil entre data-base e deadline-base. Em `PRESERVE_DEADLINE`, o deadline considerado DEVE ser o maior entre deadline-base e data considerada, produzindo duração mínima de um dia quando o desbloqueio ultrapassar o prazo de origem.
- **FR-016**: O status calculado de uma tarefa DEVE obedecer estritamente à seguinte precedência: `COMPLETED` quando concluída; `BLOCKED` quando não concluída e alguma predecessora `FS` não estiver concluída; `SCHEDULED` quando desbloqueada e sua data considerada for posterior a hoje; `LATE` quando desbloqueada, não agendada para o futuro e seu deadline considerado for anterior a hoje; `OPENED` nos demais casos desbloqueados.
- **FR-017**: Os status calculados e as datas consideradas DEVEM ser projeções determinísticas do core e NÃO DEVEM ser tratados como campos nativos nem provocar escrita automática no Todoist.

### Key Entities

- **Gantt**: configuraÃ§Ã£o e contexto de planejamento associado a um projeto externo.
- **Projeto externo**: agrupador de seÃ§Ãµes e tarefas escolhido pelo usuÃ¡rio.
- **Item hierÃ¡rquico**: seÃ§Ã£o, tarefa ou subtarefa preservada na ordem de origem.
- **Grupo**: tarefa com descendentes, cujo intervalo Ã© derivado das atividades planejadas abaixo dela.
- **Dependência bloqueadora**: relação `FS` cuja predecessora ainda não está concluída; relações `SS`, `FF` e `SF` não são bloqueadoras de estado.
- **Data efetiva de conclusão**: data civil usada para desbloqueio, obtida pelo override do Ganttist, pelo registro de conclusão do Todoist no fuso do projeto ou, na ausência de ambos, por hoje.
- **Política de projeção por desbloqueio**: configuração do Gantt que escolhe entre preservar a duração útil calculada ou preservar o prazo de entrega quando uma dependência desloca o início.
- **Data-base e deadline-base**: valores normalizados a partir dos campos explícitos do Todoist, usando hoje para data ausente e garantindo deadline-base nunca anterior à data-base.
- **Data de desbloqueio**: maior data calculada entre as predecessoras `FS`, determinando quando a tarefa pode começar segundo o grafo.
- **Data e deadline considerados**: intervalo virtual resultante da normalização, do desbloqueio e da política do Gantt, usado somente no cálculo e na exibição.
- **Status calculado**: um dos estados mutuamente exclusivos `COMPLETED`, `BLOCKED`, `SCHEDULED`, `LATE` ou `OPENED`, resolvido pela precedência definida no core.

## Success Criteria

- **SC-001**: Para cada projeto selecionado, o usuÃ¡rio encontra 100% dos itens recebidos na hierarquia original, inclusive os nÃ£o programados.
- **SC-002**: A seleÃ§Ã£o repetida do mesmo projeto pelo mesmo usuÃ¡rio nÃ£o cria mais de um Gantt correspondente.
- **SC-003**: UsuÃ¡rios conseguem identificar, sem consultar outra tela, se cada item visÃ­vel Ã© nÃ£o programado, atividade planejada ou grupo derivado.
- **SC-004**: Para um mesmo snapshot, calendário, política e data de hoje controlada, 100% das execuções produzem as mesmas datas consideradas, datas de desbloqueio e status.
- **SC-005**: Nenhum cálculo ou exibição de datas consideradas altera data ou deadline no Todoist sem uma futura ação explícita fora do escopo desta feature.
