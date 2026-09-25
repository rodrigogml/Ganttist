# Feature Specification: Tarefas Recorrentes e Soneca

**Feature**: `recurring-tasks-snooze`
**Created**: 2026-09-24
**Status**: Draft
**Briefing**: [../../briefing/20260924-recurring-tasks-and-snooze.md](../../briefing/20260924-recurring-tasks-and-snooze.md)

## Interface Coverage

| Surface | Type | Actors | Coverage | Functional Behavior | Excluded or Deferred Behavior |
|---------|------|--------|----------|---------------------|-------------------------------|
| Workspace — Gantt | Web / Mobile Web | Proprietário, editor e leitor | FULL | Exibe recorrência e ocorrência corrente; permite soneca, conclusão e edição conforme permissão; mostra impacto e estado operacional. | Dependências entre ocorrências e recorrentes como predecessoras. |
| Workspace — Tarefas | Web / Mobile Web | Proprietário, editor e leitor | FULL | Oferece os mesmos dados e ações da ocorrência corrente, consulta de histórico e identificação de tarefas recorrentes. | Relatórios analíticos avançados do histórico. |
| Editor de tarefa | Web / Mobile Web | Proprietário e editor | FULL | Cria, altera, remove ou encerra uma recorrência por editor visual ou expressão natural PT-BR. | Linguagem natural fora da gramática publicada. |
| Menus de ação rápida | Web / Mobile Web | Proprietário e editor | FULL | Adia a ocorrência aberta por opções rápidas ou data escolhida, com preview e confirmação. | Soneca em horas ou em data não útil. |

## User Scenarios & Testing

### User Story 1 - Definir uma rotina recorrente (Priority: P1)

Como proprietário ou editor, quero definir uma recorrência visualmente ou por uma expressão natural em português, para transformar uma tarefa comum em uma rotina previsível sem depender de sintaxe técnica.

**Why this priority**: Sem uma regra compreensível e validada não existe ocorrência futura confiável nem valor recorrente para o usuário.

**Independent Test**: Criar tarefas com o editor visual e com as expressões aceitas para uma segunda-feira semanal, um intervalo de quatro dias úteis e uma regra mensal ordinal; confirmar que cada uma exibe a mesma regra legível e a primeira ocorrência correta.

**Acceptance Scenarios**:

1. **Given** uma tarefa sem recorrência, **When** o editor define `toda segunda` ou a expressão natural equivalente, **Then** a tarefa passa a mostrar uma regra fixa semanal e uma ocorrência corrente na próxima segunda elegível.
2. **Given** uma tarefa sem recorrência, **When** o editor define `a cada 4 dias`, **Then** a tarefa passa a mostrar uma regra por intervalo de quatro dias úteis e uma ocorrência corrente válida no calendário do projeto.
3. **Given** uma regra criada pelo editor visual, **When** ela é exibida no campo natural, **Then** o campo apresenta uma formulação PT-BR canônica equivalente sem alterar a regra.
4. **Given** uma expressão fora da gramática publicada, **When** o usuário tenta aplicá-la, **Then** o sistema explica o trecho não aceito, preserva o rascunho e não altera a recorrência existente.
5. **Given** uma regra com início futuro ou término, **When** ela é salva, **Then** somente datas dentro do intervalo inclusivo podem se tornar ocorrências da série.

---

### User Story 2 - Adiar somente a ocorrência aberta (Priority: P1)

Como proprietário ou editor, quero adiar uma ocorrência para uma data útil sem modificar a rotina, para reorganizar o trabalho pontual sem perder a cadência planejada.

**Why this priority**: Soneca é o caminho operacional diário para lidar com imprevistos; se ela mudar a série sem transparência, a recorrência deixa de ser confiável.

**Independent Test**: Adiar uma ocorrência fixa de segunda-feira para o próximo dia útil e confirmar que a ocorrência mostra o novo agendamento, preserva a duração e conserva a regra semanal original.

**Acceptance Scenarios**:

1. **Given** uma ocorrência aberta, **When** o usuário seleciona `Amanhã`, **Then** ela é agendada no próximo dia útil do calendário do projeto.
2. **Given** uma ocorrência aberta, **When** o usuário seleciona `Próxima semana`, **Then** ela é agendada no primeiro dia útil da semana seguinte.
3. **Given** uma ocorrência aberta, **When** o usuário seleciona `3 dias` ou `7 dias`, **Then** a nova data é calculada em três ou sete dias úteis, respectivamente.
4. **Given** uma ocorrência aberta, **When** o usuário escolhe data em feriado ou dia não útil, **Then** o sistema informa a normalização e agenda no primeiro dia útil posterior.
5. **Given** uma ocorrência recorrente fixa, **When** sua data é adiada, **Then** a regra e seu padrão futuro permanecem inalterados; somente a ocorrência aberta recebe o novo agendamento.
6. **Given** uma soneca que altera sucessoras, restrições ou término previsto, **When** o preview é apresentado, **Then** o usuário vê os impactos classificados antes de poder confirmar ou cancelar a alteração.

---

### User Story 3 - Concluir e recuperar uma ocorrência (Priority: P1)

Como proprietário ou editor, quero concluir a ocorrência realizada e consultar suas conclusões anteriores, para acompanhar a rotina sem converter a tarefa recorrente em uma tarefa definitivamente concluída.

**Why this priority**: A conclusão é a transição que produz valor, histórico e a próxima ocorrência; seu comportamento precisa ser seguro diante de atraso e repetição de comandos.

**Independent Test**: Concluir uma ocorrência adiada, verificar o histórico com data lógica, agendamento e conclusão, e confirmar que a tarefa passa a apontar à ocorrência futura correta sem criar um segundo registro ao repetir a mesma ação.

**Acceptance Scenarios**:

1. **Given** uma ocorrência recorrente aberta, **When** ela é concluída, **Then** o sistema registra a ocorrência concluída e mantém a tarefa ativa na próxima ocorrência permitida.
2. **Given** uma mesma confirmação de ocorrência repetida por clique duplicado ou retry, **When** o sistema a recebe novamente, **Then** devolve o resultado já produzido sem avançar novamente a série nem duplicar o histórico.
3. **Given** uma regra fixa `toda segunda` adiada para terça, **When** ela é concluída na terça, **Then** a próxima ocorrência é a próxima segunda compatível posterior ao agendamento e à conclusão.
4. **Given** uma regra por intervalo `a cada 4 dias` adiada para amanhã e concluída hoje, **When** a próxima ocorrência é calculada, **Then** o intervalo é contado a partir de amanhã, sem ser encurtado pela conclusão antecipada.
5. **Given** uma ocorrência concluída após uma ou mais datas lógicas perdidas, **When** a série avança, **Then** ela aponta diretamente para a próxima data futura e não cria uma fila de ocorrências atrasadas.
6. **Given** a conclusão da última ocorrência dentro do término configurado, **When** não existir próxima data elegível, **Then** a tarefa fica definitivamente concluída e a série é encerrada.
7. **Given** uma tarefa recorrente, **When** o usuário escolhe `Concluir definitivamente`, **Then** a recorrência é encerrada, a tarefa fica concluída e seu histórico anterior permanece disponível.

---

### User Story 4 - Planejar recorrências sem distorcer o projeto finito (Priority: P1)

Como planejador, quero que uma rotina recorrente respeite os bloqueios que recebe, sem prolongar artificialmente o prazo, o caminho crítico ou o progresso do projeto, para manter indicadores de projeto significativos.

**Why this priority**: Recorrências contínuas não têm término finito; sem esta fronteira, uma única rotina torna os indicadores globais incorretos.

**Independent Test**: Em um projeto com tarefas finitas, uma recorrente sucessora e uma recorrente contínua, verificar que a sucessora permanece bloqueada até sua predecessora terminar, enquanto término, caminho crítico e progresso do projeto não mudam por causa das recorrentes.

**Acceptance Scenarios**:

1. **Given** uma tarefa finita como predecessora e uma recorrente como sucessora, **When** a predecessora ainda está incompleta, **Then** a ocorrência recorrente mostra o bloqueio aplicável.
2. **Given** uma tarefa recorrente contínua, **When** o projeto calcula término, caminho crítico, folga e progresso, **Then** a tarefa não amplia nem participa desses indicadores finitos globais.
3. **Given** a criação ou edição de uma dependência com uma recorrente como predecessora, **When** o usuário confirma, **Then** o sistema recusa a relação e explica que dependências por ocorrência ainda não são suportadas.
4. **Given** uma seção que contém uma recorrente, **When** ela é escolhida como predecessora ou uma tarefa da seção se torna recorrente, **Then** o sistema impede o estado inválido e informa a relação que precisa ser removida ou alterada.
5. **Given** um projeto com recorrentes e tarefas finitas, **When** o usuário consulta seus indicadores, **Then** o sistema diferencia dados operacionais de recorrência dos indicadores finitos do projeto sem somá-los indevidamente.

---

### User Story 5 - Editar, remover e consultar a série com segurança (Priority: P2)

Como proprietário ou editor, quero alterar ou encerrar uma regra conscientemente, e como leitor quero entender a ocorrência atual e o histórico, para que mudanças de rotina sejam transparentes para toda a equipe.

**Why this priority**: O uso contínuo exige mudanças de rotina, mas elas não podem apagar ou reinterpretar execuções já registradas.

**Independent Test**: Alterar uma regra que possui ocorrência adiada, consultar sua representação antes e depois, remover a recorrência e duplicar a tarefa, verificando que o histórico original não é reescrito nem copiado.

**Acceptance Scenarios**:

1. **Given** uma recorrência com ocorrência aberta adiada, **When** o usuário edita a regra, **Then** o sistema apresenta claramente a diferença entre editar a série e reagendar somente a ocorrência atual.
2. **Given** uma tarefa recorrente, **When** o usuário remove sua recorrência sem concluí-la, **Then** ela se torna uma tarefa não recorrente aberta com o planejamento atual preservado.
3. **Given** uma tarefa recorrente, **When** ela é duplicada, **Then** a cópia recebe uma regra e ocorrência inicial próprias, sem copiar as conclusões históricas da original.
4. **Given** um leitor do projeto, **When** abre tarefa recorrente ou histórico, **Then** pode consultar regra, ocorrência aberta, soneca e conclusões, mas não pode alterá-las.
5. **Given** uma tarefa recorrente, **When** o usuário acessa Gantt ou Tarefas, **Then** as duas views exibem a mesma regra, ocorrência corrente, estado e indicação de soneca.

### Edge Cases

- Uma tarefa sem data planejada passa a ser recorrente: a primeira ocorrência é determinada pela regra, início configurado e calendário do projeto.
- A data de início, a data de término ou uma ocorrência lógica cai em dia não útil: a data lógica permanece a da regra; o agendamento é normalizado para dia útil sem mudar a cadência fixa.
- A primeira data futura de uma regra fixa está além da data final: a série não abre ocorrência e a tarefa permanece definitivamente concluída ou sem recorrência ativa, conforme a ação que a originou.
- Uma tarefa é concluída antes da data agendada, na data agendada ou após ela: regras fixas e por intervalo aplicam suas bases de cálculo distintas de forma determinística.
- Uma duração de vários dias atravessa fim de semana, feriado ou a próxima data lógica: a ocorrência preserva duração em dias úteis; a próxima só é aberta após a conclusão da ocorrência corrente.
- Uma soneca provoca violação nova de restrição, entrada/saída do caminho crítico, deslocamento de sucessoras ou aumento do término finito: o preview identifica a categoria e o usuário pode cancelar sem mudança.
- A tarefa ou projeto é excluído: suas ocorrências deixam de aparecer nas views operacionais e seus históricos são removidos em cascata na V1; remover recorrência ou concluir definitivamente preserva o histórico existente.
- Uma regra existente se torna incompatível com uma dependência ou com a hierarquia de seção: o sistema não persiste a alteração e informa a incompatibilidade.
- A expressão natural contém texto de título semelhante a uma recorrência: o sistema exige ação explícita no campo de recorrência e não altera o título automaticamente.
- Data, expressão ou ação de soneca enviada por usuário sem permissão, em tarefa de outro projeto ou com ocorrência já substituída é recusada sem expor dados ou alterar a série.

## Requirements

### Functional Requirements

- **FR-001**: O sistema DEVE permitir que uma tarefa possua zero ou uma recorrência ativa e, quando ativa, uma única ocorrência corrente identificável.
- **FR-002**: O sistema DEVE representar separadamente a regra, a data lógica da ocorrência, seu agendamento e sua conclusão efetiva.
- **FR-003**: O sistema DEVE permitir criar e editar a mesma regra por editor visual ou por expressão natural PT-BR, sem divergência de significado entre as duas entradas.
- **FR-004**: A gramática inicial DEVE aceitar regras diárias, semanais, mensais e anuais; intervalos; listas de dias da semana ou do mês; primeiro ou último dia útil; ordinais mensais; início; término inclusivo; e série sem fim.
- **FR-005**: O sistema DEVE explicar expressões naturais inválidas ou não suportadas, preservar o rascunho e nunca modificar uma regra existente sem confirmação válida.
- **FR-006**: O sistema DEVE tratar padrões de calendário, tais como `toda segunda`, como regras fixas e padrões `a cada N dias`, `a cada N semanas`, `a cada N meses` e `a cada N anos` como regras por intervalo. Intervalos em dias contam exclusivamente dias úteis; intervalos em semanas, meses e anos usam unidades de calendário.
- **FR-006A**: Quando um intervalo mensal não encontrar no destino o mesmo dia da base, a data lógica DEVE ser o último dia válido desse mês. Uma recorrência anual em 29 de fevereiro DEVE usar 28 de fevereiro em anos não bissextos.
- **FR-006B**: Após calcular a data lógica por unidade de calendário, o sistema DEVE normalizar o agendamento para o primeiro dia útil aplicável sem modificar a data lógica ou a fase da série.
- **FR-007**: Ao concluir uma regra fixa, o sistema DEVE selecionar a primeira data compatível estritamente posterior ao maior entre a data agendada da ocorrência e a data efetiva de conclusão.
- **FR-008**: Ao concluir uma regra por intervalo, o sistema DEVE calcular a próxima data a partir do maior entre cursor lógico, data agendada da ocorrência e data efetiva de conclusão, somando o intervalo em dias úteis.
- **FR-009**: O sistema DEVE pular automaticamente todas as ocorrências perdidas e abrir somente a próxima ocorrência futura; a V1 NÃO DEVE oferecer recuperação em fila.
- **FR-010**: Toda conclusão de uma ocorrência recorrente DEVE gerar um histórico consultável com data lógica, agendamento da ocorrência e data efetiva de conclusão.
- **FR-011**: A repetição da mesma confirmação de ocorrência DEVE ser idempotente: não pode criar histórico duplicado nem avançar a série uma segunda vez.
- **FR-012**: O sistema DEVE permitir encerrar a série por data final inclusiva ou por `Concluir definitivamente`; quando não houver próxima ocorrência elegível, a tarefa deve ficar definitivamente concluída.
- **FR-013**: O sistema DEVE permitir remover uma recorrência sem concluir a tarefa, preservando seu planejamento corrente como tarefa não recorrente.
- **FR-014**: O sistema DEVE oferecer as ações de soneca `Amanhã`, `Próxima semana`, `3 dias`, `7 dias` e data escolhida para a ocorrência aberta.
- **FR-015**: `Amanhã` DEVE significar o próximo dia útil; `Próxima semana`, o primeiro dia útil da semana seguinte; e `3 dias` e `7 dias`, a contagem correspondente de dias úteis no calendário do projeto.
- **FR-016**: Uma data escolhida que não seja útil DEVE ser normalizada para o primeiro dia útil posterior e a interface DEVE informar essa normalização antes da confirmação.
- **FR-017**: Soneca e reagendamento pontual DEVEM alterar somente o agendamento da ocorrência corrente, preservar sua duração útil e não alterar a regra de recorrência.
- **FR-018**: Antes de aplicar uma soneca, o sistema DEVE apresentar o impacto do planejamento proposto, incluindo tarefas afetadas, mudança de término finito quando existir, alteração de criticidade e novas violações de restrição; os impactos DEVEM ser classificados como informativos, alertas ou críticos.
- **FR-019**: O usuário DEVE poder cancelar um preview de soneca sem que qualquer regra, ocorrência, data ou relação persistida seja alterada.
- **FR-020**: Uma tarefa recorrente DEVE poder receber dependências como sucessora e sua ocorrência corrente DEVE respeitar bloqueios e restrições resultantes.
- **FR-021**: O sistema DEVE impedir que uma recorrente seja predecessora direta ou indireta por uma seção e DEVE validar o mesmo limite ao criar dependência, mover itens na hierarquia ou ativar/alterar recorrência.
- **FR-022**: Recorrentes contínuas NÃO DEVEM compor o término, a folga, o caminho crítico nem o progresso finitos do projeto, mas DEVEM manter estado operacional próprio.
- **FR-023**: As views Gantt e Tarefas DEVEM exibir de forma consistente a regra legível, a data lógica da ocorrência, seu agendamento quando distinto, o estado operacional e uma indicação textual além de visual da recorrência/soneca.
- **FR-024**: Proprietário e editor DEVEM poder executar as ações de criação, edição, soneca, conclusão, encerramento e remoção da recorrência; leitor DEVE apenas consultar dados autorizados.
- **FR-025**: Toda ação essencial de recorrência, soneca, preview, confirmação, cancelamento e consulta de histórico DEVE possuir operação por teclado e toque, foco perceptível, nome acessível e feedback que não dependa somente de cor.
- **FR-026**: A duplicação de uma tarefa recorrente DEVE criar uma série independente sem copiar o histórico de ocorrências da tarefa original.
- **FR-027**: O sistema DEVE informar claramente quando uma ação edita a série inteira, altera somente a ocorrência corrente ou encerra definitivamente a recorrência.
- **FR-028**: Na V1, a exclusão de uma tarefa ou projeto DEVE remover em cascata os históricos de ocorrência correspondentes. Remover a recorrência ou concluir definitivamente a série NÃO DEVE apagar o histórico existente.

> Decisões de infraestrutura: esta feature não exige execução periódica autônoma; as ocorrências são determinadas em comandos e consultas. Idempotência de conclusão é obrigatória conforme FR-011; `completionCommandId` permanece único enquanto seu registro histórico existir e deixa de existir apenas pela exclusão em cascata definida em FR-028.

### Key Entities

- **Regra de recorrência**: definição estruturada e legível da cadência, tipo de base, início e término de uma série.
- **Ocorrência corrente**: único trabalho recorrente ainda aberto, identificado por data lógica e com agendamento próprio.
- **Data lógica**: data pertencente ao padrão da série, que mantém a cadência mesmo quando o trabalho é adiado.
- **Agendamento da ocorrência**: início e fim planejados para executar a ocorrência corrente, sujeitos a calendário, soneca e restrições.
- **Histórico de ocorrência**: registro imutável de uma ocorrência concluída, contendo datas lógica, agendada e efetiva.
- **Soneca**: alteração pontual, confirmada pelo usuário, do agendamento da ocorrência aberta.
- **Preview de impacto**: comparação legível entre o planejamento atual e o proposto, sem alteração persistida enquanto não confirmado.
- **Indicadores finitos do projeto**: término, folga, caminho crítico e progresso calculados somente sobre trabalho finito elegível.

## Success Criteria

### Measurable Outcomes

- **SC-001**: Em cenários automatizados de regras aceitas, 100% das expressões naturais e configurações visuais equivalentes produzem a mesma regra e primeira ocorrência.
- **SC-002**: Em cenários automatizados de soneca, 100% das datas resultantes são dias úteis do calendário do projeto e preservam a duração planejada da ocorrência.
- **SC-003**: Em cenários automatizados de conclusão repetida, 100% dos retries da mesma ocorrência resultam em exatamente um registro histórico e um único avanço da série.
- **SC-004**: Em cenários automatizados de atraso, 100% das regras avançam diretamente para uma ocorrência futura, sem apresentar fila de ocorrências perdidas.
- **SC-005**: Em cenários de projetos com tarefas finitas e recorrentes, 100% dos cálculos de término, caminho crítico, folga e progresso finitos excluem recorrentes contínuas e preservam os bloqueios que elas recebem.
- **SC-006**: Em testes de aceitação por teclado e toque, usuários autorizados concluem a criação de regra, soneca com preview e conclusão de ocorrência sem uso obrigatório de mouse ou de sintaxe natural.
- **SC-007**: Em projetos com até 2.000 tarefas, 95% dos previews de soneca ficam disponíveis para decisão do usuário em até 1 segundo após a solicitação.
