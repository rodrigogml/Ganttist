# Feature Specification: Workspace de Projeto Gantt

**Feature**: `gantt-workspace`
**Created**: 2026-08-17
**Status**: Draft

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

1. **Given** uma tarefa sem data, **When** o Gantt Ã© exibido, **Then** ela permanece na posiÃ§Ã£o original da Ã¡rvore, Ã© identificada como nÃ£o programada e nÃ£o recebe barra temporal persistida.
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

### Key Entities

- **Gantt**: configuraÃ§Ã£o e contexto de planejamento associado a um projeto externo.
- **Projeto externo**: agrupador de seÃ§Ãµes e tarefas escolhido pelo usuÃ¡rio.
- **Item hierÃ¡rquico**: seÃ§Ã£o, tarefa ou subtarefa preservada na ordem de origem.
- **Grupo**: tarefa com descendentes, cujo intervalo Ã© derivado das atividades planejadas abaixo dela.

## Success Criteria

- **SC-001**: Para cada projeto selecionado, o usuÃ¡rio encontra 100% dos itens recebidos na hierarquia original, inclusive os nÃ£o programados.
- **SC-002**: A seleÃ§Ã£o repetida do mesmo projeto pelo mesmo usuÃ¡rio nÃ£o cria mais de um Gantt correspondente.
- **SC-003**: UsuÃ¡rios conseguem identificar, sem consultar outra tela, se cada item visÃ­vel Ã© nÃ£o programado, atividade planejada ou grupo derivado.
