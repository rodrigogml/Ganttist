# Feature Specification: Busca Unificada e Views de Tarefas

**Feature**: `unified-task-views`
**Created**: 2026-09-22
**Status**: Draft

## Clarifications

### Session 2026-09-22

- Q: Quando as views iniciais são criadas? -> A: Sob demanda, na primeira listagem de views do usuário naquele projeto.
- Q: Qual é a forma textual canônica do status em andamento? -> A: `status:em-andamento`; “Em andamento” é somente rótulo de interface.
- Q: Como o MVP representa períodos explícitos? -> A: `data:YYYY-MM-DD..YYYY-MM-DD`, inclusivo; o funil gera essa forma quando o usuário escolhe datas fixas.

## Interface Coverage

| Surface | Type | Actors | Coverage | Functional Behavior | Excluded or Deferred Behavior |
|---|---|---|---|---|---|
| Barra de comandos em Tarefas | Web / Mobile Web | Proprietário, editor e leitor | FULL | Pesquisa, constrói filtros, entende a consulta, aplica e gerencia views permitidas | Compartilhar views automaticamente |
| Barra de comandos em Gantt | Web / Mobile Web | Proprietário, editor e leitor | FULL | Aplica a mesma consulta e a configuração visual completa de uma view | Critérios de dependência e caminho crítico na consulta |
| Construtor visual do funil | Web / Mobile Web | Proprietário, editor e leitor | FULL | Escolhe critérios que são convertidos para a expressão única de busca | Manter filtros independentes da expressão |
| Gestão de views e portabilidade | Web / Mobile Web | Proprietário, editor e leitor | FULL | Cria, altera, duplica, remove, exporta e importa views privadas conforme suas permissões de consulta | Edição colaborativa ou compartilhamento de views |

## User Scenarios & Testing

### User Story 1 - Encontrar tarefas com uma única expressão (Priority: P1)

Como usuário de um projeto, quero escrever uma única expressão para buscar por texto e restringir atributos das tarefas, para obter o mesmo recorte em Tarefas e Gantt sem administrar filtros separados.

**Why this priority**: É o núcleo da experiência: sem uma fonte única de filtragem, uma view não é confiável nem reutilizável.

**Independent Test**: Em um projeto com tarefas de diferentes responsáveis, estados, prioridades, seções e datas, escrever uma expressão combinando texto e critérios e verificar o mesmo conjunto de tarefas em Tarefas e Gantt.

**Acceptance Scenarios**:

1. **Given** uma expressão textual já válida, **When** o usuário acrescenta `& status:aberta`, **Then** somente tarefas cujo título corresponde e cujo status é aberto permanecem no resultado.
2. **Given** tarefas abertas, atrasadas, concluídas e bloqueadas, **When** o usuário aplica `(status:aberta | status:atrasada) & !status:concluida`, **Then** são exibidas somente as abertas ou atrasadas não concluídas.
3. **Given** tarefas atribuídas ao usuário atual, a outras pessoas e sem responsável, **When** o usuário aplica `responsavel:eu | responsavel:sem`, **Then** o resultado contém somente tarefas atribuídas ao usuário atual ou sem responsável.
4. **Given** uma expressão válida aplicada, **When** o usuário digita uma expressão estruturalmente inválida, **Then** recebe diagnóstico compreensível com a posição do problema e o último resultado válido permanece aplicado.
5. **Given** uma expressão válida, **When** o usuário alterna entre Tarefas e Gantt, **Then** a mesma expressão e o mesmo conjunto filtrado permanecem ativos.

---

### User Story 2 - Construir e compreender uma consulta sem memorizar a sintaxe (Priority: P1)

Como usuário, quero usar o funil e sugestões no campo de busca para montar critérios corretos e descobrir as opções disponíveis, para usufruir de filtros avançados mesmo sem conhecer a linguagem.

**Why this priority**: A linguagem textual só agrega valor se for descobrível e utilizável por pessoas que não a dominam.

**Independent Test**: Com o campo inicialmente vazio, construir pelo funil uma combinação de responsável, status e período, e verificar que a expressão resultante é exibida, editável e produz o conjunto esperado.

**Acceptance Scenarios**:

1. **Given** o usuário abre o funil, **When** seleciona um critério, **Then** o campo único de busca recebe ou atualiza uma expressão equivalente e o resultado é atualizado por essa expressão.
2. **Given** o cursor está após `responsavel:`, **When** o usuário solicita sugestões, **Then** encontra ao menos `eu`, `sem` e as pessoas disponíveis no projeto.
3. **Given** o cursor está no começo de uma expressão, **When** o usuário começa a digitar o nome de um campo, **Then** recebe sugestões para os campos e operadores suportados adequados à posição.
4. **Given** o usuário abre a ajuda da consulta, **When** escolhe um exemplo, **Then** a expressão correspondente é inserida no campo e pode ser aplicada ou ajustada.
5. **Given** o usuário seleciona uma pessoa ou seção por sugestão com espaço ou pontuação no nome, **When** a escolha é inserida, **Then** a expressão continua válida e representa inequivocamente o valor escolhido.

---

### User Story 3 - Salvar um espaço de trabalho completo (Priority: P1)

Como usuário, quero salvar e aplicar uma view privada do projeto, para retornar rapidamente a uma combinação de tarefas e apresentação que uso com frequência.

**Why this priority**: Views transformam consultas e preferências temporárias em fluxos de trabalho rápidos e reutilizáveis.

**Independent Test**: Configurar uma consulta e preferências visuais distintas em Tarefas e Gantt, salvar uma view, alterar todos os controles, reaplicar a view e verificar a restauração integral.

**Acceptance Scenarios**:

1. **Given** uma consulta e controles visuais configurados, **When** o usuário salva uma nova view com nome válido, **Then** ela fica disponível somente para ele naquele projeto e restaura a configuração completa quando aplicada.
2. **Given** uma view aplicada, **When** o usuário altera a consulta ou qualquer controle visual, **Then** a alteração é usada imediatamente no workspace sem aviso de alterações não salvas nem persistência automática.
3. **Given** uma view existente, **When** o usuário decide sobrescrevê-la, **Then** o sistema pede confirmação antes de substituir sua configuração.
4. **Given** uma view existente, **When** o usuário a duplica, renomeia ou remove, **Then** somente suas próprias views naquele projeto são afetadas.
5. **Given** uma view foi criada enquanto o usuário estava em Gantt, **When** ele a aplica em Tarefas, **Then** preferências de Tarefas também são restauradas; preferências exclusivas de Gantt permanecem armazenadas para a próxima abertura do Gantt.

---

### User Story 4 - Usar views iniciais de acompanhamento (Priority: P2)

Como usuário, quero iniciar com duas views úteis, mas poder adaptá-las ao meu modo de trabalhar, para não precisar criar as buscas operacionais básicas do zero.

**Why this priority**: Oferece valor imediato e exemplifica a linguagem e o funcionamento das views sem impor modelos imutáveis.

**Independent Test**: Abrir as duas views iniciais em um projeto com tarefas abertas, atrasadas, de diferentes responsáveis e sem responsável, e comparar cada conjunto resultante com sua definição.

**Acceptance Scenarios**:

1. **Given** um usuário acessa um projeto, **When** aplica **Minhas Tarefas**, **Then** vê tarefas abertas ou atrasadas atribuídas a ele e tarefas abertas ou atrasadas sem responsável.
2. **Given** um usuário acessa um projeto, **When** aplica **Tarefas Equipe**, **Then** vê tarefas abertas ou atrasadas atribuídas a outras pessoas e tarefas abertas ou atrasadas sem responsável.
3. **Given** qualquer uma das views iniciais, **When** o usuário a altera, duplica, sobrescreve ou exclui, **Then** ela se comporta como uma view privada comum, sem proteção especial.

---

### User Story 5 - Levar uma view para outro contexto (Priority: P2)

Como usuário, quero exportar uma view em arquivo e importar uma view recebida, para reutilizar uma configuração em outro projeto ou disponibilizá-la a outra pessoa sem tornar minhas views compartilhadas.

**Why this priority**: A portabilidade explícita preserva a privacidade padrão e permite difundir práticas de trabalho úteis.

**Independent Test**: Exportar uma view completa, importá-la em outro projeto e confirmar que a configuração é recriada, inclusive quando a query contém uma referência que não existe no projeto de destino.

**Acceptance Scenarios**:

1. **Given** uma view existente, **When** o usuário a exporta, **Then** recebe um arquivo autoidentificável que contém nome, expressão e todas as preferências salvas necessárias para recriá-la.
2. **Given** um arquivo de view válido cujo nome não existe no projeto, **When** o usuário o importa, **Then** uma nova view privada é criada e pode ser aplicada.
3. **Given** um arquivo de view válido cujo nome já existe no projeto, **When** o usuário o importa, **Then** pode escolher sobrescrever a existente, criar uma cópia ou cancelar sem alteração.
4. **Given** uma expressão importada referencia uma pessoa, seção ou outro valor inexistente no projeto, **When** a importação é concluída, **Then** a view é preservada e aplicável; a interface informa que a referência não foi encontrada e pode retornar zero tarefas.
5. **Given** um arquivo inválido, incompleto ou de formato de view não reconhecido, **When** o usuário tenta importá-lo, **Then** a importação é recusada sem criar ou alterar views e o motivo é informado.

---

### User Story 6 - Receber feedback útil sobre a consulta (Priority: P2)

Como usuário, quero saber o que a expressão significa e quantas tarefas ela encontrou, para corrigir rapidamente uma busca inesperadamente vazia sem perder minha expressão.

**Why this priority**: Reduz erro, acelera aprendizado e torna a importação tolerante compreensível.

**Independent Test**: Aplicar consultas válidas com resultados, consultas válidas com valores inexistentes e consultas inválidas, confirmando feedback diferente e acionável para cada caso.

**Acceptance Scenarios**:

1. **Given** uma expressão válida, **When** é aplicada, **Then** o usuário vê a quantidade de tarefas correspondentes e uma descrição legível dos critérios reconhecidos.
2. **Given** uma expressão válida com um responsável ou seção inexistente, **When** é aplicada, **Then** o usuário vê uma advertência não bloqueante que identifica a referência desconhecida e a consulta permanece editável e exportável.
3. **Given** consultas usadas recentemente pelo usuário no projeto, **When** ele abre o histórico do campo, **Then** pode reaplicar uma delas sem reescrever a expressão.

### Edge Cases

- A consulta vazia mostra todas as tarefas permitidas pelos controles de acesso e não representa erro.
- Texto livre que contém `:`, espaços, aspas, operadores ou caracteres curinga pode ser pesquisado por meio de escape ou delimitação informada na ajuda.
- `responsavel:sem` inclui somente tarefas sem responsável; `responsavel:eu` inclui somente tarefas atribuídas ao usuário autenticado; `responsavel:outros` exclui o usuário atual e tarefas sem responsável.
- Uma tarefa sem datas não corresponde a critérios que exigem uma data; uma tarefa concluída só corresponde a `status:concluida` quando não for explicitamente excluída.
- Datas relativas são avaliadas no dia civil atual toda vez que a view é aplicada; a view guarda a expressão relativa, não uma data fixa.
- Se não houver tarefas correspondentes, o sistema mostra estado vazio com a expressão preservada e um caminho para ajustar ou limpar a consulta.
- Pessoas e seções com o mesmo nome exigem uma seleção assistida que preserve a intenção escolhida; texto digitado manualmente segue sendo aceito mesmo se não identificar valor único.
- Ao remover, sobrescrever ou importar uma view, cancelamento e falha não podem alterar outra view nem a configuração atualmente em uso.
- Leitores podem criar e administrar suas views privadas e aplicar filtros, mas não recebem permissão para alterar tarefas, estrutura, pessoas ou acesso do projeto.

## Requirements

### Functional Requirements

- **FR-001**: O sistema DEVE manter a barra de busca única acionável pelo atalho `/` nas visualizações Tarefas e Gantt.
- **FR-002**: O sistema DEVE tratar a expressão do campo de busca como a fonte única de verdade para busca textual e filtragem de tarefas nas duas visualizações.
- **FR-003**: O sistema DEVE preservar o comportamento existente dos operadores booleanos `&`, `|`, `!`, parênteses, curinga `*` e escape.
- **FR-004**: O sistema DEVE suportar predicados `campo:valor` combináveis com texto livre e operadores booleanos.
- **FR-005**: A linguagem DEVE oferecer os campos canônicos `status`, `responsavel`, `data`, `prioridade` e `secao`, bem como equivalentes compreensíveis em português para descoberta no produto.
- **FR-006**: O campo `status` DEVE aceitar os valores canônicos `aberta`, `em-andamento`, `agendada`, `atrasada`, `bloqueada` e `concluida`; os rótulos da interface podem conter espaços e acentos, mas a expressão salva e exportada usa a forma canônica.
- **FR-007**: O campo `responsavel` DEVE aceitar ao menos os valores especiais `eu`, `sem` e `outros`, além de uma pessoa do projeto selecionada ou digitada pelo usuário.
- **FR-008**: O campo `data` DEVE aceitar `hoje`, `amanha`, `proximos-7-dias` e o intervalo inclusivo `YYYY-MM-DD..YYYY-MM-DD`; o construtor visual gera essas mesmas formas canônicas.
- **FR-009**: A linguagem DEVE permitir incluir ou excluir tarefas concluídas e bloqueadas, sem impedir a combinação com os demais critérios.
- **FR-010**: Uma expressão estruturalmente inválida NÃO DEVE substituir o último conjunto de resultados obtido por expressão válida e DEVE apresentar diagnóstico localizável.
- **FR-011**: Uma expressão estruturalmente válida com valor desconhecido DEVE ser aceita, preservada e portável; o sistema DEVE mostrar advertência não bloqueante quando puder identificar o valor desconhecido.
- **FR-012**: O funil DEVE ler e escrever a mesma expressão exibida no campo de busca e NÃO DEVE manter estado de filtro independente.
- **FR-013**: O sistema DEVE oferecer sugestões contextuais de campos, operadores e valores conhecidos, inseríveis por teclado e por toque.
- **FR-014**: O sistema DEVE disponibilizar ajuda acessível com referência de sintaxe, explicação de operadores e exemplos que possam preencher a consulta.
- **FR-015**: O sistema DEVE comunicar a quantidade de tarefas encontradas, o estado vazio e uma descrição legível dos critérios reconhecidos sem ocultar a expressão original.
- **FR-016**: O sistema NÃO DEVE persistir histórico de consultas; uma consulta só é preservada quando o usuário a salva explicitamente como view.
- **FR-017**: Uma view DEVE ser privada, pertencer a exatamente um usuário e um projeto, e armazenar uma expressão de consulta mais todas as preferências visuais suportadas no workspace.
- **FR-018**: Uma view DEVE capturar e restaurar agrupamento, subagrupamento, ordenação, subordenação, colunas visíveis, estado de hierarquia e todas as preferências específicas de Tarefas e de Gantt, incluindo o zoom quando aplicável.
- **FR-019**: Aplicar uma view DEVE restaurar toda a sua configuração, inclusive preferências da outra visualização que não estejam visíveis no modo atual.
- **FR-020**: O sistema DEVE permitir a qualquer usuário com acesso de consulta criar, renomear, duplicar, aplicar e excluir somente suas próprias views no projeto.
- **FR-021**: O sistema NÃO DEVE salvar automaticamente alterações feitas após aplicar uma view, nem exibir aviso de alterações não salvas.
- **FR-022**: O sistema DEVE exigir confirmação antes de sobrescrever a configuração de uma view existente.
- **FR-023**: Na primeira listagem de views de um usuário em um projeto, o sistema DEVE criar sob demanda as views editáveis **Minhas Tarefas** e **Tarefas Equipe** com os critérios definidos nesta especificação; depois disso elas se comportam como views privadas comuns.
- **FR-024**: O sistema DEVE permitir exportar uma view como arquivo versionado que contenha somente dados necessários para recriar sua configuração, sem dados de tarefas ou pessoas do projeto de origem.
- **FR-025**: O sistema DEVE validar o arquivo de importação antes de apresentar o conflito de nome e DEVE permitir criar, sobrescrever ou cancelar quando houver colisão.
- **FR-026**: O sistema DEVE concluir a importação de uma expressão válida mesmo que referências textuais não existam no projeto de destino.
- **FR-027**: O sistema DEVE aplicar as mesmas regras de acesso do projeto às tarefas exibidas por consulta ou view e NÃO DEVE tornar dados de outros projetos visíveis por importação, histórico ou autocomplete.
- **FR-028**: Todas as ações de consulta, sugestões, funil, gestão de views e confirmações DEVEM ser operáveis por teclado e por toque, com nomes acessíveis, foco perceptível e mensagens anunciáveis.

> Decisões de infraestrutura: N/A (feature sem scheduling, sessão persistente adicional, rotação de chave ou integração externa de longa duração).

### Key Entities

- **Expressão de consulta**: texto editável que combina busca livre, predicados de atributos e operadores lógicos para definir um conjunto de tarefas.
- **Critério de consulta**: parte reconhecida de uma expressão que restringe texto, status, responsável, data, prioridade, seção, conclusão ou bloqueio.
- **View**: configuração privada e nomeada de um usuário para um projeto, contendo expressão e snapshot de preferências visuais de Tarefas e Gantt.
- **Preferência visual de view**: escolha de apresentação restaurável, como agrupamento, ordenação, colunas, hierarquia ou zoom, sem alterar dados do projeto.
- **Arquivo de view**: representação portátil e versionada de uma view, sem tarefas, pessoas ou permissões do projeto de origem.
- **Referência desconhecida**: valor sintaticamente aceito em expressão importada ou digitada que não corresponde a uma opção conhecida no projeto atual.

## Success Criteria

### Measurable Outcomes

- **SC-001**: Em cenários automatizados, 100% das expressões válidas de referência retornam o mesmo conjunto de tarefas em Tarefas e Gantt.
- **SC-002**: Em testes de aceitação, usuários conseguem montar uma consulta de responsável, status e período pelo funil e aplicá-la sem digitar sintaxe manual.
- **SC-003**: Em cenários automatizados, 100% das expressões inválidas preservam o último resultado válido e apresentam um diagnóstico.
- **SC-004**: Em projetos de até 2.000 tarefas, 95% das alterações de uma expressão válida atualizam a contagem e o conjunto exibido em até 1 segundo após o usuário parar de digitar.
- **SC-005**: Em cenários automatizados, aplicar uma view restaura 100% dos controles que ela armazena em Tarefas e Gantt.
- **SC-006**: Em cenários automatizados, 100% das exportações válidas podem ser importadas como nova view; colisões de nome não alteram views existentes sem a escolha explícita de sobrescrever.
- **SC-007**: Em testes de teclado e toque, todas as ações essenciais de consulta e gestão de views podem ser concluídas sem uso obrigatório de mouse ou de sintaxe manual.
