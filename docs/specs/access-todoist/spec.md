# Feature Specification: Acesso e Conta Todoist

**Feature**: `access-todoist`
**Created**: 2026-08-17
**Status**: Draft

## Interface Coverage

| Surface | Type | Actors | Coverage | Functional Behavior | Excluded or Deferred Behavior |
|---|---|---|---|---|---|
| Acesso pÃºblico | Web | Visitante | FULL | Solicita e consome acesso passwordless, informa falhas e permite encerrar sessÃ£o | Senha, login social e cadastro por administrador |
| E-mail de acesso | Other | Visitante | FULL | Entrega link mÃ¡gico e cÃ³digo de uso Ãºnico | Fluxos promocionais |
| Ãrea autenticada | Web/Mobile Web | UsuÃ¡rio | FULL | Conecta, consulta estado e desconecta a conta Todoist | Mais de uma conta por usuÃ¡rio |

## User Scenarios & Testing

### User Story 1 - Entrar sem senha (Priority: P1)

Como visitante, quero iniciar sessÃ£o por e-mail para acessar meus Gantts sem criar ou administrar uma senha.

**Why this priority**: Ã© a porta de entrada de todas as funÃ§Ãµes privadas.

**Independent Test**: solicitar acesso para um endereÃ§o vÃ¡lido, usar uma Ãºnica vez o link ou cÃ³digo recebido e alcanÃ§ar uma sessÃ£o autenticada.

**Acceptance Scenarios**:

1. **Given** um visitante sem sessÃ£o, **When** solicita acesso com e-mail vÃ¡lido, **Then** recebe instruÃ§Ã£o de acesso sem revelar se o endereÃ§o jÃ¡ possui conta.
2. **Given** um cÃ³digo ou link vÃ¡lido e nÃ£o consumido, **When** o visitante o usa antes do vencimento, **Then** inicia uma sessÃ£o no dispositivo atual.
3. **Given** um cÃ³digo expirado, invÃ¡lido ou jÃ¡ consumido, **When** Ã© usado, **Then** o acesso Ã© negado de forma clara e segura.

### User Story 2 - Conectar o Todoist (Priority: P1)

Como usuÃ¡rio autenticado, quero autorizar minha conta Todoist para usar seus projetos e tarefas no Ganttist.

**Independent Test**: autorizar uma conta vÃ¡lida e verificar que seus projetos ficam disponÃ­veis somente para o mesmo usuÃ¡rio.

**Acceptance Scenarios**:

1. **Given** usuÃ¡rio autenticado sem integraÃ§Ã£o, **When** conclui a autorizaÃ§Ã£o da conta, **Then** a conexÃ£o fica identificada como ativa.
2. **Given** autorizaÃ§Ã£o recusada ou interrompida, **When** retorna ao produto, **Then** permanece autenticado no Ganttist e recebe orientaÃ§Ã£o para tentar novamente.

### User Story 3 - Gerir sessÃµes e conexÃ£o (Priority: P2)

Como usuÃ¡rio, quero encerrar minha sessÃ£o e desconectar minha conta Todoist sem afetar contas de outros usuÃ¡rios.

**Independent Test**: iniciar sessÃµes em dois dispositivos, encerrar uma e confirmar que a outra continua vÃ¡lida; desconectar o Todoist e confirmar que os dados de acesso Ã  integraÃ§Ã£o deixam de ser utilizÃ¡veis.

**Acceptance Scenarios**:

1. **Given** sessÃµes em dispositivos distintos, **When** o usuÃ¡rio encerra uma sessÃ£o, **Then** apenas aquele dispositivo perde acesso.
2. **Given** uma integraÃ§Ã£o ativa, **When** o usuÃ¡rio a desconecta, **Then** novas leituras e escritas dependentes da conta sÃ£o bloqueadas e os Gantts preservam seu histÃ³rico prÃ³prio conforme a polÃ­tica de conta.

### Edge Cases

- SolicitaÃ§Ãµes repetidas de acesso nÃ£o revelam existÃªncia de conta e sÃ£o limitadas.
- Conta Todoist revogada, expirada ou pertencente a outro usuÃ¡rio exige nova autorizaÃ§Ã£o e nÃ£o expÃµe dados cruzados.
- A exclusÃ£o da conta remove ou anonimiza dados conforme a polÃ­tica aprovada, sem afetar outros usuÃ¡rios.

## Requirements

### Functional Requirements

- **FR-001**: O sistema DEVE permitir acesso passwordless por link mÃ¡gico e cÃ³digo de uso Ãºnico, com expiraÃ§Ã£o e consumo Ãºnico.
- **FR-002**: O sistema DEVE impedir enumeraÃ§Ã£o de contas nas respostas de solicitaÃ§Ã£o de acesso.
- **FR-003**: O usuÃ¡rio DEVE poder manter mÃºltiplas sessÃµes por dispositivo e encerrar a sessÃ£o atual ou sessÃµes selecionadas.
- **FR-004**: O sistema DEVE exigir sessÃ£o autenticada antes de mostrar ou alterar qualquer Gantt ou integraÃ§Ã£o.
- **FR-005**: O usuÃ¡rio DEVE poder conectar uma Ãºnica conta Todoist e visualizar o estado da autorizaÃ§Ã£o.
- **FR-006**: O sistema DEVE manter isolamento completo de identidade, sessÃµes, integraÃ§Ã£o e projetos entre usuÃ¡rios.
- **FR-007**: O usuÃ¡rio DEVE poder desconectar ou substituir a conta Todoist com aviso claro do impacto.
- **FR-008**: Falhas, recusas e perda de autorizaÃ§Ã£o DEVEM ser comunicadas sem expor credenciais.

### Key Entities

- **UsuÃ¡rio**: identidade proprietÃ¡ria de sessÃµes, integraÃ§Ã£o e Gantts.
- **SessÃ£o**: acesso autenticado de um dispositivo, com ciclo de vida revogÃ¡vel.
- **Desafio de acesso**: link ou cÃ³digo temporÃ¡rio, de uso Ãºnico, associado ao e-mail.
- **IntegraÃ§Ã£o Todoist**: autorizaÃ§Ã£o de uma conta externa pertencente a um usuÃ¡rio.

## Success Criteria

- **SC-001**: 100% das tentativas com desafio expirado, invÃ¡lido ou jÃ¡ usado sÃ£o recusadas.
- **SC-002**: Um usuÃ¡rio nÃ£o consegue observar nem alterar Gantts, sessÃµes ou integraÃ§Ã£o de outro usuÃ¡rio em 100% dos cenÃ¡rios de isolamento automatizados.
- **SC-003**: Um usuÃ¡rio autorizado conclui conexÃ£o ou reconexÃ£o da conta Todoist sem perder a sessÃ£o do Ganttist.
