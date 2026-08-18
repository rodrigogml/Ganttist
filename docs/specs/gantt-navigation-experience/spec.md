# Feature Specification: NavegaÃ§Ã£o e ExperiÃªncia Gantt

**Feature**: `gantt-navigation-experience`
**Created**: 2026-08-17
**Status**: Draft

## Interface Coverage

| Surface | Type | Actors | Coverage | Functional Behavior | Excluded or Deferred Behavior |
|---|---|---|---|---|---|
| Desktop e telas grandes | Web | Planejador | FULL | Pesquisa, filtra, navega, ajusta Ã¡rea de trabalho e opera timeline densa | Navegadores legados |
| Tablet e celular | Mobile Web | Planejador | FULL | Executa as funÃ§Ãµes essenciais com controles, toque e painÃ©is adaptados | Paridade de layout pixel a pixel |

## User Scenarios & Testing

### User Story 1 - Encontrar e focar uma tarefa (Priority: P1)

Como planejador, quero buscar ou filtrar tarefas e ir diretamente ao seu contexto na hierarquia e na timeline.

**Independent Test**: pesquisar item dentro de grupo recolhido e confirmar foco, expansÃ£o necessÃ¡ria e indicaÃ§Ã£o quando o resultado estiver temporalmente fora da viewport.

### User Story 2 - Reduzir ruÃ­do sem perder contexto (Priority: P1)

Como planejador, quero ocultar concluÃ­das e aplicar filtros sem apagar grupos ou esconder silenciosamente relaÃ§Ãµes relevantes.

**Independent Test**: filtrar tarefas com dependÃªncia para item oculto e confirmar que a relaÃ§Ã£o Ã© indicada e pode ser inspecionada.

### User Story 3 - Operar em diferentes dispositivos (Priority: P2)

Como planejador, quero usar as principais funÃ§Ãµes no desktop, tablet e celular com teclado ou toque conforme disponÃ­vel.

**Independent Test**: executar seleÃ§Ã£o, busca, ediÃ§Ã£o, zoom e criaÃ§Ã£o de dependÃªncia em viewport desktop e touch compatÃ­vel, verificando controles alcanÃ§Ã¡veis e feedback compreensÃ­vel.

### Edge Cases

- Filtro sem resultado, projeto vazio, carregamento inicial e erro global tÃªm estados distintos e acionÃ¡veis.
- PreferÃªncias visuais locais nÃ£o alteram regras de negÃ³cio do Gantt.
- DependÃªncias densas, grupos recolhidos e itens ocultos nÃ£o tornam a navegaÃ§Ã£o ambÃ­gua.

## Requirements

### Functional Requirements

- **FR-001**: O usuÃ¡rio DEVE poder pesquisar tarefas e navegar ao resultado, preservando contexto de hierarquia e tempo.
- **FR-002**: O sistema DEVE oferecer filtros aprovados, incluindo status e concluÃ­das, com indicadores visÃ­veis dos filtros ativos.
- **FR-003**: Ocultar itens NÃƒO DEVE remover grupos, dados ou dependÃªncias do modelo de planejamento.
- **FR-004**: RelaÃ§Ãµes que cruzam itens ocultos DEVEM ter representaÃ§Ã£o e inspeÃ§Ã£o acessÃ­veis.
- **FR-005**: O usuÃ¡rio DEVE poder expandir/recolher, ajustar escala temporal, navegar no tempo e selecionar itens.
- **FR-006**: A experiÃªncia DEVE adaptar leitura, ediÃ§Ã£o e feedback a desktop, tablet e celular; aÃ§Ãµes de toque devem ter Ã¡reas adequadas.
- **FR-007**: As principais aÃ§Ãµes DEVEM ser operÃ¡veis por teclado quando aplicÃ¡vel e comunicar foco, seleÃ§Ã£o e erro de forma acessÃ­vel.
- **FR-008**: Estados de carregamento, vazio, erro, sincronizaÃ§Ã£o, confirmaÃ§Ã£o e bloqueio DEVEM ser distinguÃ­veis e acionÃ¡veis.

### Key Entities

- **Consulta**: termo de busca e resultado navegÃ¡vel na hierarquia/timeline.
- **Filtro**: critÃ©rio temporÃ¡rio de visibilidade que nÃ£o modifica os dados.
- **PreferÃªncia visual**: ajuste local de apresentaÃ§Ã£o sem efeito em regras de negÃ³cio.
- **Contexto oculto**: item nÃ£o exibido diretamente, mas relevante para grupo ou dependÃªncia.

## Success Criteria

- **SC-001**: UsuÃ¡rio encontra e focaliza uma tarefa visÃ­vel ou oculta pela hierarquia em uma Ãºnica jornada de pesquisa.
- **SC-002**: 100% das dependÃªncias com extremidade filtrada permanecem indicadas ou inspecionÃ¡veis.
- **SC-003**: As jornadas essenciais sÃ£o concluÃ­das em ao menos um dispositivo iOS/Safari e um Android/Chromium, alÃ©m de desktop.
