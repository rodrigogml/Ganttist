# Feature Specification: Gerenciamento Local de Projetos

**Feature**: `local-project-management`
**Created**: 2026-08-25
**Status**: Draft

## Clarifications

### Session 2026-08-25

- Q: Como o progresso do projeto é calculado? -> A: Pela proporção da duração planejada das tarefas concluídas.
- Q: Como excluir seções e pessoas responsáveis com referências? -> A: Seções excluem em cascata suas subseções e tarefas; excluir pessoa mantém tarefas sem responsável designado.
- Q: Quando o convite de membro concede acesso e como localizar a pessoa? -> A: O acesso vale somente após aceite; o convite usa e-mail e a referência interna no projeto pesquisa por nome.

## Interface Coverage

| Surface | Type | Actors | Coverage | Functional Behavior | Excluded or Deferred Behavior |
|---|---|---|---|---|---|
| Início autenticado | Web/Mobile Web | Proprietário, editor e leitor | FULL | Consulta projetos acessíveis, seus indicadores e cria projeto quando autorizado | Integração obrigatória com Todoist |
| Workspace do projeto | Web/Mobile Web | Proprietário, editor e leitor | FULL | Organiza estrutura, tarefas, planejamento, dependências e progresso conforme a permissão | Tarefas dentro de tarefas |
| Gestão de pessoas e acesso | Web/Mobile Web | Proprietário | FULL | Cadastra responsáveis, convida membros por e-mail e define acesso do projeto após aceite | Notificações adicionais além do convite |

## User Scenarios & Testing

### User Story 1 - Iniciar um projeto local (Priority: P1)

Como usuário autenticado, quero consultar meus projetos e criar um projeto vazio somente com um nome, para iniciar o planejamento sem conectar uma conta externa.

**Why this priority**: É a entrada para todo uso autônomo do produto.

**Independent Test**: entrar sem conexão externa, criar um projeto com nome válido e encontrá-lo na lista de projetos.

**Acceptance Scenarios**:

1. **Given** um usuário autenticado sem projetos, **When** acessa a área inicial, **Then** vê um estado vazio e a ação de criar projeto, sem solicitação de conexão externa.
2. **Given** um usuário autenticado, **When** informa um nome válido e confirma a criação, **Then** recebe um projeto vazio do qual é proprietário.
3. **Given** projetos acessíveis ao usuário, **When** ele acessa a área inicial, **Then** cada projeto apresenta nome, quantidade de tarefas, progresso e quantidade de tarefas atrasadas.
4. **Given** um usuário sem acesso a um projeto, **When** tenta consultá-lo ou alterá-lo, **Then** o sistema não expõe seus dados nem concede acesso.

---

### User Story 2 - Estruturar trabalho e delegar tarefas (Priority: P1)

Como proprietário ou editor, quero criar seções, subseções e tarefas locais, além de atribuir cada tarefa a uma pessoa, para representar a estrutura real do projeto e a responsabilidade pelo trabalho.

**Why this priority**: A estrutura e a delegação são necessárias antes de planejar ou acompanhar execução.

**Independent Test**: criar uma seção com subseções, criar uma tarefa na raiz e outra dentro de uma seção, atribuí-las a pessoas distintas e confirmar que nenhuma tarefa aceita outra tarefa como filha.

**Acceptance Scenarios**:

1. **Given** um projeto vazio com acesso de alteração, **When** o usuário cria uma seção ou tarefa, **Then** qualquer um dos dois pode ser o primeiro item do projeto.
2. **Given** uma seção, **When** o usuário cria outra seção dentro dela, **Then** a nova seção integra a hierarquia sem limite fixo de profundidade.
3. **Given** um projeto ou seção, **When** o usuário cria uma tarefa, **Then** ela fica diretamente na raiz ou naquela seção e não pode conter itens filhos.
4. **Given** uma pessoa cadastrada sem conta ou acesso ao projeto, **When** o usuário a seleciona como responsável, **Then** a tarefa é atribuída sem criar convite ou permissão implícita.

---

### User Story 3 - Planejar e acompanhar a tarefa (Priority: P1)

Como proprietário ou editor, quero registrar os dados e datas de uma tarefa e concluí-la ou reabri-la, para que seu estado seja calculado de maneira previsível.

**Why this priority**: Datas, conclusão e estado dão significado operacional ao projeto e aos seus indicadores.

**Independent Test**: criar tarefas que representem cada estado possível, alterar suas datas, concluir e reabrir uma delas, e verificar o estado resultante e a data de conclusão real.

**Acceptance Scenarios**:

1. **Given** uma tarefa não concluída cuja data de fim planejada passou, **When** o estado é avaliado, **Then** ela aparece como atrasada, mesmo que também tenha predecessoras incompletas.
2. **Given** uma tarefa não concluída e não atrasada com qualquer predecessora incompleta, **When** o estado é avaliado, **Then** ela aparece como bloqueada.
3. **Given** uma tarefa não concluída, não atrasada e sem bloqueio, **When** seu início planejado é futuro, **Then** ela aparece como agendada; quando possui início ou fim planejado e seu período chega, aparece como em andamento; sem nenhuma data planejada, aparece como aberta.
4. **Given** uma tarefa concluída, **When** seu estado é avaliado, **Then** ela aparece como concluída acima de todos os demais estados; se for reaberta, a data de conclusão real é removida e o estado é recalculado.

---

### User Story 4 - Colaborar com acesso controlado (Priority: P2)

Como proprietário, quero cadastrar pessoas e controlar quem lê ou altera cada projeto, para delegar trabalho sem abrir dados ou permissões indevidas.

**Why this priority**: Colaboração segura amplia o uso do projeto sem impedir o MVP individual de funcionar.

**Independent Test**: criar pessoas internas, incluir usuários como proprietário, editor e leitor e confirmar que cada papel tem somente as ações permitidas.

**Acceptance Scenarios**:

1. **Given** um proprietário, **When** cadastra uma pessoa, **Then** o sistema exige nome, aceita e-mail opcional e não exige conta de usuário.
2. **Given** um editor, **When** tenta gerenciar membros ou excluir o projeto, **Then** a ação é negada.
3. **Given** um leitor, **When** abre um projeto, **Then** pode consultar seus dados, mas não cria nem altera estrutura, tarefas, pessoas, relações ou configurações.
4. **Given** um responsável sem conta, **When** recebe tarefas, **Then** não recebe automaticamente acesso, convite ou notificação.
5. **Given** um proprietário, **When** localiza um usuário pelo e-mail e envia convite com um papel, **Then** o usuário só passa a acessar o projeto depois de aceitar o convite.

---

### User Story 5 - Manter o planejamento existente em dados locais (Priority: P1)

Como planejador, quero usar calendário, Gantt, simulação, reagendamento, caminho crítico e dependências FS, SS, FF e SF sobre tarefas locais, para manter as capacidades de planejamento sem depender do Todoist.

**Why this priority**: A autonomia não pode reduzir o conjunto de capacidades que diferencia o produto.

**Independent Test**: criar tarefas locais planejadas, relacioná-las pelos quatro tipos de dependência e confirmar que o Gantt, as restrições temporais, a simulação, o reagendamento e o caminho crítico usam apenas esses dados locais.

**Acceptance Scenarios**:

1. **Given** duas tarefas do mesmo projeto, **When** o usuário cria uma dependência FS, SS, FF ou SF válida, **Then** a relação influencia o planejamento e fica visível no workspace.
2. **Given** tentativa de criar dependência cíclica, duplicada, consigo mesma ou entre projetos, **When** o usuário confirma, **Then** a relação é recusada e o motivo é informado.
3. **Given** um projeto local, **When** suas tarefas, estrutura ou planejamento são alterados, **Then** o Gantt e os cálculos usam os dados locais sem solicitar ou exigir uma conta externa.

### Edge Cases

- Projetos sem tarefas apresentam total zero, progresso zero e nenhuma tarefa atrasada.
- Tarefas sem início e fim planejados são consideradas abertas quando não concluídas, atrasadas ou bloqueadas; a ausência de data de fim não caracteriza atraso.
- A data de fim planejada anterior à data de início planejada é rejeitada.
- Uma tarefa concluída com fim planejado passado permanece concluída e não conta como atrasada.
- Alterações na estrutura ou em dependências que produziriam ciclo são rejeitadas sem alterar o projeto.
- Uma pessoa pode ser responsável por tarefas em vários projetos sem receber acesso a nenhum deles.
- Um convite recusado ou pendente não concede acesso ao projeto.
- Dados existentes de teste podem ser limpos; o produto não preserva compatibilidade com projetos ou integrações anteriores.

## Requirements

### Functional Requirements

- **FR-001**: O sistema DEVE permitir que qualquer usuário autenticado crie projeto local vazio com nome obrigatório, tornando-o seu proprietário.
- **FR-002**: O sistema NÃO DEVE exigir conexão, conta, autorização ou dados do Todoist para listar, criar, abrir ou alterar projetos locais.
- **FR-003**: A tela inicial DEVE listar somente projetos aos quais o usuário possui acesso e mostrar, para cada um, nome, quantidade total de tarefas, progresso e quantidade de tarefas atrasadas.
- **FR-004**: O sistema DEVE permitir criar, renomear, reorganizar e consultar seções em árvore acíclica sem limite fixo de níveis.
- **FR-005**: O sistema DEVE permitir criar, consultar e alterar tarefas na raiz do projeto ou diretamente em uma seção, mas DEVE impedir tarefas-filhas.
- **FR-006**: Cada tarefa DEVE ter título, descrição livre, responsável, data de início planejada, data de fim planejada e data de conclusão real; título é obrigatório e a conclusão real é preenchida apenas ao concluir a tarefa.
- **FR-007**: O sistema DEVE permitir cadastrar pessoa responsável com nome obrigatório e e-mail opcional, independentemente de ela ter conta, convite, associação ou acesso ao projeto.
- **FR-008**: O sistema DEVE calcular o status da tarefa, sem seleção manual, na seguinte ordem: concluída, bloqueada, agendada, atrasada, em andamento e aberta.
- **FR-009**: Uma tarefa não concluída DEVE ser atrasada quando sua data de fim planejada for anterior à data corrente.
- **FR-010**: Uma tarefa não concluída e não atrasada DEVE ser bloqueada quando tiver ao menos uma predecessora não concluída.
- **FR-011**: Uma tarefa não concluída, não atrasada e não bloqueada DEVE ser agendada quando seu início planejado for posterior à data corrente, em andamento quando possuir início ou fim planejado e seu período já tiver chegado, e aberta quando ambos estiverem ausentes.
- **FR-012**: Concluir uma tarefa DEVE registrar a data de conclusão real; reabri-la DEVE remover essa data e recalcular o status e os indicadores afetados.
- **FR-013**: O sistema DEVE preservar dependências FS, SS, FF e SF, calendário, Gantt, simulação, reagendamento, caminho crítico e auditoria sobre projetos e tarefas locais.
- **FR-014**: Dependências DEVEM ser intraprojeto e impedir ciclos, duplicatas e autodependências.
- **FR-015**: Cada projeto DEVE ter exatamente um proprietário e permitir membros com papel de editor ou leitor; somente o proprietário pode gerenciar membros e excluir o projeto.
- **FR-016**: Editores DEVEM criar e alterar a estrutura, tarefas, relações e configurações do projeto; leitores DEVEM somente consultar seus dados.
- **FR-017**: Ser responsável por uma tarefa NÃO DEVE conceder acesso ao projeto, independentemente de a pessoa possuir conta no sistema.
- **FR-018**: O produto DEVE limpar os usuários e projetos de teste existentes antes da entrada do novo domínio e NÃO DEVE oferecer migração ou retrocompatibilidade dos dados anteriores.
- **FR-019**: O indicador de progresso do projeto DEVE ser calculado pela proporção da duração planejada das tarefas concluídas em relação à duração planejada total das tarefas do projeto.
- **FR-020**: Excluir uma seção DEVE excluir em cascata suas subseções e tarefas descendentes. Excluir uma pessoa responsável DEVE preservar as tarefas relacionadas e remover sua designação de responsável.
- **FR-021**: O proprietário DEVE localizar usuário pelo e-mail e convidá-lo com papel de editor ou leitor. O acesso ao projeto DEVE ser concedido somente após aceite; convite pendente ou recusado não concede acesso. A referência interna de pessoas no projeto DEVE permitir pesquisa por nome.
- **FR-022-INFRA-BACKUP**: Os dados locais de projetos, tarefas, pessoas, membros e planejamento DEVEM receber backup ao menos diário, retenção mínima de 30 dias e teste de restauração ao menos mensalmente.

### Key Entities

- **Projeto**: unidade local de planejamento, com nome, proprietário, membros, estrutura, tarefas, calendário e indicadores.
- **Seção**: nó organizacional de um projeto que pode conter seções e tarefas, mas não cria hierarquia de tarefas.
- **Tarefa**: trabalho planejado na raiz ou em uma seção, com dados descritivos, pessoa responsável, datas, conclusão e status calculado.
- **Pessoa responsável**: pessoa cadastrada localmente, com ou sem conta e acesso ao produto, que pode receber tarefas.
- **Membro de projeto**: usuário com acesso explícito a um projeto no papel proprietário, editor ou leitor.
- **Dependência**: relação direcional FS, SS, FF ou SF entre tarefas do mesmo projeto.

## Success Criteria

### Measurable Outcomes

- **SC-001**: 100% dos usuários autenticados conseguem criar e abrir um projeto local sem conexão externa.
- **SC-002**: 100% das tentativas automatizadas de criar ciclo, autodependência ou dependência duplicada são recusadas.
- **SC-003**: Em cenários de referência aprovados, 100% das tarefas recebem o status correspondente à ordem de prioridade definida.
- **SC-004**: Em cenários automatizados de autorização, 100% das tentativas de leitura ou alteração fora do papel concedido são recusadas.
- **SC-005**: Em projetos de até 2.000 tarefas, a lista inicial apresenta os quatro indicadores de cada projeto e o workspace mantém as funções de planejamento sem exigir integração externa.
