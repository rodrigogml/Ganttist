# Feature Specification: CalendÃ¡rio e Datas das Tarefas

**Feature**: `calendar-task-dates`
**Created**: 2026-08-17
**Status**: Draft

## Interface Coverage

| Surface | Type | Actors | Coverage | Functional Behavior | Excluded or Deferred Behavior |
|---|---|---|---|---|---|
| Workspace operacional | Web/Mobile Web | Planejador | FULL | Visualiza e edita calendÃ¡rio, datas civis, duraÃ§Ã£o e estados temporais | HorÃ¡rios e fraÃ§Ãµes de dia |
| ConfiguraÃ§Ã£o do Gantt | Web/Mobile Web | Planejador | FULL | Define semana Ãºtil, exceÃ§Ãµes e modo de reagendamento | Templates e importaÃ§Ã£o de calendÃ¡rios |

## User Scenarios & Testing

### User Story 1 - Planejar em dias Ãºteis (Priority: P1)

Como planejador, quero que inÃ­cio, fim e duraÃ§Ã£o respeitem meu calendÃ¡rio de trabalho.

**Independent Test**: configurar dia nÃ£o Ãºtil e planejar atividade de vÃ¡rios dias, confirmando que duraÃ§Ã£o conta apenas dias Ãºteis e o fim Ã© ajustado conforme as regras.

**Acceptance Scenarios**:

1. **Given** inÃ­cio vÃ¡lido e deadline ausente, **When** uma tarefa Ã© exibida, **Then** ela tem duraÃ§Ã£o de um dia Ãºtil.
2. **Given** deadline anterior ao inÃ­cio, **When** a tarefa Ã© calculada, **Then** o deadline invÃ¡lido Ã© ignorado e a duraÃ§Ã£o Ã© um dia.
3. **Given** deadline em dia nÃ£o Ãºtil, **When** o planejamento Ã© calculado, **Then** aplica a polÃ­tica configurada para esse caso.

### User Story 2 - Configurar o calendÃ¡rio do Gantt (Priority: P1)

Como planejador, quero definir semana de trabalho e exceÃ§Ãµes para que o cronograma reflita a realidade do projeto.

**Independent Test**: tornar um dia Ãºtil em bloqueado e confirmar simulaÃ§Ã£o ou aplicaÃ§Ã£o coerente de todas as tarefas afetadas, preservando duraÃ§Ã£o.

### User Story 3 - Entender datas ausentes e grupos (Priority: P2)

Como planejador, quero distinguir tarefas nÃ£o programadas de datas calculadas e ver grupos como resumo dos descendentes.

**Independent Test**: abrir grupo com descendentes planejados e nÃ£o planejados e confirmar que apenas os planejados definem o intervalo; abrir atividade sem data e conferir referÃªncia virtual sem confundi-la com dado persistido.

### Edge Cases

- Data inicial em dia bloqueado Ã© sinalizada no modo manual e ajustada para o prÃ³ximo dia Ãºtil no modo automÃ¡tico.
- Uma mudanÃ§a de calendÃ¡rio nÃ£o antecipa tarefa futura independente cujo inÃ­cio continua vÃ¡lido.
- Nenhum descendente planejado implica ausÃªncia de representaÃ§Ã£o temporal para o grupo.

## Requirements

### Functional Requirements

- **FR-001**: Planejamento DEVE usar exclusivamente datas civis e dias inteiros.
- **FR-002**: O calendÃ¡rio DEVE permitir semana Ãºtil e exceÃ§Ãµes por data por Gantt.
- **FR-003**: DuraÃ§Ã£o DEVE contar somente dias Ãºteis; ausÃªncia de deadline vÃ¡lido representa duraÃ§Ã£o de um dia.
- **FR-004**: Tarefa sem data DEVE continuar nÃ£o programada visualmente e ter referÃªncia virtual somente para cÃ¡lculos autorizados.
- **FR-005**: AlteraÃ§Ãµes no calendÃ¡rio DEVEM preservar duraÃ§Ã£o em dias Ãºteis e recalcular os impactos necessÃ¡rios.
- **FR-006**: Datas de grupo DEVEM ser derivadas do menor inÃ­cio e maior fim dos descendentes planejados.
- **FR-007**: Grupo nÃ£o pode ser movido ou redimensionado diretamente.
- **FR-008**: O sistema DEVE distinguir e comunicar datas invÃ¡lidas, nÃ£o Ãºteis, virtuais e persistidas.

### Key Entities

- **CalendÃ¡rio de trabalho**: semana Ãºtil, exceÃ§Ãµes e polÃ­tica de ajuste de deadline de um Gantt.
- **Atividade planejada**: tarefa com inÃ­cio e intervalo calculÃ¡vel.
- **Atividade nÃ£o programada**: tarefa sem inÃ­cio persistido, mantida na hierarquia.
- **Data virtual**: referÃªncia calculada nÃ£o persistida para atividade sem data.
- **Grupo**: intervalo derivado de descendentes planejados.

## Success Criteria

- **SC-001**: 100% dos casos de duraÃ§Ã£o aprovados contabilizam somente dias Ãºteis.
- **SC-002**: Alterar o calendÃ¡rio mantÃ©m a duraÃ§Ã£o Ãºtil de todas as atividades afetadas em 100% dos cenÃ¡rios automatizados.
- **SC-003**: Nenhuma tarefa sem inÃ­cio persiste uma barra temporal apenas por possuir referÃªncia virtual.
