# Feature Specification: SincronizaÃ§Ã£o com Todoist

**Feature**: `todoist-synchronization`
**Created**: 2026-08-17
**Status**: Draft

## Interface Coverage

| Surface | Type | Actors | Coverage | Functional Behavior | Excluded or Deferred Behavior |
|---|---|---|---|---|---|
| Workspace operacional | Web/Mobile Web | Planejador | FULL | Exibe estado de sincronizaÃ§Ã£o, atualizaÃ§Ãµes externas e aÃ§Ãµes de recuperaÃ§Ã£o | EdiÃ§Ã£o offline |
| IntegraÃ§Ã£o entre sistemas | Other | Todoist | FULL | Recebe alteraÃ§Ãµes, reconcilia dados e propaga resultado aos clientes conectados | SincronizaÃ§Ã£o com outros provedores |

## User Scenarios & Testing

### User Story 1 - Ver mudanÃ§as externas (Priority: P1)

Como planejador, quero que alteraÃ§Ã£o feita no Todoist apareÃ§a no meu Gantt sem recarregar a pÃ¡gina.

**Independent Test**: alterar tÃ­tulo, data, hierarquia ou conclusÃ£o externamente e confirmar reconciliaÃ§Ã£o e atualizaÃ§Ã£o visÃ­vel do Gantt aberto.

### User Story 2 - Trabalhar com falha temporÃ¡ria (Priority: P1)

Como planejador, quero saber se uma alteraÃ§Ã£o ainda estÃ¡ pendente e se foi recuperada, sem assumir que ela foi perdida.

**Independent Test**: induzir indisponibilidade temporÃ¡ria durante alteraÃ§Ã£o e verificar estado pendente, nova tentativa, reconciliaÃ§Ã£o e mensagem acionÃ¡vel.

### User Story 3 - Resolver conflito (Priority: P2)

Como planejador, quero que mudanÃ§as concorrentes sejam detectadas e apresentadas de modo que eu nÃ£o sobrescreva dados sem saber.

**Independent Test**: modificar a mesma informaÃ§Ã£o por dois contextos e verificar estado de conflito, dado final reconciliado e histÃ³rico da decisÃ£o.

### Edge Cases

- Evento duplicado, atrasado ou gerado pela prÃ³pria aplicaÃ§Ã£o nÃ£o duplica efeito.
- Tarefa movida para outro projeto nÃ£o Ã© tratada como exclusÃ£o definitiva sem confirmaÃ§Ã£o/regra aplicÃ¡vel.
- Indisponibilidade externa preserva consulta do Ãºltimo estado conhecido com aviso de degradaÃ§Ã£o.

## Requirements

### Functional Requirements

- **FR-001**: O sistema DEVE reconciliar alteraÃ§Ãµes externas de tarefas, hierarquia, datas, conclusÃ£o e projetos associados.
- **FR-002**: Clientes conectados DEVEM receber atualizaÃ§Ã£o do estado reconciliado sem recarga completa.
- **FR-003**: AlteraÃ§Ãµes iniciadas pelo usuÃ¡rio DEVEM ter estado explÃ­cito atÃ© confirmaÃ§Ã£o, falha definitiva ou recuperaÃ§Ã£o.
- **FR-004**: Eventos duplicados, fora de ordem e ecos de alteraÃ§Ãµes prÃ³prias NÃƒO DEVEM produzir efeito duplicado.
- **FR-005**: Falhas temporÃ¡rias DEVEM permitir nova tentativa e reconciliaÃ§Ã£o; falhas definitivas DEVEM informar aÃ§Ã£o necessÃ¡ria.
- **FR-006**: Conflitos DEVEM ser detectados, rastreÃ¡veis e apresentados sem sobrescrita silenciosa.
- **FR-007**: MudanÃ§a de projeto e exclusÃ£o externa DEVEM receber tratamento distinto para dependÃªncias e metadados.
- **FR-008**: O sistema DEVE reconciliar diferenÃ§as apÃ³s indisponibilidade, inclusive quando eventos nÃ£o foram recebidos.

### Key Entities

- **Estado de sincronizaÃ§Ã£o**: condiÃ§Ã£o atual de leitura/escrita e recuperaÃ§Ã£o de um item ou operaÃ§Ã£o.
- **Evento externo**: notificaÃ§Ã£o de mudanÃ§a originada no Todoist.
- **ReconciliaÃ§Ã£o**: comparaÃ§Ã£o e aplicaÃ§Ã£o do estado externo confiÃ¡vel.
- **Conflito**: alteraÃ§Ã£o concorrente que exige tratamento explÃ­cito.

## Success Criteria

- **SC-001**: Eventos repetidos nÃ£o criam mudanÃ§as duplicadas em 100% dos testes de idempotÃªncia.
- **SC-002**: ApÃ³s recuperar integraÃ§Ã£o temporariamente indisponÃ­vel, os projetos afetados convergem para o estado externo sem perder operaÃ§Ãµes rastreÃ¡veis.
- **SC-003**: UsuÃ¡rios conseguem identificar se uma alteraÃ§Ã£o estÃ¡ sincronizada, pendente, em conflito ou falhou.
