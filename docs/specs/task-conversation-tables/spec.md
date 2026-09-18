# Feature Specification: Tabelas na conversa da tarefa

**Feature**: `task-conversation-tables`
**Created**: 2026-09-10
**Status**: Draft

## Interface Coverage

| Surface | Type | Actors | Coverage | Functional Behavior | Excluded or Deferred Behavior |
|---|---|---|---|---|---|
| Conversa da tarefa | Web/Mobile Web | Proprietário, editor e leitor | FULL | Exibe comentários e blocos de tabela, permite criar e editar tabelas conforme o papel global e informa conflitos de edição. | Atualização automática do conteúdo aberto e coedição em tempo real. |
| Compositor da conversa | Web/Mobile Web | Proprietário e editor | FULL | Alterna entre criar comentário e criar tabela; permite formatar e publicar a tabela. | Importação, exportação, múltiplas abas, gráficos e fórmulas avançadas. |

## User Scenarios & Testing

### User Story 1 - Publicar uma tabela na conversa (Priority: P1)

Como proprietário ou editor de projeto, quero publicar uma tabela formatada na conversa de uma tarefa, para comunicar dados estruturados de planejamento sem convertê-los em um comentário textual.

**Why this priority**: Publicar e consultar uma tabela independente é o valor mínimo viável da feature.

**Independent Test**: abrir uma tarefa com direito de edição, criar uma tabela com dados e formato, publicá-la e reabrir a conversa para confirmar o bloco, o autor e a data.

**Acceptance Scenarios**:

1. **Given** um proprietário ou editor com a conversa aberta, **When** seleciona a aba “Nova Tabela”, **Then** pode editar uma nova tabela e publicá-la como bloco independente da conversa.
2. **Given** uma tabela publicada, **When** qualquer participante autorizado abre ou recarrega a conversa, **Then** vê a estrutura, os valores, a formatação, as células mescladas, o nome de quem a publicou e a data de publicação.
3. **Given** um leitor do projeto, **When** abre a conversa, **Then** pode visualizar tabelas, mas não vê ações para criar, editar, publicar ou remover tabelas.
4. **Given** uma tabela nova que excede o limite de 200 linhas, 100 colunas ou 500 KB, **When** o usuário tenta publicá-la, **Then** a publicação é recusada e o limite aplicável é informado sem perder o rascunho local.

---

### User Story 2 - Estruturar e formatar dados de tabela (Priority: P1)

Como proprietário ou editor, quero manipular linhas, colunas, células e fórmulas básicas, para que a tabela represente o planejamento de forma legível e útil.

**Why this priority**: A edição com aparência e comportamento de planilha é a principal razão para a tabela existir como bloco próprio.

**Independent Test**: criar uma tabela, adicionar e remover linhas e colunas, mesclar células, aplicar estilos, inserir fórmulas aritméticas e publicar o resultado.

**Acceptance Scenarios**:

1. **Given** uma tabela nova ou em edição, **When** o usuário adiciona ou remove linhas e colunas dentro dos limites, **Then** a estrutura é atualizada sem alterar dados fora da operação solicitada.
2. **Given** células adjacentes selecionadas, **When** o usuário as mescla ou desmescla, **Then** a tabela reflete a alteração e preserva o valor da célula superior esquerda conforme a convenção de planilhas.
3. **Given** uma ou mais células selecionadas, **When** o usuário aplica negrito, cor de texto, cor de fundo ou alinhamento, **Then** a formatação fica visível antes e depois de publicar a tabela.
4. **Given** células numéricas em uma tabela, **When** o usuário insere uma operação aritmética entre células ou uma soma de intervalo, **Then** o resultado calculado é exibido e acompanha alterações nos valores referenciados.

---

### User Story 3 - Editar uma tabela sem sobrescrever outra pessoa (Priority: P1)

Como proprietário ou editor, quero obter exclusividade temporária ao editar uma tabela publicada, para evitar que duas pessoas salvem alterações conflitantes na mesma versão.

**Why this priority**: Sem esse controle, qualquer edição concorrente pode substituir silenciosamente o trabalho de outro participante.

**Independent Test**: duas pessoas com direito de edição tentam abrir a mesma tabela; a primeira edita e a segunda recebe o estado de indisponibilidade até a primeira encerrar ou perder a exclusividade.

**Acceptance Scenarios**:

1. **Given** uma tabela publicada sem edição em andamento, **When** um proprietário ou editor inicia a edição, **Then** recebe o direito exclusivo de alterar aquela tabela por até 10 segundos sem renovação.
2. **Given** uma pessoa com direito exclusivo ativo, **When** outra pessoa tenta editar a mesma tabela, **Then** ela não pode alterar a tabela original enquanto o direito estiver vigente e recebe uma explicação clara.
3. **Given** uma pessoa em edição ativa, **When** continua trabalhando, **Then** o sistema mantém o direito exclusivo enquanto ele for renovado a cada 2 a 3 segundos.
4. **Given** a validade do direito expirou, **When** nenhuma outra pessoa iniciou a edição, **Then** o titular anterior pode renovar o direito e continuar seu rascunho.
5. **Given** a validade do direito expirou e outra pessoa iniciou a edição, **When** o titular anterior tenta renovar ou salvar, **Then** não pode alterar a tabela original e recebe mensagem de que outro participante assumiu a edição durante sua ausência.
6. **Given** uma pessoa encerra a edição salvando ou cancelando, **When** outra pessoa tenta editar a tabela, **Then** pode obter o direito exclusivo sem aguardar a validade anterior.

---

### User Story 4 - Preservar trabalho após perder a exclusividade (Priority: P2)

Como participante que perdeu o direito de editar uma tabela, quero preservar meu rascunho e publicá-lo como uma nova tabela, para não perder o trabalho e poder comparar os conteúdos depois.

**Why this priority**: O caminho de recuperação reduz perda de trabalho sem exigir coedição ou resolução automática de conflitos.

**Independent Test**: deixar a exclusividade expirar, fazer outra pessoa iniciar a edição, tentar renovar com o primeiro rascunho e publicar esse rascunho como nova tabela.

**Acceptance Scenarios**:

1. **Given** um rascunho cujo direito exclusivo foi adquirido por outra pessoa, **When** o conflito é identificado, **Then** o rascunho local permanece disponível em modo não editável e não é descartado automaticamente.
2. **Given** um rascunho em conflito, **When** o participante escolhe copiar o conteúdo, **Then** o conteúdo estruturado da tabela fica disponível para reutilização fora da tabela original.
3. **Given** um rascunho em conflito, **When** o participante escolhe “Publicar como nova tabela”, **Then** o sistema cria um novo bloco de tabela na conversa sem alterar a tabela original.
4. **Given** uma conversa já aberta por outro participante, **When** uma tabela é criada ou alterada, **Then** o outro participante vê a nova versão ao recarregar ou reabrir a conversa, sem atualização automática obrigatória.

### Edge Cases

- Uma tabela não pode ser publicada vazia se não contiver ao menos uma célula com valor, fórmula ou formatação não padrão.
- Dados excedentes colados na tabela não podem elevar a tabela acima dos limites publicados; o usuário deve receber feedback sem perda dos dados ainda válidos.
- A perda de conectividade ou a ocultação da página pode interromper a renovação, mas não descarta o rascunho local nem encerra automaticamente a sessão de edição visível para o usuário.
- Uma solicitação atrasada de renovação não pode retirar a exclusividade de quem a adquiriu validamente durante a ausência do titular anterior.
- Uma tabela removida enquanto está aberta para edição não pode ser salva sobre um bloco inexistente; o rascunho deve seguir o mesmo caminho de recuperação como nova tabela.
- Fórmulas inválidas ou referências inexistentes devem exibir um erro na célula e não podem impedir a visualização ou publicação das demais células válidas.

## Requirements

### Functional Requirements

- **FR-001**: A conversa da tarefa DEVE disponibilizar as abas “Novo Comentário” e “Nova Tabela” para proprietário e editor, mantendo o fluxo atual de novo comentário.
- **FR-002**: O sistema DEVE publicar cada tabela como bloco de conversa independente, ordenado junto aos comentários e identificado pelo nome e data de quem a publicou.
- **FR-003**: O sistema DEVE preservar em cada tabela publicada seus valores, fórmulas, estrutura de linhas e colunas, dimensões, estilos e células mescladas.
- **FR-004**: Uma tabela publicada DEVE aceitar no máximo 200 linhas, 100 colunas e 500 KB de conteúdo estruturado.
- **FR-005**: Proprietário e editor DEVEM poder criar, editar e remover qualquer tabela do projeto; leitor DEVE somente consultar tabelas.
- **FR-006**: O sistema NÃO DEVE aplicar permissões adicionais por tabela, tarefa ou autor da publicação.
- **FR-007**: O editor de tabela DEVE permitir inserir e remover linhas e colunas, dentro dos limites da tabela.
- **FR-008**: O editor de tabela DEVE permitir mesclar e desmesclar células adjacentes.
- **FR-009**: O editor de tabela DEVE permitir negrito, cor de texto, cor de fundo e alinhamento por célula ou seleção.
- **FR-010**: O editor de tabela DEVE permitir copiar, colar, desfazer, refazer e ajustar a dimensão visual de linhas e colunas.
- **FR-011**: A tabela DEVE calcular operações aritméticas entre células e soma de intervalos, preservando as expressões para novo cálculo quando os valores referenciados mudarem.
- **FR-012**: Antes de editar uma tabela publicada, proprietário ou editor DEVE obter exclusividade temporária sobre ela.
- **FR-013**: A exclusividade DEVE permanecer reservada por 10 segundos após cada concessão ou renovação, e uma renovação realizada a cada 2 a 3 segundos DEVE mantê-la ativa.
- **FR-014**: Após o período de 10 segundos, a exclusividade DEVE ficar disponível para aquisição por outro proprietário ou editor, mas a expiração NÃO DEVE encerrar ou apagar o rascunho do titular anterior.
- **FR-015**: O titular anterior DEVE recuperar a exclusividade ao renová-la após a expiração quando ninguém mais a tiver adquirido.
- **FR-016**: O sistema NÃO DEVE conceder a renovação ou permitir salvar a tabela original quando outra pessoa tiver adquirido validamente a exclusividade.
- **FR-017**: Ao perder a exclusividade, o sistema DEVE informar claramente que outro participante assumiu a edição durante a ausência, preservar o rascunho em modo não editável e oferecer copiar conteúdo ou publicar como nova tabela.
- **FR-018**: Publicar um rascunho em conflito como nova tabela DEVE criar novo bloco independente e NÃO DEVE modificar a tabela que originou o conflito.
- **FR-019**: A conversa DEVE apresentar tabelas no estado salvo quando for aberta ou recarregada e NÃO DEVE exigir atualização automática durante uma visualização já aberta.
- **FR-020-INFRA-LOCK**: A exclusividade de edição DEVE ser aplicada de forma consistente mesmo quando pedidos concorrentes forem atendidos por instâncias diferentes do sistema.
- **FR-021**: O sistema DEVE rejeitar alteração ou remoção de tabela inexistente, fora do projeto, ou sem permissão global de escrita, sem expor seu conteúdo a quem não possui acesso.
- **FR-022**: O sistema DEVE rejeitar uma gravação da tabela original que não estiver associada à exclusividade atualmente válida do solicitante.
- **FR-023**: Uma nova tabela DEVE iniciar com grade editável de 5 linhas por 5 colunas.
- **FR-024**: Ao copiar um rascunho que perdeu a exclusividade, o sistema DEVE copiar valores e fórmulas como texto tabulado; a preservação integral do documento continua disponível por “Publicar como nova tabela”.

### Key Entities

- **Bloco de conversa**: item cronológico associado a uma tarefa; pode ser comentário textual ou tabela e preserva o responsável pela publicação e o momento da publicação.
- **Tabela de tarefa**: conteúdo estruturado de um bloco de conversa, composto por células, linhas, colunas, estilos, dimensões, mesclagens e fórmulas.
- **Rascunho de tabela**: conteúdo em edição ainda não salvo como nova versão da tabela publicada ou como novo bloco.
- **Exclusividade de edição**: direito temporário e renovável de uma pessoa alterar uma tabela publicada, associado à tabela e ao participante que o obteve.

> Decisões de infraestrutura: a feature não exige scheduling, rotação de chaves, refresh de credenciais ou backup adicional. A exclusividade entre instâncias é requisito explícito em FR-020-INFRA-LOCK.

## Success Criteria

### Measurable Outcomes

- **SC-001**: Em testes de aceitação, 100% das tabelas com até 200 linhas, 100 colunas e 500 KB preservam valores, estilos, mesclagens e fórmulas após publicar e reabrir a conversa.
- **SC-002**: Em testes de autorização, 100% das tentativas de criação, edição ou remoção por leitor são recusadas, enquanto proprietário e editor conseguem alterar qualquer tabela do projeto.
- **SC-003**: Em testes concorrentes, 100% das tentativas simultâneas de obter exclusividade para a mesma tabela resultam em somente um participante apto a salvar a tabela original.
- **SC-004**: Em testes de expiração, 100% dos rascunhos que perdem a exclusividade permanecem disponíveis para cópia ou publicação como nova tabela.
- **SC-005**: Usuários com direito de escrita conseguem criar, formatar e publicar uma tabela simples com uma fórmula de soma em até 3 minutos em um teste de usabilidade assistido.
