# Documento de Requisitos — Sistema Web de Planejamento Gantt integrado ao Todoist

**Specification Version:** 1.0

**Specification Status:** Aprovada para desenvolvimento.

**Approved for Development:** Sim


## 1. Objetivo

Desenvolver um sistema web de planejamento de projetos baseado nas tarefas existentes no Todoist.

O sistema utilizará o Todoist como fonte principal das tarefas e de seus campos nativos, acrescentando uma camada própria para:

- visualização em gráfico de Gantt;
- definição de dependências entre tarefas;
- cálculo de caminho crítico;
- planejamento e reagendamento de tarefas;
- calendário de dias úteis e feriados;
- simulação visual de alterações de datas;
- armazenamento de metadados que não existem no Todoist.

O objetivo do MVP é manter a implementação simples, direta e previsível, evitando funcionalidades avançadas de gerenciamento de projetos que não sejam necessárias neste primeiro momento.

---

## 2. Princípios gerais de arquitetura

### 2.1 Todoist como fonte principal das tarefas

O sistema não deverá replicar no banco de dados próprio os campos que já existem no Todoist, salvo se futuramente houver uma necessidade técnica específica de cache.

Os dados nativos das tarefas deverão ser lidos e alterados diretamente através da API do Todoist.

Exemplos de informações nativas mantidas no Todoist:

- ID da tarefa;
- título;
- prioridade;
- projeto;
- seção;
- hierarquia de tarefas e subtarefas;
- estado de conclusão;
- data da tarefa;
- deadline.

### 2.2 Banco de dados próprio

O sistema deverá possuir banco de dados próprio apenas para informações adicionais necessárias à aplicação e que não existam nativamente no Todoist.

Exemplos:

- relacionamento de dependência entre tarefas;
- tipo de dependência;
- configurações do gráfico de Gantt;
- calendário de trabalho;
- feriados;
- configurações de reagendamento;
- usuários;
- sessões;
- credenciais de integração com Todoist;
- identificação dos gráficos/projetos configurados no sistema.

Todos os metadados referentes a uma tarefa deverão utilizar o ID da tarefa do Todoist como referência.

### 2.3 Obrigatoriedade do Todoist

No MVP, toda tarefa utilizada pelo sistema deverá existir no Todoist e possuir um ID válido do Todoist.

O sistema não terá tarefas independentes criadas exclusivamente em seu próprio banco de dados.

---

## 3. Criação de um gráfico de Gantt

Ao criar um novo gráfico, o usuário deverá:

1. estar autenticado no sistema;
2. possuir uma conta do Todoist integrada;
3. escolher um projeto do Todoist;
4. permitir que o sistema carregue:
   - seções;
   - tarefas;
   - subtarefas;
   - hierarquia completa do projeto;
5. visualizar automaticamente o projeto em formato de árvore e gráfico de Gantt.

Cada gráfico criado no sistema ficará associado a um único projeto do Todoist.

---

## 4. Hierarquia do projeto

A hierarquia visual deverá utilizar a própria estrutura existente no Todoist.

### 4.1 Níveis

A estrutura deverá considerar:

1. seção do Todoist;
2. tarefa de primeiro nível;
3. subtarefa;
4. demais níveis de subtarefas, conforme existirem.

### 4.2 Grupos e tarefas efetivas

As tarefas que possuírem filhos poderão funcionar visualmente como agrupadores.

As tarefas-folha representam as atividades efetivamente executáveis no gráfico.

As datas de uma tarefa-pai serão sempre calculadas a partir das tarefas descendentes apenas para planejamento e desenho no Gantt. O Ganttist jamais preencherá ou atualizará automaticamente a data ou o deadline dessa tarefa no Todoist. Mediante autorização opcional do usuário, poderá somente limpar ambos os campos de tarefas que possuam filhos.

O sistema deverá apresentar controles para expandir ou recolher os níveis da hierarquia.

---

## 5. Datas e duração

### 5.1 Unidade mínima

O sistema trabalhará exclusivamente com dias inteiros.

Não existirão:

- horários;
- frações de dia;
- tarefas com duração em horas.

### 5.2 Data inicial

A data da tarefa no Todoist será utilizada como data inicial da atividade no gráfico.

### 5.3 Data final

O campo `deadline` do Todoist será utilizado como data final prevista.

### 5.4 Tarefa com duração de um dia

A duração padrão de uma tarefa será de um dia.

Quando a tarefa começar e terminar no mesmo dia:

- a data da tarefa será informada no Todoist;
- o `deadline` não será preenchido;
- a ausência de `deadline` deverá ser interpretada pelo sistema como duração de um dia.

Essa regra existe para evitar poluição desnecessária do cadastro do Todoist.

### 5.5 Tarefas com mais de um dia

Quando a duração for superior a um dia:

- a data inicial será gravada como data da tarefa;
- a data final será gravada no `deadline`.

### 5.6 Cálculo de duração

A duração exibida no sistema será calculada a partir das datas inicial e final, respeitando o calendário de dias úteis.

Dias não úteis não serão contabilizados na duração.

---

### 5.7 Tarefas sem data

Tarefas existentes no Todoist que não possuam data deverão sempre ser exibidas no sistema.

Elas deverão:

- permanecer exatamente em sua posição correta dentro da hierarquia original de seção, tarefa e subtarefa;
- aparecer normalmente na árvore/tabela à esquerda;
- possuir diferenciação visual clara indicando que ainda não estão planejadas;
- possuir um timeblock provisório de um dia na faixa de hoje enquanto não tiverem uma data inicial, sem persistir essa referência até uma ação explícita do usuário;
- não ser movidas para uma seção artificial de "Não planejadas";
- continuar visíveis mesmo quando não puderem participar do posicionamento temporal do gráfico.

A condição de "não planejada" será apenas um estado visual e lógico da tarefa, sem modificar sua organização no Todoist.

Quando uma data inicial for atribuída, a tarefa deverá passar a possuir representação temporal no Gantt e poderá participar normalmente dos cálculos de dependência, caminho crítico e reagendamento, conforme as demais regras do sistema.

### 5.8 Tarefas-pai e grupos

Tarefas-pai deverão funcionar como grupos de escopo temporal.

Suas datas não serão tratadas como valores independentes. Elas serão sempre derivadas das tarefas descendentes planejadas.

Regras:

- `S(grupo) = min(S dos descendentes planejados)`
- `F(grupo) = max(F dos descendentes planejados)`

A duração do grupo será derivada do intervalo entre essas duas datas, respeitando o calendário de dias úteis.

#### 5.8.1 Sincronização com o Todoist

As datas calculadas do grupo deverão substituir os valores correspondentes da tarefa-pai no Todoist.

Portanto:

- alterações nas tarefas descendentes podem recalcular o intervalo do grupo;
- o core deverá atualizar automaticamente a data e o deadline da tarefa-pai no Todoist;
- alterações manuais feitas diretamente no Todoist nas datas da tarefa-pai não deverão prevalecer;
- na próxima sincronização, o core deverá recalcular e restaurar os valores derivados corretos.

#### 5.8.2 Representação visual

Tarefas-pai não deverão ser exibidas como um time block comum.

No grid do Gantt, deverão utilizar uma barra-resumo diferenciada, visualmente semelhante a um colchete voltado para baixo, indicando o escopo temporal de todas as tarefas descendentes.

Essa barra:

- será somente derivada;
- não poderá ser arrastada;
- não poderá ser redimensionada diretamente;
- será atualizada automaticamente quando qualquer descendente planejado mudar de data.

Descendentes sem planejamento não interferem no cálculo do intervalo.

Se nenhum descendente possuir planejamento, o grupo também não terá representação temporal no grid.

#### 5.8.3 Grupos aninhados

Em hierarquias com vários níveis, os grupos deverão ser calculados de baixo para cima.

O intervalo de um grupo superior deverá refletir o menor início e o maior fim de todos os seus descendentes planejados, direta ou indiretamente.

#### 5.8.4 Grupos em dependências

No MVP, tarefas-pai/grupos poderão participar de dependências **somente como predecessores de tarefas comuns**.

Não serão permitidos grupos como sucessores nem relações grupo→grupo nesta versão.

Para fins de precedência, as datas derivadas do grupo serão utilizadas como referência.

Exemplo:

Em uma relação Fim → Início entre `Grupo A` e `Tarefa B`, `B` somente poderá iniciar no próximo dia útil após a última tarefa planejada de `Grupo A` terminar.

### 5.9 Tratamento de datas incompletas ou inválidas

O sistema deverá tratar de forma determinística tarefas com datas ausentes ou inconsistentes.

#### 5.9.1 Deadline anterior à data inicial

Se o `deadline` for anterior à data inicial da tarefa, o valor deverá ser considerado inválido.

Nesse caso:

- o `deadline` será ignorado para fins de planejamento;
- a tarefa será tratada como uma tarefa de duração padrão de 1 dia;
- o comportamento será equivalente ao de uma tarefa sem `deadline` preenchido;
- o sistema não deverá utilizar esse valor inválido em cálculos de duração, dependência ou caminho crítico.

#### 5.9.2 Tarefa sem data inicial e sem predecessoras

Se uma tarefa não possuir data inicial e também não possuir predecessoras capazes de determinar seu início:

- ela será considerada visualmente não programada;
- continuará aparecendo normalmente na hierarquia à esquerda;
- possuirá um time block visual provisório de um dia na faixa de hoje, distinguível de uma data persistida;
- esse posicionamento não será gravado no Todoist enquanto o usuário não concluir um drag ou edição de data;
- para fins de cálculo, utilizará a data virtual definida posteriormente neste documento, baseada em `OperationalToday`.

#### 5.9.3 Tarefa sem data inicial e com predecessoras

Se uma tarefa não possuir data inicial, mas possuir uma ou mais predecessoras com informação suficiente para determinar seu posicionamento:

- a data inicial deverá ser calculada automaticamente pelo core;
- o cálculo deverá respeitar o tipo de dependência;
- deverá respeitar o calendário de dias úteis;
- deverá utilizar a restrição mais forte quando houver múltiplas predecessoras;
- a duração padrão será de 1 dia caso não exista informação válida que determine duração superior.

Após o cálculo, a tarefa passará a participar normalmente da timeline, dependências, caminho crítico e reagendamento.

### 5.10 Persistência de datas calculadas automaticamente

Quando uma tarefa sem data inicial tiver seu posicionamento determinado pelas predecessoras, o comportamento dependerá do modo de reagendamento configurado.

#### 5.10.1 Modo manual

No modo manual:

- o core poderá calcular a data necessária;
- a interface deverá apresentar essa posição apenas como parte da simulação;
- nenhuma data deverá ser gravada no Todoist antes da confirmação explícita do usuário;
- após a confirmação, a data calculada deverá ser persistida no Todoist.

#### 5.10.2 Modo automático

No modo automático:

- o core deverá calcular a nova data;
- a data deverá ser gravada imediatamente no Todoist;
- a interface deverá refletir a alteração assim que o core concluir a atualização.

### 5.11 Tarefas posicionadas em dias não úteis

Se uma tarefa estiver posicionada em um dia não útil, seja por alteração direta no Todoist ou por mudança posterior no calendário do projeto, o sistema deverá considerar essa situação uma inconsistência de calendário.

No modo manual:

- a tarefa deverá permanecer visível na posição recebida;
- deverá possuir destaque visual indicando a inconsistência;
- não deverá ser corrigida silenciosamente;
- deverá ser incluída na próxima simulação de reagendamento;
- na simulação, deverá ser deslocada para o próximo dia útil válido;
- sua duração deverá ser preservada.

No modo automático:

- a tarefa deverá ser corrigida imediatamente;
- deverá ser deslocada para o próximo dia útil válido;
- sua duração deverá ser preservada;
- a nova data deverá ser sincronizada com o Todoist.

### 5.12 Regras consolidadas de calendário e tarefas sem data

#### 5.12.1 Deadline em dia não útil

Quando um `deadline` cair em dia não útil, a regra padrão será ajustá-lo para o dia útil imediatamente anterior.

Essa política deverá ser configurável nas configurações de cálculo automático do projeto, permitindo que o comportamento seja alterado futuramente sem modificar a regra central do domínio.

#### 5.12.2 Alteração do calendário de trabalho

A inclusão ou remoção de um dia útil deverá preservar a duração das tarefas em dias úteis.

Quando um dia que estava dentro do intervalo de uma tarefa mudar de condição:

- a duração original em dias úteis deverá ser preservada;
- a data final deverá ser recalculada;
- se o início da tarefa cair em um dia que passou a ser não útil, o início deverá avançar para o próximo dia útil válido;
- dependências deverão ser recalculadas em cascata;
- sucessoras afetadas deverão respeitar novamente todas as regras de precedência.

No modo manual, essas alterações deverão ser apresentadas por meio da simulação de reagendamento antes da persistência.

No modo automático, o core poderá aplicar e sincronizar as alterações imediatamente.

Uma tarefa futura independente, sem predecessoras, cuja data inicial continue válida após a alteração do calendário, deverá manter sua data inicial programada. A simples criação ou remoção de dias úteis anteriores à sua data não deverá antecipá-la ou deslocá-la.

#### 5.12.3 Operações manuais em dias bloqueados

A interface deverá impedir que o usuário crie diretamente uma configuração temporal incompatível com o calendário vigente.

Operações de drag, resize ou edição de datas deverão respeitar os dias bloqueados pelo calendário e aplicar o comportamento de encaixe definido pela interface.

#### 5.12.4 Alteração manual da data inicial

Quando o usuário alterar explicitamente a data inicial de uma tarefa planejada, a duração deverá ser preservada e a data final recalculada.

Essa operação deverá ser equivalente ao deslocamento horizontal da barra inteira no Gantt.

#### 5.12.5 Semântica de tarefas sem data

Uma tarefa sem data inicial continuará sendo considerada visualmente **não programada**:

- permanecerá em sua posição hierárquica;
- receberá destaque visual de tarefa não programada;
- terá um time block provisório de um dia exibido na faixa de hoje;
- esse time block representa uma referência visual e não uma data persistida enquanto não houver drop ou confirmação equivalente.

Entretanto, para fins de cálculo, o sistema deverá atribuir a ela uma **data virtual igual à data atual** quando nenhuma outra restrição determinar uma data posterior.

A duração virtual padrão será de 1 dia quando não houver `deadline` válido.

Assim, uma tarefa sem data poderá participar do cálculo de dependências mesmo antes de possuir uma data persistida.

Exemplo:

- tarefa `A` sem data e sem predecessoras: para cálculo, `A` é considerada como iniciando hoje e com duração de 1 dia;
- tarefa `B` com dependência FS de `A`: `B` somente poderá iniciar no próximo dia útil após hoje, salvo se outra restrição exigir data posterior.

Se a tarefa sem data possuir predecessoras, sua data virtual deverá ser calculada respeitando essas precedências. A data efetiva calculada será a mais tardia entre a referência de hoje e as restrições impostas pelas predecessoras.

A interface deverá permitir indicar visualmente a referência temporal calculada para hoje sem confundi-la com uma tarefa efetivamente programada no Todoist.

#### 5.12.5.1 Drag direto de timeblocks

O drag horizontal de uma tarefa folha será uma edição direta de sua data inicial no Todoist:

- durante o movimento, somente um ghost sem texto será deslocado dentro da linha original;
- o encaixe ocorrerá exclusivamente em colunas de dias civis inteiros;
- `Esc` cancelará o gesto sem persistência;
- no drop, a nova data inicial será gravada imediatamente;
- quando existir `deadline`, ele receberá o mesmo deslocamento em dias civis, preservando a duração;
- quando não existir `deadline`, ele continuará ausente;
- falha de persistência restaurará integralmente a posição anterior.

Timeblocks planejados terão largura igual a um número inteiro de colunas, cobrindo da data inicial ao `deadline` inclusive. Sem `deadline`, terão exatamente uma coluna. Nenhum texto será exibido dentro do timeblock no MVP atual.

#### 5.12.6 Opção para eliminar tarefas sem data

As configurações de cálculo automático poderão possuir uma opção equivalente a **Não permitir tarefas sem data**.

Quando habilitada:

- ao encontrar uma tarefa sem data, o core deverá calcular sua primeira data válida;
- sem predecessoras, a referência inicial será hoje;
- com predecessoras, deverão ser respeitadas as regras de precedência;
- na ausência de `deadline`, a duração será de 1 dia;
- a data calculada deverá ser persistida no Todoist conforme o modo de reagendamento configurado.

Quando essa opção estiver desabilitada, a tarefa permanecerá não programada no Todoist, embora sua data virtual possa ser utilizada nos cálculos de dependência.

### 5.13 Demais regras temporais aprovadas

#### 5.13.1 Alteração dos dias úteis da semana

Alterações na definição dos dias úteis deverão seguir as mesmas regras aplicadas à inclusão e remoção de feriados.

Datas existentes que continuarem válidas deverão ser preservadas. Mudanças que tornem datas ou durações inconsistentes deverão provocar simulação de reagendamento no modo manual e recálculo em cascata no modo automático.

#### 5.13.2 Redimensionamento atravessando dias não úteis

Ao redimensionar uma tarefa, sua borda deverá terminar sempre em um dia útil válido.

Dias não úteis poderão permanecer visualmente dentro do intervalo da barra, mas não contarão para a duração.

#### 5.13.3 Alteração manual da data final

Quando o usuário alterar diretamente a data final, a data inicial deverá ser preservada e a duração recalculada em dias úteis.

#### 5.13.4 Remoção do deadline

Quando o usuário remover o `deadline` de uma tarefa planejada, ela passará a possuir duração de 1 dia, mantendo sua data inicial.

Nenhuma duração anterior deverá permanecer armazenada implicitamente.

#### 5.13.5 Tarefas recorrentes

No MVP, tarefas recorrentes do Todoist deverão permanecer visíveis na hierarquia, com diferenciação visual própria, mas não participarão da timeline como tarefas planejáveis enquanto mantiverem a recorrência.

O suporte completo a recorrências ficará para versão futura.

#### 5.13.6 Tarefa concluída sem planejamento anterior

Uma tarefa concluída que nunca possuiu data planejada deverá preservar essa condição histórica.

Sua `effective_completion_date` poderá ser exibida como informação de conclusão e referência temporal, sem inventar retroativamente uma data planejada.

#### 5.13.7 Conclusão anterior ao prazo planejado

Quando uma tarefa for concluída antes do prazo previsto:

- a barra planejada original deverá ser preservada;
- a data efetiva de conclusão deverá ser apresentada por indicador visual;
- o deadline histórico não deverá ser reduzido automaticamente.

#### 5.13.8 Conclusão posterior ao prazo planejado

Quando uma tarefa for concluída depois do deadline:

- o planejamento original deverá ser preservado;
- a interface deverá representar visualmente a diferença até a data efetiva de conclusão;
- dependências posteriores deverão utilizar a data efetiva de conclusão definida pelas regras de `effective_completion_date`.

#### 5.13.9 Fuso horário

Cada usuário deverá possuir um timezone aplicável à interpretação de timestamps externos.

O timezone poderá ser inicialmente detectado pelo navegador.

Timestamps recebidos do Todoist deverão ser convertidos para o timezone aplicável antes da extração da data utilizada pelo domínio do Gantt.

Após essa conversão, os cálculos de planejamento deverão trabalhar apenas com datas de calendário.

#### 5.13.10 Horário de verão

Os cálculos do Gantt não deverão assumir que um dia equivale a 86.400 segundos.

Mudanças de horário de verão não poderão alterar duração ou posicionamento das tarefas.

#### 5.13.11 Regra canônica de duração

A duração é uma quantidade de dias úteis de calendário, nunca uma quantidade de horas.

Formalmente:

`D(T) = CountWorkDays(S(T), F(T))`

e:

`F(T) = AddWorkDays(S(T), D(T) - 1)`

Nenhum algoritmo do core deverá calcular duração dividindo diferenças entre timestamps por 24 horas.

## 6. Sincronização de datas com Todoist

Ao enviar datas para o Todoist, o sistema deverá transmitir exclusivamente datas de calendário.

Nunca deverá ser enviado componente de hora.

Essa regra se aplica tanto:

- à data inicial;
- quanto ao deadline.

O sistema não deverá gerar horários implicitamente.

---

## 7. Dependências entre tarefas

O sistema deverá permitir relacionamentos de precedência entre tarefas.

### 7.1 Convenções matemáticas

Para cada tarefa `T`:

- `S(T)` = data de início;
- `F(T)` = data de fim;
- `D(T)` = duração da tarefa em dias úteis;
- `Next(x)` = próximo dia útil após a data `x`;
- `Prev(x)` = dia útil anterior à data `x`;
- `AddWorkDays(x, n)` = soma de `n` dias úteis à data `x`;
- `SubtractWorkDays(x, n)` = subtração de `n` dias úteis da data `x`.

Para uma tarefa de um dia:

`F(T) = S(T)`

Para uma tarefa de `D` dias:

`F(T) = AddWorkDays(S(T), D(T) - 1)`

O próprio dia inicial conta como o primeiro dia da duração.

### 7.2 Tipos suportados

O MVP deverá suportar os quatro tipos clássicos de dependência:

#### 7.2.1 Fim → Início — FS

A tarefa sucessora `B` somente pode iniciar após o término da predecessora `A`.

Regra:

`S(B) >= Next(F(A))`

Exemplo:

Se `A` termina na terça-feira, `B` poderá começar, no mínimo, na quarta-feira.

Se `A` termina na sexta-feira e sábado e domingo não forem úteis, `B` poderá começar, no mínimo, na segunda-feira.

Essa regra também se aplica quando `A` possui duração de apenas um dia.

#### 7.2.2 Início → Início — SS

A tarefa sucessora `B` não pode começar antes que a predecessora `A` tenha começado.

Regra:

`S(B) >= S(A)`

As durações de `A` e `B` permanecem independentes.

#### 7.2.3 Fim → Fim — FF

A tarefa sucessora `B` não pode terminar antes que a predecessora `A` termine.

Regra:

`F(B) >= F(A)`

Quando for necessário deslocar `B`, sua data de início deverá ser recalculada preservando a duração:

`S(B) = SubtractWorkDays(F(B), D(B) - 1)`

#### 7.2.4 Início → Fim — SF

A tarefa sucessora `B` não pode terminar antes que a predecessora `A` comece.

Regra:

`F(B) >= S(A)`

Quando for necessário deslocar `B`, sua data inicial deverá ser recalculada mantendo sua duração constante.

### 7.3 Regra fundamental de reagendamento por dependência

O algoritmo automático de correção de precedências deverá operar apenas para frente no tempo.

Portanto:

- se a dependência já estiver satisfeita, nenhuma data será alterada;
- se estiver violada, a tarefa sucessora será deslocada somente o número mínimo de dias úteis necessário;
- o algoritmo nunca deverá antecipar automaticamente uma tarefa;
- folgas existentes não deverão ser utilizadas para puxar tarefas para datas anteriores.

O objetivo é manter o comportamento previsível e evitar reorganizações desnecessárias do planejamento.

### 7.4 Preservação de duração

Reagendamentos provocados por dependências não poderão alterar a duração da tarefa.

Ao deslocar uma tarefa:

- sua data inicial poderá mudar;
- sua data final será recalculada;
- sua duração em dias úteis permanecerá constante.

A duração somente poderá ser modificada por uma ação explícita do usuário, como o redimensionamento da barra no gráfico.

### 7.5 Múltiplas predecessoras

Uma tarefa poderá possuir mais de uma predecessora.

Todas as restrições deverão ser satisfeitas simultaneamente.

Quando diversas dependências impuserem datas mínimas diferentes, deverá prevalecer a restrição mais forte, ou seja, aquela que exigir o deslocamento mais tardio da tarefa sucessora.

Exemplo:

- uma dependência exige início em `10/08`;
- outra exige início em `13/08`;
- outra exige início em `12/08`.

A tarefa deverá iniciar, no mínimo, em `13/08`.

O mesmo princípio deverá ser aplicado às restrições que atuem sobre datas de término.

### 7.6 Propagação em cascata

Sempre que uma tarefa for deslocada, todas as suas sucessoras deverão ser reavaliadas.

Exemplo:

`A → B → C → D`

Se `A` for alterada:

1. recalcular `B`;
2. se `B` mudar, recalcular `C`;
3. se `C` mudar, recalcular `D`;
4. continuar até que nenhuma tarefa adicional precise ser deslocada.

O processamento deverá ocorrer em ordem topológica da rede de dependências.

### 7.7 Dependências circulares

Dependências circulares são inválidas.

Exemplo inválido:

`A → B`  
`B → C`  
`C → A`

Antes de criar ou alterar uma dependência, o core deverá verificar se a operação produzirá um ciclo.

Se produzir:

- a dependência não será criada;
- nenhuma data será alterada;
- a interface deverá informar ao usuário que a relação criaria uma dependência circular.

### 7.8 Tarefas concluídas e data efetiva de conclusão

Tarefas concluídas nunca deverão ser deslocadas por reagendamento.

Elas, entretanto, continuarão válidas como predecessoras para as demais tarefas.

A data efetiva de conclusão utilizada pelo sistema seguirá a seguinte regra de precedência:

`effective_completion_date = completion_date_override ?? Todoist.completed_at`

#### 7.8.1 Conclusão realizada pela aplicação

Quando a tarefa for concluída através da própria aplicação, o core deverá utilizar o mecanismo de conclusão disponibilizado pela API do Todoist que permita informar a data de conclusão.

Para o usuário, a aplicação continuará trabalhando exclusivamente com datas, sem horários.

Caso a API exija tecnicamente um timestamp, o core fará a conversão necessária apenas para comunicação com o Todoist. O componente de hora não terá significado no planejamento e não será apresentado ao usuário.

#### 7.8.2 Conclusão realizada diretamente no Todoist

Quando a tarefa for concluída diretamente no Todoist, o sistema deverá utilizar `completed_at` como fonte padrão da data real de conclusão.

A aplicação poderá exibir essa informação mesmo que ela não seja apresentada de forma equivalente na interface padrão do Todoist.

#### 7.8.3 Correção manual da data de conclusão

A aplicação deverá permitir que o usuário corrija a data efetiva de conclusão de uma tarefa já concluída.

Como a data de conclusão de uma tarefa já concluída não deve ser reescrita no Todoist por meio de operações artificiais de reabertura e nova conclusão, a correção será armazenada exclusivamente no banco próprio no campo lógico:

`completion_date_override`

Quando esse campo possuir valor, ele terá precedência sobre `Todoist.completed_at` em todos os cálculos do sistema, incluindo dependências, histórico temporal e análises futuras.

Quando o override for removido, o sistema voltará automaticamente a utilizar `Todoist.completed_at`.

A interface deverá indicar discretamente quando a data efetiva exibida corresponde a um override, diferenciando-a da data original informada pelo Todoist.

O sistema não deverá reabrir e concluir novamente uma tarefa no Todoist apenas para corrigir retroativamente sua data de conclusão, evitando efeitos colaterais sobre o histórico e o estado da tarefa.

### 7.9 Criação de dependência que gera inconsistência

O usuário poderá criar uma dependência mesmo quando as datas atuais das tarefas não satisfizerem a nova relação.

Nesse caso, no modo manual:

- a dependência será criada;
- a inconsistência será identificada visualmente;
- as datas não serão alteradas automaticamente;
- o sistema deverá disponibilizar a função **Simular Reagendamento**.

Se o projeto estiver com reagendamento automático habilitado, o core poderá corrigir imediatamente a violação conforme as regras deste documento.

### 7.10 Lag e lead

O MVP utilizará:

- `lag = 0`;
- `lead = 0`.

Não existirão, nesta versão, regras como:

- iniciar três dias depois;
- terminar dois dias antes;
- aplicar atraso adicional configurável entre tarefas.

Suporte a lag e lead ficará para versão futura.

### 7.11 Armazenamento

As dependências deverão ser armazenadas no banco próprio da aplicação utilizando os IDs das tarefas do Todoist.

Para cada dependência deverão ser armazenados, no mínimo:

- ID da tarefa predecessora;
- ID da tarefa sucessora;
- tipo de dependência: FS, SS, FF ou SF.

## 8. Caminho crítico

O sistema deverá calcular e exibir o caminho crítico do projeto com base:

- nas durações;
- nas dependências;
- no calendário de dias úteis.

A visualização deverá permitir distinguir claramente as tarefas pertencentes ao caminho crítico.

Não será implementado, neste MVP, um sistema avançado de folga/slack para edição ou planejamento.

---

## 9. Calendário de trabalho

Cada gráfico/projeto deverá permitir definir:

- quais dias da semana são dias úteis;
- quais dias da semana não são úteis;
- feriados específicos.

### 9.1 Comportamento dos dias não úteis

Dias não úteis:

- não deverão contar na duração das tarefas;
- não deverão ser utilizados automaticamente para posicionar tarefas;
- poderão ser exibidos ou ocultados na interface.

Quando exibidos, deverão possuir diferenciação visual clara, por exemplo:

- fundo acinzentado;
- hachura;
- outra sinalização visual fixa do MVP.

---

## 10. Status das tarefas

O MVP não terá:

- percentual manual de conclusão;
- progresso numérico;
- estados personalizados.

O status deverá ser derivado automaticamente das datas e do estado de conclusão no Todoist.

Regras gerais:

- tarefa futura: não iniciada;
- tarefa cuja data inicial já chegou e ainda está dentro do período previsto: em execução;
- tarefa não concluída cujo prazo final já passou: atrasada;
- tarefa marcada como concluída no Todoist: concluída.

Para uma tarefa de um dia sem deadline, a própria data deverá funcionar como limite previsto.

---

## 11. Interface principal

A interface deverá seguir o padrão clássico de gráfico de Gantt.

### 11.1 Área esquerda

A tabela hierárquica deverá apresentar, no mínimo:

1. checkbox de seleção;
2. identificação/título da tarefa;
3. prioridade;
4. data inicial;
5. data final;
6. duração em dias.

A árvore deverá possuir controles para expandir e recolher grupos.

### 11.2 Área direita

A área temporal deverá apresentar:

- grade de calendário;
- barras das tarefas;
- linhas de dependência;
- caminho crítico;
- dias não úteis;
- indicação do período visível.

A tabela e o gráfico deverão permanecer verticalmente sincronizados.

---

## 12. Barra do Sistema

A parte superior da interface será chamada de **Barra do Sistema**.

Ela conterá ações globais sobre o gráfico/projeto.

Entre as funções previstas:

- expandir todos os itens;
- recolher todos os itens;
- alterar a escala visual;
- exibir ou ocultar dias não úteis;
- sincronizar;
- iniciar simulação de reagendamento;
- acessar configurações do projeto;
- abrir configurações gerais aplicáveis ao gráfico.

Configurações visuais personalizáveis não fazem parte do MVP.

---

## 13. Barra de Itens Selecionados

A barra inferior será chamada de **Barra de Itens Selecionados** ou **Barra de Seleção**.

Ela deverá aparecer somente quando houver uma ou mais tarefas marcadas.

A primeira coluna da tabela deverá possuir checkbox para seleção.

A barra permitirá ações em lote somente sobre os itens selecionados.

Deverá existir distinção clara entre:

- tarefa ativa, selecionada por clique;
- tarefas marcadas por checkbox.

A seleção múltipla deverá ocorrer pelos checkboxes.

---

## 14. Interação direta com as barras do Gantt

### 14.1 Movimentação horizontal

Arrastar uma barra inteira horizontalmente deverá:

- alterar a data inicial;
- alterar a data final proporcionalmente;
- preservar a duração.

### 14.2 Alteração pela borda esquerda

Arrastar a borda esquerda deverá:

- alterar a data inicial;
- manter a data final;
- recalcular a duração.

### 14.3 Alteração pela borda direita

Arrastar a borda direita deverá:

- manter a data inicial;
- alterar a data final;
- recalcular a duração.

### 14.4 Ajuste em dias inteiros

Todo movimento ou redimensionamento deverá encaixar em dias inteiros.

Nenhuma operação poderá produzir horas ou frações de dia.

---

## 15. Criação visual de dependências

Cada barra deverá possuir pontos de conexão em suas extremidades.

O usuário poderá criar uma dependência arrastando um conector de uma tarefa até outra.

A combinação entre ponto de origem e ponto de destino deverá determinar o tipo de relação.

As dependências deverão ser desenhadas utilizando linhas ortogonais:

- segmentos horizontais e verticais;
- ângulos de 90 graus;
- seta indicando a tarefa de destino.

O objetivo é garantir legibilidade mesmo quando houver múltiplas dependências.

---

## 16. Seleção e edição

### 16.1 Clique simples

Um clique sobre uma linha ou tarefa define a tarefa ativa.

### 16.2 Checkbox

O checkbox marca a tarefa para ações em lote.

### 16.3 Duplo clique

O duplo clique deverá abrir a edição da tarefa.

---

## 17. Painel de edição

A edição não deverá utilizar a interface do Todoist.

O sistema terá interface própria e realizará as alterações através da API.

A preferência para o MVP é utilizar um **painel lateral de propriedades**, e não um modal.

O painel deverá manter o gráfico visível enquanto a tarefa é editada.

Poderá ser dividido em áreas ou abas como:

- Geral;
- Dependências;
- Informações.

### 17.1 Campos nativos

Campos pertencentes ao Todoist deverão ser atualizados através da API do Todoist.

### 17.2 Metadados próprios

Campos adicionais da aplicação deverão ser atualizados no banco de dados próprio.

---

## 18. Sincronização Todoist ↔ Sistema

A integração deverá ser bidirecional.

### 18.1 Alterações feitas no sistema

Alterações em campos nativos deverão ser enviadas ao Todoist através da API.

### 18.2 Alterações feitas no Todoist

Eventos recebidos por webhook deverão atualizar o core da aplicação.

A interface deverá refletir as alterações recebidas sem exigir recarregamento manual da página.

### 18.3 Fluxo arquitetural

A regra arquitetural deverá ser:

**Interface → Core da aplicação → Todoist**

e, para eventos externos:

**Todoist/Webhook → Core da aplicação → Interface**

A interface não deverá implementar regras de negócio isoladas que possam divergir do core.

### 18.4 Atualização em tempo real

O front-end deverá possuir mecanismo para receber mudanças do core e atualizar os elementos afetados.

A tecnologia específica poderá ser definida durante a implementação, por exemplo:

- WebSocket;
- Server-Sent Events;
- mecanismo equivalente.

---

## 19. Conflitos de sincronização

Se uma tarefa estiver sendo editada e uma atualização externa chegar:

1. a atualização deverá ser recebida pelo core;
2. o sistema deverá evitar edição concorrente inconsistente;
3. a interface deverá indicar discretamente que houve sincronização;
4. o item deverá ser recarregado com o estado considerado válido;
5. durante operações críticas, poderá existir bloqueio temporário e leve de edição.

A fonte final da verdade para campos nativos deverá continuar sendo o Todoist.

---

## 18A. Modelo de dados próprio

### 18A.1 Princípio geral

O banco próprio deverá armazenar identidade, integração, configurações, relacionamentos e histórico operacional da aplicação.

Não deverá existir, no MVP, uma tabela autoritativa que replique os campos nativos das tarefas do Todoist.

IDs de tarefas e projetos do Todoist deverão ser tratados como identificadores externos opacos.

### 18A.2 Usuários

Entidade `user`, contendo no mínimo:

- `id`;
- `email` único;
- `timezone`;
- status da conta;
- `created_at`;
- `updated_at`.

### 18A.3 Sessões

Entidade `session`, permitindo múltiplas sessões simultâneas por usuário, contendo:

- `id`;
- `user_id`;
- hash do token;
- criação;
- última utilização;
- expiração;
- revogação;
- informações mínimas de dispositivo/navegador, quando úteis.

### 18A.4 Integração Todoist

Entidade `todoist_integration`, em relação 1:1 com o usuário no MVP, contendo:

- `id`;
- `user_id`;
- identificação externa da conta Todoist;
- credenciais OAuth protegidas;
- status;
- data da autorização;
- última sincronização;
- token de sincronização incremental, quando aplicável.

### 18A.5 Projeto Gantt

Entidade `gantt_project`, contendo:

- `id`;
- `user_id`;
- `todoist_project_id`;
- status;
- data de criação;
- referências às configurações próprias.

No MVP deverá existir unicidade em:

`(user_id, todoist_project_id)`

Portanto, um mesmo usuário não poderá criar mais de um gráfico Gantt para o mesmo projeto Todoist.

Essa restrição evita conjuntos concorrentes de dependências, calendários e regras de reagendamento disputando as mesmas tarefas.

### 18A.6 Configurações

Entidade `project_settings`, em relação 1:1 com `gantt_project`, contendo configurações como:

- reagendamento manual ou automático;
- política para deadline em dia não útil;
- opção de não permitir tarefas sem data;
- escala visual padrão;
- exibição de dias não úteis;
- demais configurações próprias do projeto.

### 18A.7 Semana de trabalho

A semana padrão poderá ser armazenada por sete flags booleanas correspondentes aos dias da semana.

### 18A.8 Exceções de calendário

Entidade `calendar_exception`, contendo:

- `id`;
- `gantt_project_id`;
- `date`;
- tipo `NON_WORKING` ou `WORKING`;
- descrição opcional.

### 18A.9 Dependências

Entidade `task_dependency`, contendo:

- `id`;
- `gantt_project_id`;
- `predecessor_todoist_task_id`;
- `successor_todoist_task_id`;
- tipo `FS`, `SS`, `FF` ou `SF`;
- status ativo/inativo;
- timestamps.

Não deverão ser permitidas:

- dependências duplicadas idênticas;
- dependência de uma tarefa para ela mesma;
- dependências circulares;
- dependências entre tarefas pertencentes a projetos Todoist diferentes.

### 18A.10 Metadados por tarefa

Entidade `task_metadata`, criada somente quando existir dado próprio da aplicação, contendo:

- `id`;
- `gantt_project_id`;
- `todoist_task_id`;
- `completion_date_override`, quando aplicável;
- status do metadado;
- timestamps.

Não deverão ser copiados título, prioridade, datas ou demais campos nativos sem necessidade técnica explícita.

### 18A.11 Reagendamentos e itens

Entidade `recalculation` para cada operação lógica de reagendamento e entidade `recalculation_item` para cada tarefa afetada, armazenando antes/depois, estados, UUIDs de sincronização, tentativas e erros.

### 18A.12 Fila persistente

Entidade `sync_operation` ou equivalente deverá manter operações de sincronização que precisam sobreviver a reinicializações do servidor.

### 18A.13 Eventos Todoist

Entidade enxuta `todoist_event` deverá registrar eventos necessários para processamento, deduplicação e diagnóstico, submetida a política de retenção.

### 18A.14 Tarefa movida para outro projeto Todoist

Mover uma tarefa para outro projeto no Todoist deverá ser tratado de forma diferente da exclusão definitiva da tarefa.

Ao detectar que uma tarefa anteriormente pertencente ao Gantt foi movida para outro projeto Todoist, o sistema deverá colocá-la em um estado de **movida / pendente de resolução**, sem apagar imediatamente seu histórico ou suas relações.

Enquanto estiver nesse estado:

- a tarefa continuará aparecendo no Gantt em sua posição histórica, com indicação visual clara de que não pertence mais ao projeto Todoist associado;
- não deverão ser enviadas alterações para essa tarefa no Todoist;
- a tarefa deverá permanecer bloqueada para edição pela aplicação;
- suas dependências, posição hierárquica anterior e demais metadados deverão ser preservados temporariamente;
- o sistema deverá apresentar aviso ao usuário solicitando uma decisão explícita.

O usuário deverá possuir duas opções principais:

#### 18A.14.1 Restaurar tarefa ao projeto

Se o usuário optar por restaurar:

- a aplicação deverá mover a tarefa de volta para o projeto Todoist original;
- deverá restaurar a seção anterior, quando ainda existir;
- deverá restaurar a posição/hierarquia anterior, quando tecnicamente possível;
- deverão ser preservadas as dependências de precedência existentes;
- descendentes e relações hierárquicas deverão ser mantidos quando ainda forem válidos;
- após a restauração, o core deverá reconciliar o estado e recalcular grupos, dependências e caminho crítico afetados.

Caso a seção, tarefa-pai ou outro elemento hierárquico anterior não exista mais, a aplicação deverá sinalizar essa condição e utilizar uma posição segura antes de permitir a conclusão da restauração.

#### 18A.14.2 Confirmar remoção do Gantt

Se o usuário confirmar que a tarefa realmente deve deixar o projeto:

- a tarefa deverá ser removida da visualização ativa;
- suas dependências como predecessora ou sucessora deverão ser eliminadas do fluxo ativo;
- seus metadados específicos do Gantt poderão ser removidos ou arquivados conforme a política de retenção;
- grupos, caminho crítico e cadeias afetadas deverão ser recalculados;
- nenhuma alteração deverá ser feita na tarefa que permanece no outro projeto Todoist.

A remoção definitiva do Gantt deverá ocorrer somente após confirmação explícita do usuário.

### 18A.15 Tarefa excluída no Todoist

Se a tarefa tiver sido realmente excluída no Todoist, e não apenas movida:

- não haverá fluxo de restauração automática para o projeto;
- a referência da tarefa deverá ser removida do Gantt;
- dependências de precedência relacionadas deverão ser removidas do fluxo ativo;
- metadados próprios da tarefa deverão ser eliminados ou arquivados conforme a política de retenção;
- grupos, caminho crítico e cadeias afetadas deverão ser recalculados;
- a aplicação não deverá tentar recriar silenciosamente a tarefa excluída.

A distinção entre **movida** e **excluída** deverá ser preservada explicitamente pelo core.

### 18A.16 Exclusão do Gantt

A exclusão de um Gantt da aplicação nunca deverá excluir automaticamente o projeto ou tarefas do Todoist.

Somente os metadados e configurações pertencentes à aplicação poderão ser removidos ou arquivados conforme a política definida.

### 18A.17 Segurança

- tokens de sessão deverão ser armazenados por hash;
- tokens OAuth Todoist deverão ser criptografados em repouso;
- segredos da aplicação OAuth deverão permanecer em configuração segura do servidor;
- credenciais não deverão ser expostas ao front-end.

### 18A.18 Regra geral

> O banco próprio armazena identidade, integração, configuração, relacionamentos e histórico operacional. O Todoist permanece fonte dos campos nativos. Um Gantt corresponde univocamente a um projeto Todoist por usuário, e todas as dependências do MVP são estritamente intraprojeto.

## 18B. Caminho crítico

### 18B.1 Escopo e princípio matemático

O caminho crítico será calculado sobre o grafo de tarefas executáveis e suas dependências.

A criticidade será determinada pela folga total em dias úteis:

`TotalFloat = LS - ES`

Uma tarefa será crítica quando:

`TotalFloat = 0`

Poderão existir múltiplos caminhos críticos simultaneamente.

### 18B.2 Duração e calendário

A duração utilizada será sempre a duração canônica em dias úteis:

`D(T) = CountWorkDays(S(T), F(T))`

Todos os cálculos deverão utilizar as mesmas funções de calendário do motor de reagendamento, incluindo `NextWorkDay`, `PrevWorkDay`, `AddWorkDays`, `SubtractWorkDays` e `CountWorkDays`.

Não deverão ser utilizados cálculos baseados em horas ou divisão de timestamps por 24 horas.

### 18B.3 Grafo e ciclos

As relações FS, SS, FF e SF deverão formar um grafo dirigido acíclico para fins de cálculo.

Se for detectado ciclo:

- a cadeia afetada não poderá ter caminho crítico calculado de forma válida;
- o ciclo deverá ser sinalizado como erro;
- a interface deverá permitir ao usuário identificar as relações envolvidas.

### 18B.4 Forward pass e backward pass

O core deverá calcular:

- `ES` — Early Start;
- `EF` — Early Finish;
- `LS` — Late Start;
- `LF` — Late Finish.

O forward pass deverá ocorrer em ordem topológica, aplicando todas as restrições das predecessoras.

O backward pass deverá partir da data final global calculada do projeto.

O mesmo motor de restrições usado no reagendamento deverá ser utilizado no cálculo do caminho crítico.

### 18B.5 Data final global

A data final operacional do projeto será a maior data final válida entre as tarefas relevantes após aplicação das dependências e do calendário.

Cadeias independentes serão avaliadas contra essa mesma data final global.

### 18B.6 Tarefas-pai e grupos

Grupos não serão nós independentes no cálculo matemático do caminho crítico.

O cálculo ocorrerá sobre tarefas executáveis/folhas.

Quando um grupo possuir descendente crítico, ele deverá receber indicação visual equivalente a **contém tarefa crítica**, sem ser classificado como tarefa crítica propriamente dita.

### 18B.7 Tarefas isoladas

Tarefas isoladas poderão ser críticas se matematicamente possuírem folga total zero em relação ao término global do projeto.

### 18B.8 Tarefas concluídas

Tarefas concluídas permanecerão na rede histórica.

Para o planejamento operacional restante, será utilizada a `effective_completion_date` como realidade conhecida.

Conclusão posterior ao previsto poderá deslocar sucessoras e alterar o caminho crítico.

Conclusão anterior ao previsto poderá liberar sucessoras mais cedo e criar nova folga, preservando-se visualmente o planejamento histórico original.

### 18B.9 Tarefas sem data

Tarefas sem data persistida poderão participar do cálculo utilizando sua data virtual:

`virtual_start = max(hoje, restrições das predecessoras)`

A ausência de data persistida continuará sendo indicada visualmente como tarefa não programada.

### 18B.10 Dependências

O cálculo deverá suportar integralmente FS, SS, FF e SF e utilizar exatamente as mesmas fórmulas e regras de precedência definidas para o reagendamento.

### 18B.11 Alterações que provocam recálculo

Deverão provocar recálculo do caminho crítico, quando relevantes:

- alteração de calendário;
- alteração de datas;
- alteração de duração;
- criação, remoção ou mudança de dependência;
- conclusão de tarefa;
- mudança da data efetiva de conclusão;
- reagendamento;
- mudança hierárquica que afete grupos ou relações;
- exclusão ou restauração de tarefa.

### 18B.12 Caminho crítico em simulações

Durante uma simulação de reagendamento, o caminho crítico deverá ser recalculado sobre o cenário simulado sem persistir alterações.

No MVP, a comparação visual será simples:

- o caminho crítico atual poderá permanecer em apresentação discreta;
- o caminho crítico simulado receberá o destaque principal;
- tarefas que entram ou saem da criticidade poderão receber indicação visual simples.

Não será necessária uma interface analítica avançada de comparação no MVP.

### 18B.13 Apresentação visual

Tarefas críticas deverão possuir destaque visual fixo e claramente distinguível das demais tarefas.

As linhas de dependência pertencentes ao caminho crítico também deverão ser destacadas.

Grupos contendo tarefas críticas deverão possuir marcador ou variação visual em sua barra-resumo.

### 18B.14 Folga

A folga será calculada internamente para determinação da criticidade.

No MVP não será necessário oferecer edição, configuração ou painel detalhado de folgas.

Exibição numérica avançada de slack poderá ser avaliada futuramente.

### 18B.15 Performance

Durante drag contínuo não será necessário executar o cálculo completo a cada pixel.

A interface apresentará um ghost local encaixado em dias inteiros e executará a persistência consistente no drop. Para o deslocamento direto de uma única tarefa, a escrita será imediata no Todoist; operações compostas e cascatas continuarão usando o fluxo de simulação quando aplicável.

A implementação deverá priorizar correção matemática antes de otimizações prematuras.

### 18B.16 Tarefa movida para outro projeto

Enquanto uma tarefa estiver no estado **movida / pendente de resolução**:

- sua representação histórica permanecerá visível;
- ela ficará suspensa do caminho crítico operacional;
- a rede deverá indicar que existe inconsistência pendente.

Se for restaurada, retornará ao cálculo após reconciliação.

Se sua remoção for confirmada, a rede será recalculada sem ela.

### 18B.17 Tarefa excluída

Tarefa efetivamente excluída no Todoist deverá ser removida do cálculo do caminho crítico e as cadeias afetadas deverão ser recalculadas.

### 18B.18 Regra geral

> O caminho crítico é o conjunto de tarefas e relações cuja folga total calculada em dias úteis é zero, considerando calendário, dependências, estado real conhecido das tarefas concluídas e datas virtuais aplicáveis.

> Caminho crítico e reagendamento deverão utilizar o mesmo motor de calendário e precedência, evitando qualquer divergência entre posicionamento temporal e criticidade.

## 18C. Validação e tratamento das dependências

### 18C.1 Validação na criação

Toda nova dependência deverá ser validada pelo core antes de ser persistida.

Deverá ser verificado:

- existência da predecessora e da sucessora;
- pertencimento de ambas ao mesmo Gantt;
- predecessor e sucessor diferentes entre si;
- tipo válido: FS, SS, FF ou SF;
- inexistência de ciclo;
- inexistência de duplicata idêntica.

A interface deverá antecipar essas validações quando possível, mas o core será a autoridade final.

### 18C.2 Autodependência

Dependências de uma tarefa para ela mesma são proibidas.

A interface deverá impedir a conclusão do gesto e o core deverá rejeitar qualquer tentativa equivalente.

### 18C.3 Dependências duplicadas

Uma relação idêntica entre o mesmo predecessor, sucessor e tipo não poderá ser cadastrada mais de uma vez.

### 18C.4 Múltiplos tipos entre o mesmo par

Poderão existir relações de tipos diferentes entre o mesmo par de tarefas, desde que não gerem ciclo ou impossibilidade lógica.

Todas as restrições válidas serão aplicadas simultaneamente.

### 18C.5 Ciclos

Qualquer nova relação que crie ciclo lógico deverá ser rejeitada, independentemente do tipo FS, SS, FF ou SF.

Nenhuma data deverá ser alterada como resultado de uma tentativa de criação inválida.

### 18C.6 Feedback durante o drag

Durante a criação visual da dependência:

- destinos válidos deverão receber destaque positivo;
- destinos inválidos deverão receber destaque de erro;
- autodependência deverá ser bloqueada;
- ciclo potencial deverá ser indicado antes da conclusão, quando tecnicamente viável;
- o tipo resultante da conexão deverá ser apresentado ao usuário.

### 18C.7 Tipo determinado pelas extremidades

As extremidades deverão representar:

- fim → início = FS;
- início → início = SS;
- fim → fim = FF;
- início → fim = SF.

### 18C.8 Persistência após criação

Uma dependência válida poderá ser salva diretamente, sem modal de confirmação adicional.

Se ela tornar o cronograma atual inconsistente:

- no modo manual, a inconsistência deverá ser sinalizada e incluída na simulação;
- no modo automático, o core poderá corrigir as datas conforme as regras do projeto.

### 18C.9 Alteração do tipo

O usuário poderá selecionar uma dependência existente e alterar seu tipo.

O core deverá revalidar o grafo e recalcular os impactos temporais e de criticidade.

### 18C.10 Exclusão

A remoção de uma dependência deverá:

- retirar a relação do fluxo ativo;
- recalcular caminho crítico;
- reavaliar inconsistências;
- não antecipar automaticamente tarefas já agendadas.

### 18C.11 Seleção visual

Linhas de dependência deverão possuir área de hit-test maior que sua espessura visual.

Ao selecionar uma relação, a interface deverá destacar:

- a linha;
- a predecessora;
- a sucessora;
- o tipo da relação.

### 18C.12 Representação gráfica

As dependências deverão utilizar linhas ortogonais com indicação de direção por seta no destino.

O roteamento deverá tentar reduzir sobreposições quando possível.

### 18C.13 Relação inconsistente com as datas atuais

Uma dependência poderá ser válida mesmo que as datas atuais ainda não respeitem a restrição.

Nessa condição:

- a relação permanece válida;
- a tarefa/relação deverá ser marcada como inconsistente;
- a correção será feita pelo mecanismo de reagendamento.

### 18C.14 Tarefas sem data

Dependências envolvendo tarefas sem data são permitidas.

O cálculo deverá utilizar a data virtual definida pelas regras temporais do projeto.

### 18C.15 Tarefas concluídas

Tarefas concluídas poderão permanecer como parte da cadeia de dependências e utilizarão `effective_completion_date` como referência operacional.

Tarefas concluídas nunca serão deslocadas.

Uma nova dependência cuja satisfação exigisse mover uma tarefa já concluída deverá ser rejeitada.

### 18C.16 Grupos como predecessores

Tarefas-pai/grupos poderão ser utilizadas como predecessoras de tarefas comuns.

As datas derivadas do grupo serão utilizadas como referência.

Exemplo FS:

`Grupo A → Tarefa B`

A sucessora somente poderá iniciar após o término derivado do grupo.

### 18C.17 Grupos como sucessores

No MVP, grupos **não poderão ser sucessores** de tarefas ou de outros grupos.

Portanto, serão inválidas relações como:

- `Tarefa A → Grupo B`;
- `Grupo A → Grupo B`.

Essa restrição evita a necessidade de distribuir uma condição de precedência sobre múltiplos descendentes do grupo.

Suporte a grupos como sucessores ou relações grupo→grupo poderá ser avaliado em versão futura.

### 18C.18 Tarefa excluída

Quando uma tarefa for efetivamente excluída no Todoist, suas dependências ativas deverão ser removidas do fluxo e o grafo deverá ser recalculado.

### 18C.19 Tarefa movida para outro projeto

Enquanto a tarefa estiver no estado **movida / pendente de resolução**, suas dependências deverão permanecer preservadas, porém suspensas para cálculo.

Se a tarefa for restaurada, poderão ser reativadas após nova validação.

Se sua remoção do Gantt for confirmada, as relações serão definitivamente removidas do fluxo ativo.

### 18C.20 Estados de dependência

A persistência deverá permitir estados equivalentes a:

- `ACTIVE`;
- `SUSPENDED`;
- `ORPHANED`;
- `REMOVED`.

### 18C.21 Reativação

Antes de reativar uma dependência suspensa, o core deverá verificar novamente:

- existência das tarefas;
- pertencimento ao mesmo projeto;
- inexistência de ciclo;
- validade do tipo;
- consistência com as demais relações.

### 18C.22 Mudança de hierarquia

Mudanças de seção, pai ou nível hierárquico não deverão apagar automaticamente dependências.

As relações afetadas deverão ser revalidadas e os grupos recalculados quando necessário.

### 18C.23 Dependências em grupos colapsados

Colapsar a hierarquia não deverá alterar a lógica das dependências.

Quando relações externas envolverem tarefas ocultas por colapso, a interface deverá procurar representar essa existência no resumo do grupo sem alterar a relação real.

### 18C.24 Controle de densidade visual

A Barra do Sistema deverá permitir modos simples de visualização das dependências, incluindo:

- todas;
- apenas as relacionadas à tarefa ativa;
- apenas as pertencentes ao caminho crítico.

### 18C.25 Dependências durante simulação

Durante uma simulação de reagendamento, as relações responsáveis pelos deslocamentos deverão receber destaque suficiente para evidenciar a cadeia causal.

### 18C.26 Falha de sincronização temporal

A dependência é um metadado próprio da aplicação.

Se a relação for salva, mas uma alteração de data associada falhar no Todoist:

- a dependência não deverá ser descartada;
- a inconsistência temporal permanecerá sinalizada;
- a sincronização das datas seguirá o mecanismo de retry e reconciliação.

### 18C.27 Regra geral

> Uma dependência válida é uma relação intraprojeto, não circular, entre duas tarefas existentes, com tipo FS, SS, FF ou SF. A relação pode existir mesmo que o cronograma atual ainda não a satisfaça; nesse caso, a inconsistência deverá ser explicitamente sinalizada e corrigida pelo mecanismo de reagendamento.

> No MVP, grupos poderão atuar apenas como predecessores de tarefas comuns. Grupos como sucessores e relações grupo→grupo ficam fora do escopo.

## 18D. Calendário de trabalho

### 18D.1 Escopo

Cada `gantt_project` deverá possuir um calendário de trabalho próprio.

Projetos diferentes do mesmo usuário poderão utilizar calendários diferentes.

### 18D.2 Semana padrão

O calendário deverá permitir definir individualmente cada dia da semana como útil ou não útil.

Configuração inicial padrão:

- segunda a sexta: úteis;
- sábado e domingo: não úteis.

O usuário poderá alterar essa configuração.

### 18D.3 Exceções por data

O calendário deverá aceitar exceções específicas:

- `NON_WORKING`: transforma a data em não útil;
- `WORKING`: transforma a data em útil.

As exceções permitirão representar feriados e dias extraordinários de trabalho.

### 18D.4 Precedência das regras

Uma exceção específica de data sempre prevalecerá sobre a configuração semanal.

Formalmente:

`exceção da data > regra do dia da semana`

### 18D.5 Feriados recorrentes

No MVP, feriados e exceções deverão ser cadastrados como datas concretas.

Regras recorrentes anuais e feriados móveis automáticos ficarão para versão futura.

### 18D.6 Importação de feriados

No MVP, o cadastro de feriados será manual.

Importação automática por país, estado, município ou serviço externo poderá ser avaliada futuramente.

### 18D.7 Descrição

Cada exceção poderá possuir descrição opcional para identificar sua razão.

### 18D.8 Visualização de dias não úteis

A Barra do Sistema deverá permitir:

- exibir dias não úteis no grid, com diferenciação visual;
- ocultar dias não úteis, comprimindo-os para fora da escala temporal.

A escolha de visualização não altera os cálculos.

### 18D.9 Barras atravessando dias não úteis

Quando dias não úteis estiverem visíveis, a barra poderá atravessá-los visualmente, embora eles não contem na duração.

Quando estiverem ocultos, a barra deverá permanecer visualmente contínua na escala comprimida.

### 18D.10 Alterações de calendário

Mudanças no calendário deverão ser processadas pelo mesmo mecanismo de planejamento:

No modo manual:

`alteração → simulação → confirmação → aplicação`

No modo automático:

`alteração → recálculo → aplicação`

### 18D.11 Preservação da duração

Quando uma alteração do calendário afetar o interior do intervalo de uma tarefa, sua duração em dias úteis deverá ser preservada e sua data final recalculada.

### 18D.12 Tarefas futuras independentes

Uma tarefa futura sem predecessoras deverá manter sua data inicial se essa data continuar válida após a alteração do calendário.

A criação ou remoção de dias úteis anteriores à tarefa não deverá antecipá-la ou deslocá-la sem necessidade.

### 18D.13 Cascata

Alterações de calendário deverão propagar seus efeitos na seguinte ordem lógica:

`calendário → tarefas diretamente afetadas → sucessoras → grupos → caminho crítico`

### 18D.14 Data inicial tornada inválida

Se uma data inicial passar a ser não útil, o início deverá avançar para o próximo dia útil válido, preservando a duração da tarefa.

### 18D.15 Deadline em dia não útil

A política padrão será mover um deadline inválido para o dia útil imediatamente anterior.

Essa política será configurável.

### 18D.16 Política de deadline

O MVP deverá oferecer pelo menos:

- `ANTERIOR` — padrão: `PrevWorkDay(deadline)`;
- `POSTERIOR`: `NextWorkDay(deadline)`.

### 18D.17 Operações manuais

Drag, resize e edição direta de datas não poderão produzir uma posição incompatível com o calendário.

A interface deverá aplicar snap para uma data útil válida e o core deverá validar novamente antes da persistência.

### 18D.18 Referência de hoje

Para todas as regras que utilizem **hoje** como referência, deverá ser considerada a data atual no timezone aplicável ao usuário.

Se a data atual for um dia não útil, a referência operacional de **hoje** será o próximo dia útil do calendário.

Formalmente:

`OperationalToday = IsWorkDay(LocalToday) ? LocalToday : NextWorkDay(LocalToday)`

Portanto, uma tarefa sem data e sem predecessoras terá:

`virtual_start = OperationalToday`

### 18D.19 Predecessoras sem data

Se uma tarefa sem data utilizar `OperationalToday` como data virtual, suas sucessoras deverão respeitar normalmente as regras de precedência.

Exemplo FS: se hoje for domingo, segunda-feira for útil e a predecessora virtual tiver duração de um dia, a sucessora poderá iniciar a partir de terça-feira.

### 18D.20 Mudança da data durante sessão aberta

Se uma sessão permanecer aberta durante a mudança de dia no timezone do usuário, o sistema deverá atualizar a referência de `OperationalToday` e recalcular os elementos que dependam dela, sem exigir reload manual.

### 18D.21 Timezone

O calendário deverá interpretar a data atual conforme o timezone aplicável ao usuário/projeto, nunca pelo timezone do servidor.

O domínio temporal continuará operando com datas `YYYY-MM-DD`, sem horários.

### 18D.22 Validação mínima

O calendário deverá possuir pelo menos um dia útil recorrente na semana.

O MVP não permitirá uma semana com todos os sete dias marcados como não úteis.

### 18D.23 Proteção do algoritmo

Funções como `NextWorkDay` e `PrevWorkDay` deverão possuir proteção contra busca indefinida.

Se não for possível localizar um dia útil dentro de um limite técnico seguro, o core deverá gerar erro de calendário em vez de entrar em loop.

### 18D.24 Edição em lote

A tela de calendário deverá permitir preparar várias alterações antes de aplicá-las.

Ao clicar em **Aplicar alterações**, o conjunto deverá gerar uma única avaliação/simulação consolidada, evitando múltiplos reagendamentos sucessivos.

### 18D.25 Cancelamento

Alterações de calendário permanecerão em rascunho até a ação **Aplicar**.

A ação **Cancelar** deverá descartar o rascunho sem alterar calendário ou tarefas.

### 18D.26 Histórico

Reagendamentos originados por alteração de calendário deverão registrar essa origem no histórico operacional.

Não será necessário versionamento completo de calendários no MVP.

### 18D.27 Interface de configuração

A configuração do projeto deverá permitir, no mínimo:

- selecionar dias úteis da semana;
- listar exceções;
- adicionar/remover exceções;
- definir exceção como útil ou não útil;
- informar descrição opcional;
- selecionar política de deadline;
- aplicar alterações;
- cancelar alterações.

### 18D.28 Regra geral

> Cada Gantt possui um calendário próprio formado por uma semana padrão e exceções específicas por data. Exceções prevalecem sobre a semana. Todo cálculo temporal utiliza exclusivamente os dias úteis desse calendário. Quando a data civil atual não for útil, a referência operacional de “hoje” será o próximo dia útil. Alterações que afetem o planejamento passam pelo mesmo motor de simulação, cascata e reagendamento do projeto.

## 18E. Estados operacionais das tarefas

### 18E.1 Princípio geral

O estado operacional das tarefas deverá ser calculado dinamicamente pelo core e não persistido como cópia de status.

Estados principais do MVP:

- `NAO_PROGRAMADA`;
- `NAO_INICIADA`;
- `EM_EXECUCAO`;
- `ATRASADA`;
- `CONCLUIDA`.

Estado operacional, qualificadores e estado de sincronização são conceitos independentes.

### 18E.2 Ordem de avaliação

A avaliação deverá seguir, conceitualmente, esta precedência:

1. pendência de integridade, como tarefa movida para outro projeto;
2. conclusão atual;
3. ausência de data inicial persistida;
4. início futuro;
5. prazo ultrapassado;
6. caso restante: em execução.

O core será a autoridade sobre essa classificação.

### 18E.3 Concluída

Se a tarefa estiver atualmente concluída no Todoist, seu estado principal será `CONCLUIDA`.

A data histórica relevante será:

`effective_completion_date = completion_date_override ?? Todoist.completed_at`

### 18E.4 Não programada

Tarefa aberta sem data inicial persistida será `NAO_PROGRAMADA`, mesmo que possua data virtual para fins de cálculo.

A data virtual não transforma, por si só, uma tarefa em programada.

### 18E.5 Não iniciada

Tarefa aberta e programada será `NAO_INICIADA` quando:

`S(T) > OperationalToday`

### 18E.6 Em execução

Tarefa aberta e programada será `EM_EXECUCAO` quando:

`S(T) <= OperationalToday <= F(T)`

Esse estado significa que a tarefa se encontra no período planejado de execução; não representa apontamento real de horas ou confirmação humana de início.

### 18E.7 Atrasada

Tarefa aberta e programada será `ATRASADA` quando:

`OperationalToday > F(T)`

Uma tarefa de um único dia permanece `EM_EXECUCAO` durante seu dia planejado e passa a `ATRASADA` no próximo dia operacional caso continue aberta.

### 18E.8 Dias não úteis

Como `OperationalToday` avança para o próximo dia útil quando a data civil atual não é útil, uma tarefa cujo prazo terminou no último dia útil anterior já poderá aparecer como atrasada durante um fim de semana ou feriado.

### 18E.9 Tarefa sem data e dependências

Tarefas sem data continuam visualmente `NAO_PROGRAMADA`, mas para o motor de precedência utilizam:

`virtual_start = OperationalToday`

ou uma data posterior imposta pelas predecessoras.

### 18E.10 Configuração para não permitir tarefas sem data

Quando a configuração de preenchimento automático estiver ativa, a data virtual calculada poderá ser persistida.

Depois da persistência, a tarefa deixa de ser `NAO_PROGRAMADA` e passa ao estado correspondente à sua data.

### 18E.11 Qualificadores de conclusão

Uma tarefa concluída poderá receber qualificadores históricos:

- `ANTECIPADA`;
- `NO_PRAZO`;
- `COM_ATRASO`.

Regras:

- `effective_completion_date < S(T)` → `ANTECIPADA`;
- `S(T) <= effective_completion_date <= F(T)` → `NO_PRAZO`;
- `effective_completion_date > F(T)` → `COM_ATRASO`.

Esses qualificadores não criam novos estados principais.

### 18E.12 Override da data de conclusão

Quando existir `completion_date_override`, ele prevalecerá sobre a data informada pelo Todoist em todos os cálculos históricos aplicáveis.

### 18E.13 Reabertura

Quando uma tarefa concluída for reaberta, seu estado atual voltará a ser calculado pelas regras normais.

O override de conclusão eventualmente existente ficará inativo enquanto a tarefa estiver aberta, podendo ser preservado apenas como informação histórica.

### 18E.14 Grupos

Grupos não são tarefas executáveis e terão estado derivado dos descendentes.

Estados visuais derivados:

- todos os descendentes concluídos → `CONCLUIDO`;
- algum descendente atrasado → `COM_ATRASO`;
- algum descendente em execução → `EM_ANDAMENTO`;
- todos os descendentes relevantes futuros → `NAO_INICIADO`;
- nenhum descendente planejado → `NAO_PROGRAMADO`.

Não haverá percentual de progresso no MVP.

### 18E.15 Grupo parcialmente concluído

Um grupo com parte das tarefas concluídas e trabalho restante será apresentado como `EM_ANDAMENTO`, salvo quando existir descendente atrasado, caso em que `COM_ATRASO` terá precedência visual.

### 18E.16 Descendentes não programados

Grupo contendo tarefas não programadas deverá receber qualificador visual equivalente a `CONTEM_TAREFA_NAO_PROGRAMADA`.

Suas datas derivadas continuarão considerando apenas os descendentes com datas aplicáveis conforme as regras já definidas.

### 18E.17 Estados em simulação

Uma simulação poderá calcular os estados resultantes do cenário proposto.

A interface poderá indicar transições relevantes, por exemplo:

`ATRASADA → EM_EXECUCAO`

sem substituir o estado real antes da confirmação.

### 18E.18 Sincronização e conflito

Estados técnicos de sincronização são independentes do estado operacional.

Exemplo:

`ATRASADA · PENDENTE_DE_SINCRONIZACAO`

ou:

`EM_EXECUCAO · CONFLITO_DE_SINCRONIZACAO`

### 18E.19 Tarefa movida

Tarefa detectada como movida para outro projeto e ainda pendente de resolução receberá indicação prioritária `MOVIDA_DO_PROJETO`.

Enquanto essa pendência existir, sua condição de integridade deverá prevalecer visualmente sobre o estado operacional comum.

### 18E.20 Criticidade

Criticidade será um qualificador independente.

Uma tarefa poderá ser, por exemplo:

`ATRASADA · CRITICA`

sem necessidade de criar um estado operacional específico.

### 18E.21 Semântica visual

O MVP deverá possuir semântica visual consistente para:

- não programada;
- não iniciada;
- em execução;
- atrasada;
- concluída;
- criticidade;
- conflitos e pendências de integridade.

A informação não deverá depender exclusivamente de cor; texto, ícone, padrão ou outro indicador deverá garantir compreensão e acessibilidade.

### 18E.22 Core como autoridade

O front-end não deverá implementar uma segunda regra independente para determinar estados.

O core deverá fornecer o estado e os qualificadores calculados e a interface deverá representá-los.

### 18E.23 Regra geral

> O estado operacional é derivado dinamicamente de conclusão, planejamento, `OperationalToday` e intervalo temporal. Estados não serão persistidos como cópia dos dados do Todoist. Criticidade, atraso histórico, ausência de planejamento, conflito, integridade e sincronização serão tratados como qualificadores ou estados técnicos independentes.

## 18F. Sincronização, consistência e operações de tarefa

### 18F.1 Fontes da verdade

A autoridade será definida por domínio:

- campos nativos de tarefas e projetos: Todoist;
- dependências, calendário e configurações do Gantt: aplicação;
- `completion_date_override`: aplicação;
- estados operacionais, grupos derivados e caminho crítico: core.

### 18F.2 Fluxo Todoist → aplicação

Alterações externas deverão seguir conceitualmente:

`Todoist → webhook → core → sincronização/reconciliação → recálculo → interface`

O webhook será um sinal de mudança e não a única fonte do estado.

### 18F.3 Reconciliação

A aplicação deverá utilizar sincronização incremental sempre que possível.

Full sync deverá ser reservado para primeira conexão, perda do estado incremental, recuperação de inconsistência grave ou reconstrução administrativa.

Ao abrir um Gantt, o sistema poderá carregar imediatamente o último estado disponível, iniciar reconciliação incremental e atualizar a interface quando houver diferenças.

### 18F.4 Atualização em tempo real

Clientes com o Gantt aberto deverão receber atualizações do backend sem reload manual.

Para o MVP, SSE (Server-Sent Events) é a estratégia recomendada para servidor → navegador.

Deverá existir reconciliação periódica leve como fallback e ação manual **Sincronizar agora** na Barra do Sistema.

### 18F.5 Fluxo aplicação → Todoist

Alterações de campos nativos deverão seguir:

`interface → core → validação → operação/fila → Todoist → confirmação → reconciliação → interface`

O navegador não deverá chamar diretamente a API Todoist.

### 18F.6 Atualização otimista

A interface poderá refletir imediatamente uma alteração válida, identificando-a como `SINCRONIZANDO`.

Após confirmação, o indicador desaparece.

Em falha permanente, a interface deverá reconciliar com o estado real e informar o erro.

### 18F.7 Eco, deduplicação e ordem

Alterações originadas pela própria aplicação e posteriormente observadas por webhook/sync não deverão ser interpretadas como nova intenção do usuário.

Eventos e comandos deverão possuir mecanismos de deduplicação.

Eventos recebidos fora de ordem não deverão sobrescrever cegamente um estado mais recente; o core deverá reconciliar contra o estado corrente.

### 18F.8 Múltiplas abas e dispositivos

Mudanças confirmadas deverão ser propagadas aos demais clientes conectados do mesmo usuário/projeto.

### 18F.9 Concorrência

Antes de escritas relevantes, o core deverá revalidar os campos utilizados como premissa da operação.

Conflitos deverão ser avaliados por campo relevante, evitando `last write wins` cego.

Alterações não conflitantes em campos diferentes poderão coexistir.

### 18F.10 Mudanças estruturais

Criação, exclusão, mudança de projeto, mudança de seção, mudança de pai, conclusão e reabertura deverão acionar as regras específicas de hierarquia, dependência, calendário e recálculo.

### 18F.11 Criação de tarefas pelo Gantt

A criação de tarefas fará parte do MVP.

A interface deverá permitir criar uma tarefa diretamente no Gantt, informando os campos suportados pela aplicação, como:

- título;
- projeto Gantt/Todoist correspondente;
- seção e posição hierárquica;
- prioridade;
- data inicial;
- deadline quando aplicável.

O core deverá criar a tarefa no Todoist e obter seu ID externo antes de considerá-la definitivamente incorporada ao Gantt.

Falhas de criação não deverão deixar metadados ou dependências órfãs.

### 18F.12 Edição de tarefas

A aplicação poderá editar diretamente campos nativos suportados, incluindo título, prioridade, hierarquia, seção e datas, persistindo as mudanças no Todoist.

Datas e deadlines serão enviados sem horário.

### 18F.13 Conclusão e reabertura

O MVP permitirá concluir e reabrir tarefas pelo Gantt.

Essas ações deverão ser persistidas no Todoist e reconciliadas pelo mesmo fluxo de sincronização.

### 18F.14 Exclusão de tarefas

A exclusão de tarefas pelo Gantt fará parte do MVP.

Antes da exclusão, o core deverá verificar pelo menos:

- descendentes hierárquicos;
- dependências de entrada;
- dependências de saída;
- participação em cadeias;
- impactos em grupos e caminho crítico.

Quando houver impacto estrutural, a interface deverá apresentar confirmação informativa antes da exclusão.

### 18F.15 Continuidade da rota ao excluir

Quando uma tarefa a ser excluída estiver no meio de uma cadeia de dependências, a confirmação deverá oferecer a opção **Manter continuidade da rota**.

Se selecionada, após remover a tarefa o core deverá propor/criar dependências diretas entre cada predecessora relevante da tarefa removida e cada sucessora relevante.

Exemplo:

`A → B → C`

Excluindo B com continuidade:

`A → C`

Com múltiplas entradas e saídas, o core poderá produzir o produto das relações necessárias entre predecessoras e sucessoras, sempre validando:

- ciclos;
- duplicidades;
- pertencimento ao mesmo projeto;
- tipos de relação;
- demais regras do grafo.

### 18F.16 Tipos das dependências reconstruídas

Quando a composição das relações removidas produzir um tipo de dependência inequívoco e compatível, o sistema poderá sugeri-lo automaticamente.

Quando a combinação de FS, SS, FF ou SF não permitir uma composição segura e inequívoca, a interface deverá apresentar as novas relações propostas para decisão do usuário antes da aplicação.

O core não deverá inventar silenciosamente uma semântica de precedência ambígua.

### 18F.17 Inserir tarefa em uma rota

O MVP deverá permitir inserir uma nova tarefa diretamente em uma dependência existente.

Ao clicar com o botão direito sobre uma linha de dependência, o menu de contexto deverá oferecer **Inserir tarefa na rota**.

Para uma relação:

`A → B`

o fluxo será:

1. abrir o cadastro da nova tarefa;
2. criar a tarefa no Todoist;
3. validar a operação;
4. remover a dependência `A → B`;
5. criar `A → Nova`;
6. criar `Nova → B`;
7. recalcular datas, grupos e caminho crítico.

Por padrão, quando semanticamente válido, o tipo da dependência original poderá ser utilizado nos dois novos trechos.

### 18F.18 Atomicidade lógica da inserção em rota

A inserção deverá ser tratada como uma única operação lógica do core.

Se a criação da nova tarefa no Todoist falhar, a dependência original não deverá ser removida.

Se ocorrer falha depois da criação da tarefa, o core deverá manter estado intermediário rastreável e executar recuperação/reconciliação, sem ocultar uma cadeia parcialmente modificada.

### 18F.19 Indicadores de sincronização

A interface deverá suportar indicadores discretos de:

- sincronizado;
- sincronizando;
- pendente;
- erro;
- conflito.

A Barra do Sistema deverá possuir também indicador global da integração Todoist.

### 18F.20 OAuth revogado ou inválido

Quando a autorização deixar de ser válida:

- interromper tentativas repetitivas inadequadas;
- marcar a integração como desconectada;
- solicitar reconexão OAuth;
- impedir novas escritas;
- permitir consulta do último estado conhecido com indicação clara de não sincronizado.

Após reconexão, deverá ocorrer reconciliação segura antes de restabelecer operação normal.

### 18F.21 Indisponibilidade temporária

Falhas temporárias do Todoist não deverão bloquear desnecessariamente todo o projeto.

Operações pendentes poderão permanecer na fila e seguir a política de retry.

### 18F.22 Offline

Edição offline real/offline-first fica fora do MVP.

Sem garantia de comunicação com o backend/serviço necessário, escritas deverão ser bloqueadas ou explicitamente recusadas, sem simular persistência definitiva.

### 18F.23 Diagnóstico

Operações relevantes deverão permitir rastrear usuário, integração, projeto, tarefa, origem, operação, tentativa, resultado, erro e timestamps, sem registrar tokens ou segredos.

### 18F.24 Regra contra loops

> Uma alteração originada pela própria aplicação e posteriormente observada por webhook ou sincronização não deverá ser interpretada como uma nova intenção de alteração ou disparar indefinidamente novos reagendamentos.

### 18F.25 Regra geral

> Todoist é a fonte da verdade dos campos nativos; a aplicação é a fonte de seus metadados. Webhooks fornecem baixa latência, sincronização incremental fornece reconciliação e o core é o único intermediário autorizado entre interface e Todoist. Criação, edição, conclusão, reabertura e exclusão de tarefas fazem parte do MVP.

## 18G. Segurança, autenticação e isolamento entre usuários

### 18G.1 Isolamento por usuário

Todo acesso a Gantts, configurações, dependências, sessões e integrações deverá ser autorizado pelo backend a partir do usuário autenticado.

O backend nunca deverá confiar em `user_id` informado pelo navegador para conceder acesso.

Conhecer o identificador de um recurso de outro usuário não poderá conceder acesso a ele.

### 18G.2 Autenticação passwordless

O MVP utilizará autenticação sem senha:

`e-mail → código/link → validação → sessão`

Código e magic link deverão ser de uso único, possuir expiração curta e ser invalidados após autenticação bem-sucedida.

### 18G.3 Código e magic link

Um mesmo desafio lógico de autenticação poderá ser concluído pelo link recebido ou pelo código digitado.

A utilização bem-sucedida de uma opção invalida a outra.

### 18G.4 Enumeração de contas

As respostas de solicitação de autenticação não deverão revelar desnecessariamente se determinado e-mail já possui conta.

### 18G.5 Primeiro acesso

No primeiro login passwordless válido, a conta poderá ser criada automaticamente, dispensando fluxo separado de cadastro no MVP.

### 18G.6 Múltiplas sessões

Um usuário poderá possuir múltiplas sessões simultâneas, permitindo desktop, celular e tablet autenticados ao mesmo tempo.

### 18G.7 Cookie de sessão

O cookie de sessão deverá utilizar, em produção:

- `HttpOnly`;
- `Secure`;
- política `SameSite` apropriada;
- expiração controlada.

O token de sessão não deverá precisar ser acessível pelo JavaScript da aplicação.

### 18G.8 Duração da sessão

A política padrão do MVP será aproximadamente 30 dias de inatividade.

A atividade válida poderá renovar a expiração conforme a política de sessão implementada.

### 18G.9 Logout

Logout comum revogará apenas a sessão atual.

Deverá existir ação **Sair de todos os dispositivos**, revogando todas as sessões do usuário.

Uma interface avançada de gestão individual de dispositivos fica para versão futura.

### 18G.10 OAuth Todoist

Tokens OAuth Todoist deverão permanecer exclusivamente no backend.

O navegador nunca deverá receber credenciais que permitam chamar diretamente a API Todoist em nome do usuário.

### 18G.11 Proteção de credenciais

Tokens Todoist deverão ser criptografados em repouso.

A chave de proteção deverá permanecer fora do banco de dados, em configuração segura do ambiente.

Client secrets, chaves criptográficas, credenciais de e-mail e demais segredos não deverão ser armazenados no código-fonte, front-end ou logs.

### 18G.12 CSRF

Operações autenticadas por cookie e especialmente operações de escrita deverão possuir proteção adequada contra CSRF, utilizando `SameSite`, token CSRF ou mecanismo equivalente.

### 18G.13 XSS

Conteúdo originado do Todoist ou informado pelo usuário não deverá ser tratado como HTML confiável.

A interface deverá realizar escape/sanitização apropriados.

### 18G.14 Banco de dados

A camada de persistência deverá utilizar prepared statements, ORM seguro ou mecanismo equivalente, sem concatenação insegura de dados recebidos em comandos SQL.

### 18G.15 Validação no servidor

Todas as regras de autorização e negócio deverão ser validadas pelo backend mesmo quando a interface já impedir a operação.

Validação do front-end é mecanismo de experiência do usuário, não fronteira de segurança.

### 18G.16 Rate limiting

Deverá existir limitação apropriada pelo menos em:

- solicitação de autenticação;
- validação de código;
- operações sensíveis de autenticação;
- endpoints suscetíveis a abuso.

### 18G.17 Webhooks

Endpoints de webhook deverão validar a autenticidade das requisições segundo o mecanismo oficial suportado pelo Todoist.

Eventos inválidos deverão ser rejeitados.

Deduplicação e reconciliação continuam obrigatórias.

### 18G.18 Logs

Não deverão ser registrados em logs:

- tokens OAuth;
- cookies;
- códigos de login;
- magic links completos;
- client secrets;
- chaves criptográficas.

Identificadores técnicos de projeto/tarefa poderão ser registrados quando necessários ao diagnóstico.

### 18G.19 Exclusão da conta

O MVP deverá permitir **Excluir minha conta**.

A operação deverá:

- exigir confirmação adequada;
- revogar sessões;
- remover credenciais da integração Todoist;
- remover ou arquivar Gantts e metadados próprios conforme política de retenção;
- nunca excluir automaticamente projetos ou tarefas existentes no Todoist.

### 18G.20 Desconectar Todoist

O usuário poderá desconectar sua conta Todoist sem excluir sua conta na aplicação.

A operação deverá revogar/remover a integração e impedir novas escritas.

Os Gantts associados deverão permanecer indisponíveis para edição ou em modo de consulta claramente identificado até reconexão compatível.

### 18G.21 Troca da conta Todoist

No MVP, cada usuário terá somente uma conta Todoist conectada.

Para conectar outra conta, a integração atual deverá ser desconectada primeiro.

A interface deverá avisar que os Gantts existentes estão vinculados aos IDs da conta anterior.

Não haverá migração automática de metadados entre contas Todoist.

### 18G.22 Permissões Todoist

A aplicação não deverá presumir que a autorização OAuth concede capacidade irrestrita sobre todos os recursos acessíveis.

Quando o Todoist rejeitar uma operação por permissão:

- não tentar contornar a restrição;
- informar o erro adequadamente;
- reconciliar o estado.

### 18G.23 Papéis internos

O MVP não terá sistema próprio de papéis `admin/editor/viewer` para colaboração entre usuários.

Cada Gantt pertence ao usuário autenticado.

Papéis e colaboração própria poderão ser avaliados futuramente.

### 18G.24 Compartilhamento

Compartilhamento de Gantt entre contas da aplicação, links públicos ou mecanismos equivalentes ficam fora do MVP.

### 18G.25 HTTPS

HTTPS será obrigatório em produção.

### 18G.26 Cabeçalhos de segurança

A implantação deverá configurar políticas adequadas, incluindo quando aplicável:

- Content Security Policy;
- proteção contra framing;
- proteção de content type;
- referrer policy;
- HSTS após estabilização do ambiente.

### 18G.27 Backup e restauração

O banco próprio contém dados que não podem ser reconstruídos somente a partir do Todoist, especialmente dependências, calendários, configurações e histórico operacional.

A execução, retenção e restauração de backups **não serão responsabilidade da aplicação**.

Essas atividades serão responsabilidade da infraestrutura, que utilizará seus mecanismos próprios de snapshots/backups atômicos de bancos, volumes ou partições.

A aplicação deverá apenas:

- identificar claramente seus componentes persistentes;
- manter consistência de escrita compatível com os mecanismos de infraestrutura;
- documentar quais dados/volumes precisam ser protegidos e restaurados em conjunto.

### 18G.29 Regra geral

> Toda autorização é determinada pelo backend a partir da sessão autenticada. Tokens e segredos nunca são expostos ao front-end. O sistema deverá proteger tanto os dados próprios quanto a capacidade concedida pelo OAuth de modificar dados no Todoist.

## 18H. Responsividade e experiência por dispositivo

### 18H.1 Aplicação única

O MVP será uma única aplicação web responsiva, sem aplicativo móvel separado.

A interface deverá adaptar composição, densidade e controles conforme viewport e modalidade de entrada.

### 18H.2 Desktop

Em desktop, a experiência completa utilizará:

`hierarquia/tabela à esquerda | timeline Gantt à direita`

com Barra do Sistema superior e Barra de Seleção inferior quando houver itens selecionados.

### 18H.3 Widescreen e 4K

A aplicação deverá aproveitar praticamente toda a largura útil disponível.

Não deverá impor um `max-width` típico de sites institucionais que desperdice telas largas.

Em telas grandes:

- mais colunas poderão permanecer visíveis;
- maior intervalo temporal poderá ser exibido;
- painel lateral poderá coexistir com o Gantt sem reduzir excessivamente a área principal.

### 18H.4 Divisor redimensionável

A divisão entre tabela e timeline deverá ser redimensionável horizontalmente.

Deverão existir larguras mínimas funcionais para impedir que qualquer uma das áreas se torne inutilizável.

A preferência poderá ser mantida localmente por dispositivo.

### 18H.5 Sincronização de linhas

Tabela e timeline deverão compartilhar a mesma rolagem vertical e permanecer perfeitamente alinhadas.

Cabeçalhos poderão permanecer fixos quando apropriado.

### 18H.6 Rolagem horizontal

A timeline terá rolagem horizontal independente.

A área hierárquica deverá permanecer estável enquanto o usuário navega no tempo.

### 18H.7 Barra do Sistema

No desktop, comandos principais deverão aparecer diretamente.

Em larguras menores, comandos secundários deverão migrar para menu de overflow, preservando legibilidade e área de toque.

### 18H.8 Tablet

Em tablets com largura suficiente, tabela e timeline poderão permanecer lado a lado.

Em telas mais estreitas, a interface deverá reduzir colunas visíveis e priorizar:

- seleção;
- título/hierarquia;
- estado;
- datas essenciais;
- timeline.

Campos secundários deverão permanecer acessíveis pelo painel de edição.

### 18H.9 Celular

No celular, a interface deverá ser recomposta e não apenas reduzida.

A experiência deverá priorizar:

- cabeçalho compacto;
- lista/hierarquia;
- timeline rolável horizontalmente;
- controles essenciais;
- painel de edição adaptado.

### 18H.10 Colunas no celular

Não será necessário exibir simultaneamente todas as colunas da versão desktop.

Deverão ser priorizados:

- seleção;
- hierarquia/nome;
- indicador de estado.

Datas e demais propriedades poderão ser acessadas ao abrir a tarefa.

### 18H.11 Painel de edição no celular

O painel lateral do desktop deverá adaptar-se para bottom sheet ou painel que ocupe grande parte da tela em dispositivos estreitos.

### 18H.12 Barra de Seleção

No desktop, deverá permanecer como barra inferior.

No celular, deverá utilizar versão compacta e fixa, com overflow para ações secundárias.

### 18H.13 Áreas de toque

Botões, checkboxes, handles de resize e conectores deverão possuir áreas interativas adequadas para toque.

A área de hit-test poderá ser maior que o elemento visual.

### 18H.14 Drag em touch

Mover e redimensionar tarefas deverá ser possível por toque sem conflitar com a rolagem.

A implementação poderá utilizar press-and-hold, handles específicos ou comportamento equivalente.

### 18H.15 Dependências no celular

Além da criação por conectores visuais, o painel da tarefa deverá oferecer uma alternativa explícita como **Adicionar dependência**, permitindo selecionar a outra tarefa.

Isso garante que dependências não sejam recurso exclusivo de mouse.

### 18H.16 Inserir tarefa na rota no touch

A ação **Inserir tarefa na rota** deverá estar disponível ao selecionar uma dependência em dispositivos sem botão direito.

No desktop, poderá existir também em menu de contexto.

### 18H.17 Regra de equivalência de interação

Nenhuma função essencial poderá depender exclusivamente de hover, mouse ou botão direito.

Toda operação crítica deverá possuir alternativa acessível por touch e teclado quando aplicável.

### 18H.18 Escala temporal

O MVP deverá oferecer níveis discretos de visualização:

- dia;
- semana;
- mês.

A unidade lógica de planejamento permanece sendo um dia.

### 18H.19 Pinch-to-zoom

Pinch-to-zoom não será requisito do MVP.

Controles explícitos de escala serão suficientes.

### 18H.20 Indicador de hoje

A timeline deverá destacar `OperationalToday`.

A ação **Ir para hoje** deverá estar disponível, especialmente útil em dispositivos móveis.

### 18H.21 Posição inicial da timeline

Ao abrir um Gantt, a timeline deverá posicionar-se em uma região útil, preferencialmente próxima de `OperationalToday`, salvo quando existir posição visual recente preservada no dispositivo.

### 18H.22 Preferências visuais locais

Preferências estritamente visuais poderão ser armazenadas no dispositivo, incluindo:

- largura da tabela;
- escala/zoom;
- última posição horizontal;
- grupos expandidos ou recolhidos.

Essas preferências não precisam ser persistidas no banco no MVP.

### 18H.23 Configurações de negócio

Calendário, modo de reagendamento, política de deadline e demais regras do projeto deverão permanecer no servidor.

Preferências visuais locais não poderão substituir regras de negócio persistidas.

### 18H.24 Orientação

A aplicação deverá funcionar em retrato e paisagem.

Paisagem poderá aproveitar melhor a timeline, mas não será obrigatória.

### 18H.25 Virtualização vertical

Projetos com muitas tarefas deverão utilizar virtualização de linhas quando necessário.

Tabela e timeline deverão utilizar a mesma janela virtual para preservar alinhamento.

### 18H.26 Virtualização horizontal

A timeline não deverá renderizar intervalos temporais enormes de forma indiscriminada.

Deverá utilizar janela temporal ou técnica equivalente para manter desempenho.

### 18H.27 Resize da viewport

Mudanças de tamanho da janela, orientação ou divisão de tela deverão recompor a interface sem reload e sem perder seleção ou contexto de trabalho.

### 18H.28 Acessibilidade

A interface deverá possuir pelo menos:

- contraste adequado;
- foco de teclado visível;
- labels acessíveis;
- estados identificáveis além de cor;
- operações principais acessíveis sem dependência exclusiva de mouse.

### 18H.29 Densidade

A interface desktop deverá utilizar densidade compacta apropriada para ferramenta de planejamento.

Em dispositivos touch, áreas interativas e espaçamentos deverão aumentar conforme necessário.

### 18H.30 Regra geral

> A aplicação será uma única interface web responsiva, funcional desde celulares até monitores widescreen 4K. Responsividade significa recompor e priorizar a interface, e não apenas reduzir proporcionalmente o layout desktop. Nenhuma funcionalidade essencial poderá depender exclusivamente de mouse, hover ou botão direito.

## 18I. Feedback visual, carregamento, erros e confirmações

### 18I.1 Princípio geral

O feedback da interface deverá seguir três níveis principais:

- feedback no próprio elemento para estados locais;
- toast para eventos pontuais relevantes;
- modal somente quando uma decisão explícita do usuário for necessária.

Modais meramente informativos deverão ser evitados.

### 18I.2 Salvamento automático

Alterações rotineiras válidas deverão ser salvas automaticamente, sem botão geral **Salvar**.

Durante a persistência/sincronização, a interface deverá indicar discretamente `SALVANDO` ou `SINCRONIZANDO`.

### 18I.3 Sucesso

Operações rotineiras bem-sucedidas não deverão produzir toasts repetitivos.

Toasts de sucesso serão reservados para ações significativas, como criação de tarefa ou aplicação de reagendamento.

### 18I.4 Estados de sincronização

Uma tarefa poderá apresentar indicadores técnicos como:

- `SINCRONIZANDO`;
- `PENDENTE_DE_SINCRONIZACAO`;
- `ERRO`;
- `CONFLITO`.

Esses indicadores são independentes do estado operacional.

### 18I.5 Erros temporários

Falhas recuperáveis deverão seguir a política automática de retry.

A interface não deverá emitir uma mensagem a cada tentativa, podendo apresentar indicação consolidada como **Problema ao sincronizar — tentando novamente**.

### 18I.6 Erros definitivos

Falhas permanentes deverão:

- identificar o elemento/operação afetado;
- apresentar mensagem compreensível;
- oferecer ação apropriada, como **Tentar novamente** ou **Descartar alteração**, quando aplicável.

Mensagens técnicas detalhadas permanecerão nos logs.

### 18I.7 Conflitos

Conflitos deverão ser tratados separadamente de erros comuns.

Quando necessária decisão humana, a interface deverá apresentar o estado corrente do Todoist e a alteração pretendida, oferecendo opções compatíveis com as regras de negócio.

Conflitos deverão ser avaliados por campo relevante.

### 18I.8 Validação antes da sincronização

Operações inválidas por regra de negócio deverão ser rejeitadas antes de qualquer chamada externa.

Exemplos:

- ciclo de dependência;
- autodependência;
- posição temporal inválida;
- relação proibida envolvendo grupo.

### 18I.9 Drag e resize inválidos

Durante drag/resize, posições inválidas deverão receber indicação visual.

Se o usuário soltar em posição inválida, o elemento deverá retornar à posição anterior e a interface poderá apresentar explicação curta.

### 18I.10 Dependência inválida

Destinos inválidos deverão ser indicados durante a criação da relação.

Quando necessário, a interface deverá explicar por que a dependência não foi criada.

### 18I.11 Confirmações destrutivas

Ações de impacto difícil de desfazer deverão exigir confirmação, especialmente:

- exclusão de tarefa;
- exclusão de conta;
- desconexão do Todoist;
- alterações estruturais relevantes;
- aplicação de operações de grande impacto quando aplicável.

Operações triviais não deverão exigir confirmação.

### 18I.12 Exclusão simples

Tarefa sem dependências, descendentes ou impacto estrutural deverá receber confirmação simples antes da exclusão.

### 18I.13 Exclusão com impacto

Quando a tarefa possuir relações ou descendentes, a confirmação deverá apresentar o impacto e, quando aplicável, a opção **Manter continuidade das rotas**.

A interface poderá informar quantas novas relações serão propostas.

### 18I.14 Preview ghost da reconstrução de rota

O MVP deverá oferecer preview visual antes de confirmar exclusão com continuidade de rota:

- tarefa removida em aparência de exclusão;
- relações antigas atenuadas;
- novas relações propostas em modo ghost.

Essa visualização não deverá persistir alterações antes da confirmação.

### 18I.15 Simulação de reagendamento

O modo manual seguirá:

`Simular → visualizar → Aplicar ou Cancelar`

Durante a simulação, uma faixa ou indicação persistente deverá informar claramente que o usuário está visualizando um cenário não persistido.

### 18I.16 Resumo da simulação

Antes da aplicação, a interface deverá apresentar resumo simples dos impactos relevantes, como número de tarefas reagendadas, deadlines alterados ou mudanças de estado.

### 18I.17 Operações em lote

Quando tecnicamente possível, operações em lote deverão apresentar progresso lógico, como quantidade processada.

Em falha parcial, a interface deverá informar quantas alterações foram aplicadas e quais não puderam ser concluídas.

### 18I.18 Atomicidade externa

A interface não deverá fingir atomicidade quando uma operação composta já tiver produzido efeitos parciais no Todoist.

Falhas intermediárias deverão entrar em estado de recuperação/reconciliação explicitamente rastreável.

### 18I.19 Carregamento inicial

Ao abrir um Gantt, a interface deverá preferir skeleton da estrutura a uma tela vazia com spinner central.

Quando houver último estado conhecido, ele poderá ser apresentado enquanto ocorre reconciliação com o Todoist.

### 18I.20 Primeiro carregamento

Sem dados locais disponíveis, deverá ser exibida indicação equivalente a **Carregando projeto do Todoist**, acompanhada de skeleton apropriado.

### 18I.21 Projeto vazio

Projeto sem tarefas deverá apresentar estado vazio explícito e ação **Criar primeira tarefa**.

### 18I.22 Filtros sem resultado

Ausência de resultado por filtro deverá ser distinguida de projeto vazio e oferecer ação para limpar filtros quando aplicável.

### 18I.23 Tarefa movida

Tarefa movida para outro projeto deverá permanecer indicada conforme regras de integridade, com ações:

- **Restaurar neste projeto**;
- **Remover deste Gantt**.

### 18I.24 Exclusão externa

Quando uma tarefa for excluída externamente durante sessão ativa, a interface poderá apresentar toast informativo discreto.

Eventos múltiplos semelhantes deverão ser agrupados.

### 18I.25 Problemas globais

Condições globais deverão utilizar banner persistente, por exemplo:

- OAuth Todoist desconectado;
- perda de comunicação com o servidor;
- sincronização global degradada.

### 18I.26 Agrupamento de notificações

Eventos em massa não deverão produzir dezenas de toasts individuais.

A interface deverá consolidá-los em mensagens resumidas.

### 18I.27 Duração dos toasts

Mensagens de sucesso/informação poderão desaparecer automaticamente.

Erros que exigem ação deverão permanecer até resolução ou dispensa consciente.

### 18I.28 Central de notificações

Uma central completa de notificações fica fora do MVP.

Eventos relevantes continuarão disponíveis nos mecanismos de histórico/auditoria definidos.

### 18I.29 Undo

Undo genérico fica fora do MVP.

Reversões futuras poderão ser implementadas como operações específicas apoiadas no histórico, em vez de um mecanismo universal de desfazer.

### 18I.30 Teclado

No desktop, deverão ser suportadas interações básicas:

- `Esc` para cancelar drag, criação de dependência e fechar menus/popovers quando aplicável;
- `Delete`/`Backspace` poderá iniciar exclusão da seleção, sempre com confirmação;
- `Enter` poderá abrir/editar a tarefa selecionada.

### 18I.31 Tooltips

Tooltips poderão explicar ícones e estados técnicos, mas informação essencial não poderá existir exclusivamente em hover.

### 18I.32 Seleção

Seleção deverá utilizar camada visual própria e não reutilizar exclusivamente cores semânticas de estado, atraso ou criticidade.

A seleção de uma tarefa será única e sincronizada entre linha da tabela e bloco da timeline.

### 18I.33 Alterações externas visíveis

Mudanças recebidas por webhook/sincronização poderão produzir destaque/animação breve no elemento afetado.

A interface não deverá alterar inesperadamente scroll, foco ou seleção em razão de atualização externa.

### 18I.34 Mensagens acionáveis

Sempre que possível, mensagens de erro deverão comunicar:

1. o que aconteceu;
2. qual foi o efeito;
3. qual ação o usuário pode executar.

### 18I.35 Severidades

A interface deverá padronizar pelo menos:

- informação;
- sucesso;
- aviso;
- erro.

### 18I.36 Separação entre representação e regra

Toast, animação, ghost, badge ou banner são representações de estados determinados pelo core.

O front-end não deverá utilizar esses elementos visuais como fonte de verdade para regras de negócio.

### 18I.37 Regra geral

> A interface deverá comunicar claramente estado, progresso, falhas e consequências sem interromper desnecessariamente o fluxo do usuário. Feedback local será preferido para eventos locais, banners para condições globais, toasts para eventos pontuais e modais somente quando uma decisão explícita for necessária.

## 18J. Histórico, auditoria e rastreabilidade

### 18J.1 Histórico próprio

A aplicação deverá manter histórico próprio dos eventos relevantes, independentemente do histórico oferecido pelo Todoist.

### 18J.2 Eventos auditáveis

Deverão ser auditados, quando aplicável:

- criação, edição e exclusão de tarefas;
- conclusão e reabertura;
- alterações de datas e prioridade;
- mudanças hierárquicas;
- criação, alteração e exclusão de dependências;
- reagendamentos;
- alterações de calendário;
- alterações de `completion_date_override`;
- restauração ou remoção de tarefa movida;
- inserção de tarefa em rota;
- reconstrução de rota;
- conflitos e respectivas resoluções;
- alterações relevantes de configurações do projeto.

Cliques, abertura de painéis e interações sem efeito persistente não precisam ser auditados.

### 18J.3 Origem

Cada evento deverá identificar sua origem por valor equivalente a:

- `USER_GANTT`;
- `TODOIST_EXTERNAL`;
- `AUTO_RESCHEDULE`;
- `MANUAL_RESCHEDULE`;
- `CALENDAR_CHANGE`;
- `SYNC_RECONCILIATION`;
- `SYSTEM_RECOVERY`.

### 18J.4 Responsável

Ações iniciadas na aplicação deverão registrar o usuário responsável.

### 18J.5 Estado anterior e posterior

Alterações relevantes deverão armazenar representação suficiente de `before` e `after` para explicar a mudança.

### 18J.6 Operações compostas

Operações em cascata deverão possuir uma operação-pai e itens associados.

Cada operação lógica relevante deverá receber `operation_id` único, compartilhado entre auditoria, logs, comandos externos, retries e recuperação quando tecnicamente aplicável.

### 18J.7 Causa e cadeia causal

Reagendamentos deverão registrar a causa que os originou.

Quando uma tarefa provocar alterações em sucessoras, a estrutura causal deverá ser preservada de forma que seja possível explicar por que uma tarefa indireta foi deslocada.

### 18J.8 Histórico da tarefa

O painel da tarefa deverá possuir seção de histórico carregada sob demanda, apresentando eventos em linguagem compreensível.

### 18J.9 Histórico do projeto

O projeto deverá oferecer visualização de operações relevantes, especialmente alterações em lote, calendário, sincronização e reagendamento.

### 18J.10 Histórico funcional e logs técnicos

Histórico apresentado ao usuário deverá ser separado dos logs técnicos.

Request IDs, códigos de API, retries e detalhes de exceção poderão existir nos logs sem poluir a interface funcional.

### 18J.11 Alterações externas

Mudanças detectadas no Todoist deverão ser auditadas como `TODOIST_EXTERNAL`, registrando as diferenças reais encontradas pela reconciliação.

O simples recebimento de webhook não constitui por si só um evento funcional de alteração.

### 18J.12 Simulações

Simulações canceladas não deverão gerar eventos permanentes no histórico funcional.

Simulações aplicadas deverão registrar operação, causa, usuário, timestamp e alterações resultantes.

### 18J.13 Falhas parciais e recuperação

Operações parcialmente aplicadas deverão registrar explicitamente a condição parcial.

Recuperações posteriores deverão gerar eventos vinculados ao mesmo `operation_id`.

### 18J.14 Override de conclusão

Criação, alteração ou remoção de `completion_date_override` deverá ser sempre auditada.

### 18J.15 Exclusões

A exclusão de uma tarefa não deverá apagar imediatamente seu histórico operacional.

Deverá ser preservado registro mínimo suficiente para identificar a tarefa e as operações relevantes enquanto a política de retenção permitir.

Antes de exclusões estruturais, deverão ser registradas predecessoras, sucessoras, dependências removidas e eventuais relações reconstruídas.

### 18J.16 Inserção em rota

A inserção de tarefa em uma rota deverá ser registrada como uma operação lógica única, incluindo a relação original removida e as novas relações criadas.

### 18J.17 Alterações de calendário e configuração

Mudanças de calendário deverão registrar configuração anterior, nova configuração, exceções modificadas e tarefas afetadas.

Alterações de regras de projeto, como reagendamento automático ou política de deadline, também deverão ser auditadas.

### 18J.18 Política de retenção

A política de retenção do histórico será implementada já no MVP atual como configuração global do servidor.

Configuração recomendada:

`AUDIT_RETENTION_DAYS`

Semântica:

- `-1` = retenção indefinida;
- valor `> 0` = quantidade de dias durante os quais os eventos de auditoria deverão ser retidos;
- `0` ou valores `< -1` = configuração inválida e não deverão provocar exclusão de dados.

O valor padrão inicial do MVP será:

`AUDIT_RETENTION_DAYS=-1`

A configuração não será definida por usuário, conta Todoist ou Gantt.

Uma rotina periódica de manutenção deverá remover apenas eventos elegíveis segundo a política configurada.

A limpeza deverá preservar integridade referencial e consistência de operações agrupadas.

### 18J.19 Revisão futura da retenção

Fica registrada como pendência explícita para uma versão futura do MVP/produto:

> Reavaliar o valor padrão de `AUDIT_RETENTION_DAYS` com base no volume real de histórico e na janela de retenção desejada, evitando distribuir indefinidamente o software com retenção infinita como padrão.

A implementação da política já deverá existir; a pendência futura será principalmente a definição de um valor padrão finito apropriado.

### 18J.20 Histórico e backup

Histórico/auditoria e backup são mecanismos independentes.

A política de retenção do histórico não define automaticamente a retenção dos backups.

### 18J.21 Imutabilidade lógica

Eventos de auditoria persistidos não deverão ser editados.

Correções deverão ser representadas por novos eventos relacionados.

A exclusão da conta poderá remover dados conforme a política própria de exclusão, independentemente da imutabilidade normal do histórico.

### 18J.22 Reversão futura

O histórico deverá ser estruturado de modo a permitir futura implementação de reversões específicas, embora Undo genérico continue fora do MVP.

### 18J.23 Exportação

Exportação de histórico em CSV/JSON fica para versão futura.

### 18J.24 Performance e filtros

O histórico não deverá ser carregado integralmente junto com o Gantt.

Deverá ser carregado sob demanda e paginado.

O histórico do projeto poderá oferecer filtros básicos por:

- período;
- tarefa;
- tipo de evento;
- origem.

### 18J.25 Precisão temporal

A restrição de planejamento sem horários aplica-se às datas das tarefas, não à infraestrutura.

Eventos de auditoria, logs, webhooks, sessões e sincronizações deverão utilizar timestamps precisos, preferencialmente armazenados em UTC e apresentados no timezone do usuário.

### 18J.26 Regra geral

> Toda alteração persistente relevante deverá ser rastreável quanto à origem, causa, usuário, momento, estado anterior e estado posterior. Operações em cascata serão agrupadas por identificador lógico comum, permitindo reconstruir não apenas o que mudou, mas por que determinada tarefa foi afetada.

## 18K. Desempenho, escalabilidade e limites operacionais

### 18K.1 Escala de referência

O MVP deverá ser projetado e testado para operar confortavelmente com aproximadamente **2.000 tarefas por Gantt**.

Esse valor será uma referência de dimensionamento e testes, não necessariamente um limite rígido imposto ao usuário.

Projetos acima desse volume poderão continuar sendo abertos, com eventual aviso de desempenho reduzido.

### 18K.2 Virtualização

A interface deverá utilizar virtualização vertical das linhas e, quando necessário, virtualização/janela temporal horizontal.

Tabela e timeline deverão compartilhar a mesma janela virtual para preservar alinhamento.

### 18K.3 Hierarquia colapsada

Descendentes de grupos recolhidos não precisarão ser renderizados, embora continuem participando normalmente do modelo, cálculos, dependências e caminho crítico.

### 18K.4 Carregamento progressivo

O carregamento inicial deverá priorizar os dados necessários ao Gantt.

Histórico, logs e dados secundários deverão ser carregados sob demanda.

### 18K.5 Interações locais

Expandir/recolher grupos, selecionar tarefas, abrir menus e navegar na timeline não deverão depender de round-trip ao Todoist.

Drag e resize utilizarão preview local durante a interação e cálculo autoritativo no término do gesto.

### 18K.6 Cálculos incrementais

Quando possível, o reagendamento, grupos e demais cálculos deverão operar sobre o subgrafo afetado.

O caminho crítico poderá exigir recálculo mais amplo quando a alteração afetar o término global do projeto.

Otimizações nunca poderão alterar o resultado matemático autoritativo.

### 18K.7 Batching e rate limits

Operações em lote deverão utilizar batching/fila centralizada, respeitando limites da API Todoist.

Rate limiting, `Retry-After`, backoff e controle de throughput deverão ser tratados centralmente.

### 18K.8 Webhooks em rajada

Eventos recebidos em sequência poderão ser coalescidos/debounced para evitar sincronizações e recálculos redundantes.

Atualizações enviadas aos navegadores também poderão ser consolidadas.

### 18K.9 Cache

Caches poderão ser utilizados para desempenho, mas nunca serão fonte da verdade.

Todo cache deverá poder ser invalidado e reconstruído por reconciliação.

### 18K.10 Banco e consultas

Deverão existir índices adequados para os principais acessos, incluindo:

- `user_id`;
- `gantt_project_id`;
- `todoist_task_id`;
- predecessor/sucessor;
- estados de sincronização;
- `operation_id`;
- timestamps de auditoria.

A implementação deverá evitar consultas N+1.

### 18K.11 Payloads

O payload principal do Gantt deverá conter apenas informações necessárias à visualização e interação corrente.

Histórico, logs e detalhes técnicos não deverão acompanhar cada tarefa sem necessidade.

### 18K.12 Busca

O MVP deverá possuir busca por título.

Ao selecionar um resultado, a interface deverá:

- expandir os ancestrais necessários;
- localizar a linha;
- rolar até ela;
- selecionar a tarefa.

### 18K.13 Filtros

O MVP deverá oferecer filtros simples, incluindo pelo menos:

- estado;
- prioridade;
- crítica/não crítica;
- não programadas;
- concluídas.

Filtros nunca deverão alterar o grafo ou as regras de negócio.

### 18K.14 Ocultar tarefas concluídas

A Barra do Sistema deverá possuir controle **Exibir tarefas concluídas**.

Quando desabilitado:

- tarefas concluídas poderão ser omitidas da renderização;
- elas continuarão existindo no modelo do core;
- continuarão válidas para histórico, dependências, grupos e cálculos que necessitem delas;
- a ocultação será apenas visual.

Grupos não deverão desaparecer porque seus descendentes concluídos foram ocultados.

Quando aplicável, o grupo deverá indicar a quantidade de tarefas concluídas ocultas, por exemplo:

`12 concluídas ocultas`

### 18K.15 Dependências com tarefas ocultas

Quando uma tarefa visível possuir predecessora que não está atualmente renderizada por filtro, ocultação de concluídas, colapso ou outra regra visual, a dependência não deverá simplesmente desaparecer sem indicação.

A tarefa visível deverá receber um indicador de **dependência proveniente de elemento oculto**.

Representação recomendada:

`… ─────▶ [Tarefa visível]`

Visualmente:

- reticências na origem;
- pequeno segmento horizontal;
- seta entrando no ponto apropriado da tarefa visível;
- estilo discreto, porém perceptível.

O indicador representa que existe uma ou mais predecessoras válidas fora da visualização corrente.

Quando houver múltiplas predecessoras ocultas, a interface poderá consolidá-las em um único indicador e apresentar a quantidade por tooltip, popover ou informação acessível equivalente.

A relação real permanecerá preservada no grafo e voltará a ser desenhada normalmente quando a tarefa oculta voltar a ser exibida.

De forma simétrica, quando útil à leitura, uma tarefa visível que possua sucessoras ocultas poderá utilizar indicação equivalente de saída:

`[Tarefa visível] ─────▶ …`

Essa representação é apenas visual e não altera a dependência.

### 18K.16 Filtros e grupos

Filtros poderão ocultar tarefas-folha, mas não deverão eliminar automaticamente seus grupos estruturais.

Grupos poderão indicar que existem descendentes ocultos pelos filtros atuais.

### 18K.17 Processamento assíncrono

Grandes sincronizações, cascatas ou operações em lote poderão ser executadas por worker/fila persistente.

O navegador deverá acompanhar a operação por `operation_id`.

Reinicialização de workers não poderá perder operações que necessitem de garantia.

### 18K.18 Idempotência e timeouts

Workers e retries deverão ser idempotentes ou protegidos contra duplicação.

Chamadas externas deverão possuir timeout explícito.

### 18K.19 Observabilidade

Deverão ser coletadas métricas operacionais, incluindo quando possível:

- duração de sincronizações;
- duração de recálculos;
- quantidade de tarefas do projeto;
- quantidade de tarefas afetadas;
- chamadas à API Todoist;
- retries e falhas.

### 18K.20 Testes de carga

Antes de produção, deverão ser executados testes representativos com aproximadamente:

- 100 tarefas;
- 500 tarefas;
- 1.000 tarefas;
- 2.000 tarefas;
- hierarquias profundas;
- redes densas de dependência;
- grandes cascatas.

### 18K.21 Degradação controlada

Operações demoradas deverão produzir feedback de progresso em vez de congelar silenciosamente a interface.

### 18K.22 Configurações globais

Limites e referências técnicas deverão poder ser configurados no servidor, por exemplo:

`GANTT_REFERENCE_TASK_COUNT=2000`

e demais parâmetros de batch, retry e processamento.

Essas configurações não precisam ser expostas ao usuário.

### 18K.23 Revisão futura

Após uso real, deverão ser revistos:

- referência de 2.000 tarefas;
- tamanhos de batches;
- estratégia de cache;
- comportamento de virtualização;
- métricas reais de produção;
- eventual necessidade de limite rígido por Gantt.

### 18K.24 Regra geral

> O sistema deverá operar confortavelmente com aproximadamente 2.000 tarefas por Gantt como cenário de referência do MVP, utilizando virtualização, processamento incremental, batching, filas persistentes e controle centralizado da integração. Ocultar tarefas é uma otimização visual e nunca remove sua participação no modelo ou nas regras de negócio. Dependências com elementos ocultos deverão continuar perceptíveis por indicadores de entrada/saída originados ou destinados a reticências.

## 18L. Busca, filtros e navegação

### 18L.1 Busca

A Barra do Sistema deverá possuir busca rápida por título de tarefa, tolerante a maiúsculas/minúsculas, acentuação e correspondência parcial.

A busca deverá localizar, não filtrar automaticamente o Gantt.

Resultados deverão apresentar contexto hierárquico suficiente para distinguir tarefas com títulos iguais.

### 18L.2 Navegação para resultado

Ao selecionar resultado de busca, a interface deverá:

- expandir ancestrais necessários;
- tornar a tarefa temporariamente visível quando estiver oculta;
- rolar verticalmente até ela;
- posicionar a timeline para exibir seu bloco quando aplicável;
- selecionar/destacar a tarefa.

Tarefas não programadas deverão ser localizadas na hierarquia e utilizar `OperationalToday` como referência temporal visual conforme regras existentes.

### 18L.3 Busca em itens ocultos

A busca deverá pesquisar o projeto inteiro, inclusive tarefas ocultas por filtros, conclusão ou colapso.

Resultados deverão indicar quando a tarefa estiver atualmente oculta.

Ao navegar até ela, poderá ser utilizada exibição temporária sem alterar permanentemente os filtros.

Busca exata por ID Todoist poderá ser suportada como recurso auxiliar de diagnóstico.

### 18L.4 Filtros do MVP

O MVP deverá oferecer filtros por:

- estado operacional;
- prioridade;
- criticidade;
- programada/não programada;
- conclusão;
- intervalo temporal.

### 18L.5 Semântica dos filtros

Dentro da mesma categoria, múltiplos valores utilizarão lógica `OR`.

Entre categorias diferentes, será utilizada lógica `AND`.

Filtros temporais deverão considerar interseção do intervalo da tarefa com o período selecionado.

O filtro **Hoje** utilizará `OperationalToday`.

### 18L.6 Concluídas

O controle **Exibir tarefas concluídas** será tratado internamente como parte do estado de filtragem de conclusão, evitando controles contraditórios.

### 18L.7 Indicadores de filtro

Quando houver filtros ativos, a interface deverá mostrar indicação clara, como `Filtros (3)`.

Deverá existir ação única **Limpar filtros**.

Filtros ativos poderão ser representados por chips removíveis individualmente.

No celular, o painel de filtros poderá ser apresentado como bottom sheet.

### 18L.8 Grupos e filtros

Grupos estruturais e ancestrais necessários deverão permanecer visíveis para fornecer contexto às tarefas filtradas.

Grupos exibidos apenas como contexto poderão ser visualmente atenuados.

A interface poderá informar contagens como:

`128 tarefas exibidas · 437 ocultas`

### 18L.9 Dependências com elementos ocultos

Mantém-se a convenção:

`… ─────▶ [Tarefa visível]`

e, quando aplicável:

`[Tarefa visível] ─────▶ …`

As reticências representam uma ou mais relações com elementos atualmente ocultos.

### 18L.10 Inspeção das reticências

Ao clicar/tocar no indicador de dependência oculta, deverá ser apresentado popover ou painel listando as tarefas relacionadas ocultas.

O usuário poderá selecionar uma delas para navegar diretamente até a tarefa.

Essa navegação poderá revelar temporariamente o item, expandir ancestrais, rolar e selecioná-lo.

### 18L.11 Navegação temporal

O MVP deverá oferecer:

- **Ir para hoje**;
- **Ir para início do projeto**;
- **Ir para fim do projeto**;
- **Ir para tarefa selecionada**.

Essas ações deverão preservar filtros e seleção quando compatível.

### 18L.12 Breadcrumb

O painel de edição deverá apresentar breadcrumb hierárquico quando útil, por exemplo:

`Projeto > Grupo A > Grupo B > Tarefa`

### 18L.13 Expandir/recolher

As ações **Expandir todos** e **Recolher todos** permanecem na Barra do Sistema.

Com filtros ativos, **Expandir todos** deverá priorizar a estrutura relevante aos resultados, evitando expandir desnecessariamente ramos sem itens visíveis.

A operação interna **Expandir até a seleção** deverá ser reutilizável por busca e navegação.

### 18L.14 Persistência dos filtros

Filtros serão preferências visuais locais por dispositivo e poderão ser preservados para cada Gantt.

Quando restaurados, deverão permanecer claramente indicados para evitar que o usuário esqueça que a visualização está filtrada.

Filtros não serão compartilhados entre dispositivos.

### 18L.15 URLs e deep links

Não será requisito do MVP codificar todo o estado visual de filtros, zoom e seleção na URL.

A arquitetura, porém, deverá permitir deep link interno para tarefa, em formato conceitual equivalente a:

`/gantt/{project}/task/{task}`

### 18L.16 Navegação a partir do histórico

Ao selecionar uma tarefa existente no histórico do projeto, a interface deverá navegar até ela, expandir ancestrais e selecioná-la.

Para tarefa excluída, deverá abrir o registro histórico correspondente em vez de tentar navegar para uma linha inexistente.

### 18L.17 Atalho de busca

No desktop, `/` deverá focar a busca do Gantt quando o foco não estiver em campo editável.

`Ctrl/Cmd+F` permanecerá disponível para o comportamento padrão do navegador.

### 18L.18 Mini mapa

Mini mapa/overview da timeline fica para versão futura.

### 18L.19 Filtros salvos

Views/filtros salvos personalizados ficam para versão futura.

### 18L.20 Ordenação

O Gantt principal não permitirá ordenação arbitrária por data, prioridade ou outros campos no MVP.

A ordem hierárquica/estrutural permanecerá como referência principal.

Visualizações tabulares alternativas poderão ser avaliadas futuramente.

### 18L.21 Regra de foco

Atalhos globais não deverão capturar teclas quando o usuário estiver editando campos de texto ou controles equivalentes.

### 18L.22 Regra geral

> Busca localiza; filtros reduzem temporariamente a visualização; navegação reposiciona a viewport. Nenhum desses mecanismos modifica tarefas, hierarquia, dependências ou cálculos. Tarefas ocultas continuam pertencendo integralmente ao modelo e suas relações com elementos visíveis permanecem perceptíveis.

## 18M. Configuração, administração e parâmetros operacionais

### 18M.1 Escopos de configuração

Toda configuração deverá pertencer explicitamente a um dos seguintes escopos:

1. servidor;
2. usuário;
3. projeto/Gantt;
4. dispositivo.

Preferências visuais locais não poderão substituir regras de negócio do projeto, e configurações de projeto não poderão substituir políticas globais de segurança/operação.

### 18M.2 Configurações do servidor

Parâmetros técnicos globais deverão ser administrados na implantação, incluindo quando aplicável:

- `AUDIT_RETENTION_DAYS`;
- referência de volume por Gantt;
- tamanhos de batch;
- retries e backoff;
- timeouts;
- limites de autenticação;
- intervalos de reconciliação;
- parâmetros de filas/workers;
- feature flags;
- modo de manutenção.

Esses parâmetros não serão configuráveis pelo usuário comum.

### 18M.3 Ambientes, defaults e validação

Desenvolvimento, homologação e produção poderão possuir valores diferentes.

Defaults deverão ser centralizados em schema/módulo único.

O backend deverá validar configurações no startup e rejeitar ou neutralizar valores perigosos ou inválidos.

Segredos nunca deverão ser versionados.

### 18M.4 Configurações do usuário

No MVP, as configurações de usuário deverão permanecer enxutas, incluindo:

- dados básicos da conta;
- timezone;
- conexão Todoist;
- sessões/logout;
- exclusão da conta.

### 18M.5 Timezone do usuário

A aplicação deverá persistir um timezone IANA por usuário, por exemplo `America/Sao_Paulo`.

A prioridade para definição será:

1. timezone configurado manualmente na aplicação;
2. timezone obtido da conta Todoist após OAuth;
3. timezone detectado pelo navegador;
4. UTC como fallback técnico extremo.

Após a primeira conexão OAuth, o timezone do Todoist deverá ser utilizado como default preferencial quando não existir override manual.

A interface deverá permitir ao usuário consultar e alterar seu timezone.

Quando o timezone do navegador, Todoist e aplicação divergirem, a configuração deverá deixar essa diferença compreensível ao usuário.

A aplicação não deverá alterar automaticamente o timezone global da conta Todoist.

### 18M.6 Datas de planejamento e timezone

O domínio de planejamento operará exclusivamente com datas civis sem horário, conceitualmente `LocalDate`, em formato canônico:

`YYYY-MM-DD`

Datas de planejamento e deadlines enviados ao Todoist deverão utilizar os campos de data inteira suportados pela API, sem horário.

É proibido converter uma data de planejamento para meia-noite UTC antes de enviá-la ao Todoist.

Não deverão ser fabricados valores como `00:00:00Z` para representar uma data inteira.

Timezone será utilizado para:

- determinar a data civil corrente;
- calcular `OperationalToday`;
- interpretar timestamps reais;
- apresentar auditoria, conclusão, logs e eventos ao usuário.

Alterar timezone não deverá deslocar datas de planejamento já armazenadas como `YYYY-MM-DD`, embora possa alterar qual data é considerada `OperationalToday` naquele instante.

### 18M.7 Configurações de formato

Formatos de data/hora obtidos do Todoist ou locale do navegador poderão ser utilizados como sugestão inicial de apresentação.

Internamente e nas integrações de data inteira, o formato canônico continuará sendo `YYYY-MM-DD`.

Formato de exibição não deverá alterar semântica ou persistência.

### 18M.8 Timestamps de infraestrutura

Conclusão real, auditoria, webhooks, sessões, sincronizações e logs poderão utilizar timestamps completos.

Esses timestamps deverão ser armazenados preferencialmente em UTC e convertidos para o timezone configurado do usuário para apresentação e, quando necessário, extração de data civil.

A regra de tarefas sem horário não se aplica a esses timestamps técnicos.

### 18M.9 Configurações do Gantt

Configurações de projeto deverão incluir quando aplicável:

- calendário;
- dias úteis;
- feriados/exceções;
- reagendamento automático;
- comportamento de tarefas sem data;
- políticas de cálculo aprovadas;
- demais regras matemáticas configuráveis já definidas.

Essas configurações deverão ser persistidas no servidor e acompanhar o Gantt.

### 18M.10 Tela de configurações do Gantt

O MVP deverá possuir tela/painel de configuração do Gantt organizada em seções simples, como:

- Planejamento;
- Calendário;
- Automação.

### 18M.11 Alterações com impacto

Quando uma configuração de negócio puder alterar datas ou estrutura calculada, o sistema deverá preferir:

`alterar → recalcular simulação → apresentar impacto → confirmar → persistir`

reutilizando o mecanismo de preview ghost.

Configurações sem impacto retroativo não precisam gerar simulação.

### 18M.12 Auditoria de configurações

Alterações relevantes de configuração de negócio deverão ser auditadas conforme o Item 14.

### 18M.13 Preferências locais

Preferências exclusivamente visuais poderão permanecer no dispositivo, incluindo:

- zoom;
- posição horizontal;
- largura da tabela;
- filtros;
- grupos expandidos/recolhidos;
- densidade visual quando aplicável.

Falha de armazenamento local não poderá impedir o funcionamento do Gantt.

Deverá existir ação **Restaurar visualização padrão**, afetando apenas preferências visuais.

### 18M.14 Feature flags

A arquitetura deverá suportar feature flags globais simples no servidor.

Feature flags não substituem autorização ou controles de segurança.

Não haverá tela de experimentos para usuário no MVP.

### 18M.15 Configurabilidade consciente

Regras fundamentais não deverão se tornar configuráveis apenas por conveniência técnica.

Somente parâmetros com necessidade real de produto deverão ser expostos como configuração.

### 18M.16 Versionamento de configurações

Configurações persistidas do Gantt deverão possuir versionamento de schema, conceitualmente equivalente a:

`settings_version: 1`

Mudanças futuras deverão possuir estratégia de migração para projetos existentes.

### 18M.17 Concorrência em configurações

Alterações de configuração de negócio deverão ser propagadas aos demais clientes conectados.

Simulações baseadas em configuração anterior deverão ser invalidadas ou recalculadas antes de poderem ser aplicadas.

### 18M.18 Importação, exportação e templates

Importação/exportação de configurações e templates de calendário/regras ficam para versão futura.

### 18M.19 Administração web

Painel administrativo web do servidor fica fora do MVP.

Parâmetros globais serão administrados pela configuração do ambiente/deployment.

### 18M.20 Health checks

O backend deverá possuir endpoints técnicos apropriados de:

- liveness;
- readiness.

Esses endpoints não deverão expor segredos.

### 18M.21 Modo de manutenção

A arquitetura poderá suportar configuração global de modo de manutenção, impedindo novas escritas e apresentando mensagem adequada durante operações críticas.

### 18M.22 Documentação de configuração

Todo parâmetro de servidor deverá possuir documentação contendo:

- nome;
- tipo;
- default;
- valores válidos;
- descrição;
- necessidade ou não de restart;
- impacto.

O repositório deverá possuir exemplo de configuração, como `.env.example`, sem segredos reais.

### 18M.23 Produção segura

Defaults inseguros não deverão ser aceitos silenciosamente em produção.

Segredos de sessão, OAuth e equivalentes deverão ser explicitamente configurados.

### 18M.24 Precedência técnica

Quando houver múltiplas fontes técnicas de configuração, a precedência deverá ser documentada.

Configurações persistidas de usuário/projeto deverão ser separadas de parâmetros globais de ambiente e preferências visuais locais.

### 18M.25 Aplicação de mudanças

Parâmetros técnicos que exigirem restart deverão ser identificados.

Configurações de usuário e Gantt deverão entrar em vigor sem reiniciar o servidor.

### 18M.26 Evolução futura

Um painel administrativo poderá futuramente permitir visualizar métricas, filas, saúde e parâmetros operacionais autorizados, mas fica fora do MVP atual.

### 18M.27 Regra geral

> Toda configuração deverá possuir escopo, autoridade, default, validação e política de alteração explicitamente definidos. O planejamento utiliza datas civis `YYYY-MM-DD` sem conversão UTC; timezone é aplicado à determinação de `OperationalToday` e à interpretação/apresentação de timestamps reais. O timezone do Todoist será a principal sugestão inicial após OAuth, podendo ser sobrescrito manualmente pelo usuário.

## 18N. Testes, critérios de aceite e qualidade

### 18N.1 Estratégia de testes

O MVP deverá possuir quatro camadas principais de validação:

- testes unitários;
- testes de integração;
- testes end-to-end;
- testes de carga/performance.

Não será exigida cobertura de 100%, mas as regras críticas de negócio deverão possuir cobertura forte e determinística.

### 18N.2 Motor matemático

O motor de planejamento deverá possuir testes extensivos para:

- FS, SS, FF e SF;
- calendário útil;
- feriados;
- duração;
- múltiplas predecessoras e sucessoras;
- cascatas;
- grupos;
- caminho crítico;
- tarefas sem data;
- deadlines;
- conclusão;
- `completion_date_override`.

### 18N.3 Determinismo

A mesma entrada deverá produzir a mesma saída independentemente de:

- ordem de consulta ao banco;
- ordem de chegada de webhooks;
- timezone do servidor;
- locale do processo;
- ordem acidental de coleções.

### 18N.4 Relógio controlável

O core deverá utilizar uma abstração de relógio/data atual, evitando chamadas espalhadas e não controláveis a `now()`.

Isso deverá permitir testes determinísticos de `OperationalToday`.

### 18N.5 Timezone

Deverão existir testes com diferentes fusos, incluindo pelo menos exemplos equivalentes a:

- `America/Sao_Paulo`;
- `America/New_York`;
- `Asia/Tokyo`.

Datas de planejamento deverão permanecer datas civis, sem deslocamento por conversão indevida.

### 18N.6 Regressão anti-UTC

Deverá existir teste explícito garantindo que uma data de planejamento `YYYY-MM-DD` não seja serializada como timestamp UTC para representar uma data inteira no Todoist.

### 18N.7 Calendário

Casos mínimos:

- segunda a sexta;
- sábado útil;
- domingo útil;
- feriado;
- remoção de feriado;
- criação retroativa de feriado;
- tarefa atravessando dia não útil;
- início em dia não útil;
- `OperationalToday` em dia não útil.

### 18N.8 Tarefas sem data

Testar:

- sem data e sem predecessora;
- sem data com predecessora;
- predecessora também sem data;
- persistência automática de data;
- permanência visual como `NAO_PROGRAMADA`;
- uso correto da data virtual.

### 18N.9 Deadline

Deverão existir testes para deadlines ausentes, inválidos, anteriores à data inicial e posicionados em dias não úteis conforme as políticas definidas.

### 18N.10 Dependências

Testar criação, alteração e remoção de FS, SS, FF e SF, incluindo múltiplas predecessoras e sucessoras.

### 18N.11 Ciclos

Casos como:

`A → A`

`A → B → A`

`A → B → C → A`

e ciclos maiores deverão ser rejeitados antes da persistência.

### 18N.12 Grupos

Testar:

- datas derivadas;
- mudanças em descendentes;
- grupo vazio;
- descendente não programado;
- grupo como predecessor;
- tentativa de grupo como sucessor;
- estados derivados.

### 18N.13 Exclusão e continuidade de rota

Testar exclusão simples e exclusão em rota.

Exemplo:

`A → B → C`

Com **Manter continuidade = SIM**, o resultado esperado é:

`A → C`

Com **NÃO**, nenhuma nova relação deverá ser criada.

Cenários com múltiplas entradas e saídas deverão validar ciclos, duplicidades e tipos propostos.

### 18N.14 Inserção de tarefa em rota

Caso obrigatório:

`A → B`

Inserir `X`.

Resultado esperado:

`A → X → B`

A relação original só poderá ser removida após confirmação suficiente da criação da nova tarefa.

### 18N.15 Falhas em operações compostas

Deverão ser simuladas falhas como:

- Todoist indisponível;
- falha após criação parcial;
- banco indisponível;
- retry;
- recuperação posterior.

Nenhuma dessas situações poderá gerar corrupção silenciosa.

### 18N.16 Reagendamento

Testar cascatas pequenas e grandes, preservação de duração, subgrafo afetado e comportamento do modo manual e automático.

### 18N.17 Simulação

Deverá ser garantido que:

`Simular != Persistir`

Nenhuma mudança poderá chegar ao Todoist antes da ação **Aplicar**.

Cancelar deverá preservar integralmente o estado persistido.

### 18N.18 Preview ghost

Testes end-to-end/visuais deverão confirmar que posições original e simulada são distinguíveis durante simulação.

### 18N.19 Reagendamento automático e loops

Deverá existir teste crítico garantindo que uma escrita originada pela própria aplicação e posteriormente observada por webhook/sync não dispare novo reagendamento infinito.

### 18N.20 Idempotência

Processar a mesma operação ou evento duplicado deverá produzir um único efeito lógico quando aplicável.

### 18N.21 Webhooks

Testar:

- duplicação;
- chegada fora de ordem;
- perda;
- reconciliação posterior.

### 18N.22 Múltiplos clientes

Testar duas abas e, quando possível, clientes independentes.

Uma alteração em um cliente deverá refletir nos demais sem reload.

### 18N.23 Concorrência por campo

Alterações em campos diferentes deverão coexistir.

Alterações concorrentes no mesmo campo deverão gerar conflito conforme as regras aprovadas, sem sobrescrita silenciosa.

### 18N.24 Tarefa movida

Testar:

- mudança externa de projeto;
- preservação histórica;
- bloqueio de escrita;
- restauração;
- remoção definitiva do Gantt.

### 18N.25 Tarefa excluída externamente

Deverá ser removida do fluxo conforme as regras e suas dependências deverão ser tratadas adequadamente.

### 18N.26 OAuth

Testar:

- conexão inicial;
- callback inválido;
- revogação;
- reconexão;
- tentativa de escrita sem integração válida.

### 18N.27 Passwordless

Testar:

- código válido;
- magic link válido;
- expiração;
- reutilização;
- código incorreto;
- rate limiting;
- múltiplas sessões;
- logout;
- logout global.

### 18N.28 Isolamento entre usuários

Este será critério de aceite crítico.

Usuário A não poderá acessar dados do usuário B por URL, API manual, ID conhecido ou qualquer outro caminho.

### 18N.29 Segurança automatizada

O pipeline de CI deverá incluir, quando aplicável:

- análise de dependências vulneráveis;
- secret scanning;
- análise estática;
- lint.

### 18N.30 Responsividade e touch

Testar classes representativas de:

- celular pequeno;
- celular grande;
- tablet;
- notebook;
- desktop;
- widescreen/4K.

Em touch deverão ser testados scroll, seleção, drag, resize, dependências, edição, inserção em rota e Barra de Seleção.

### 18N.31 Acessibilidade

O MVP deverá buscar conformidade prática com WCAG 2.1 AA nas partes essenciais da interface.

### 18N.32 Teclado

Testar `Esc`, `Enter`, `/`, exclusão e navegação de foco, garantindo que atalhos globais não interfiram com edição de texto.

### 18N.33 Filtros e busca

Testar:

- `OR` dentro da mesma categoria;
- `AND` entre categorias;
- filtros sem alteração do cálculo;
- tarefas ocultas;
- indicadores `… → tarefa` e `tarefa → …`;
- busca parcial;
- acentuação;
- caixa;
- tarefa oculta;
- tarefa concluída;
- tarefa não programada;
- títulos duplicados;
- ID Todoist.

### 18N.34 Performance nominal

O cenário formal de referência será um Gantt com aproximadamente **2.000 tarefas**, contendo hierarquia e dependências representativas.

### 18N.35 Stress

Deverá existir teste interno com volume superior, por exemplo 5.000 tarefas, não como garantia de performance, mas para observar degradação sem corrupção matemática ou de dados.

### 18N.36 Critérios de performance percebida

A interface deverá manter:

- scrolling utilizável;
- seleção e menus independentes do Todoist;
- drag visualmente fluido;
- feedback para operações demoradas;
- ausência de congelamentos prolongados do navegador.

Metas numéricas mais rígidas poderão ser definidas após benchmarks.

### 18N.37 Banco de integração

Testes importantes de persistência deverão utilizar o mesmo tipo de banco previsto para produção, evitando depender apenas de bancos fake/in-memory.

### 18N.38 Todoist mock e ambiente real

O CI deverá usar mock/fake determinístico da API Todoist.

Separadamente, deverá existir ambiente/conta controlada de teste para validações periódicas contra a API real.

### 18N.39 Contract tests

Deverão existir testes de contrato para detectar mudanças relevantes da API Todoist, incluindo:

- formatos de data;
- payloads;
- autenticação;
- erros;
- objetos de tarefa.

### 18N.40 Dados de teste

CI e testes automatizados deverão usar fixtures sintéticas, sem dados pessoais reais.

### 18N.41 Migrações

Migrações de banco deverão ser testadas de versão anterior para versão nova.

### 18N.42 Deployment e rollback

O deployment deverá possuir estratégia operacional de rollback.

Migrações destrutivas não deverão assumir que rollback do código sozinho restaura o estado anterior.

### 18N.43 CI obrigatório

Antes de merge/deployment, deverão executar pelo menos:

- lint;
- testes unitários;
- integração;
- verificações básicas de segurança;
- build.

E2E completo poderá executar em pipeline apropriado.

### 18N.44 Release blockers

Serão bloqueadores de release falhas em:

- isolamento entre usuários;
- integridade do grafo;
- cálculo de datas;
- sincronização sem loops;
- persistência;
- OAuth/autenticação;
- perda ou corrupção de dados.

Problemas estritamente cosméticos poderão ser avaliados separadamente.

### 18N.45 Bugs conhecidos

Bugs conhecidos não bloqueantes deverão ser explicitamente registrados antes do release.

### 18N.46 Checklist de aceite

Antes de considerar o MVP pronto, deverá existir checklist objetivo consolidando os requisitos críticos aprovados.

### 18N.47 Teste exploratório

Deverá ocorrer rodada manual de teste exploratório antes de release, com foco especial na natureza visual e interativa do Gantt.

### 18N.48 Navegadores

O MVP suportará versões atuais dos principais navegadores modernos, especialmente:

- Chrome/Chromium;
- Safari;
- Firefox.

Navegadores legados ficam fora do escopo.

### 18N.49 Dispositivos reais

Antes da entrega, deverão ser realizados testes pelo menos em um dispositivo iOS/Safari e um Android/Chromium, além de emulação.

### 18N.50 Regra geral

> O MVP somente será considerado pronto quando regras matemáticas, integridade do grafo, isolamento entre usuários, sincronização Todoist e operações destrutivas estiverem cobertas por testes determinísticos e critérios objetivos de aceite. Performance será validada com cenário nominal de aproximadamente 2.000 tarefas e a qualidade visual/interativa também será verificada em dispositivos reais.

## 18O. Implantação, operação e monitoramento

### 18O.1 Ambientes

Deverão existir ambientes separados de desenvolvimento, homologação/staging e produção, com bancos, credenciais OAuth, domínios/callbacks e configurações próprias.

Staging não deverá utilizar a base real de produção.

### 18O.2 Deploy reproduzível

O deployment deverá ser reproduzível e preferencialmente automatizado por pipeline.

Alterações manuais ad hoc no servidor não deverão ser o procedimento normal de implantação.

### 18O.3 Migrações

Migrações de banco deverão ser versionadas e executadas de forma controlada.

Migrações incompatíveis poderão utilizar modo de manutenção e deverão possuir estratégia operacional de recuperação.

### 18O.4 Workers e scheduler

Sincronizações, retries, grandes cascatas, tarefas periódicas e limpeza de auditoria poderão ser executados por workers/scheduler fora do request web.

Workers deverão possuir supervisão/reinício automático e filas persistentes quando a operação exigir garantia.

### 18O.5 Health checks

O backend deverá possuir mecanismos de liveness e readiness.

Readiness deverá refletir dependências internas críticas, como banco, sem depender de chamada síncrona ao Todoist a cada health check.

### 18O.6 Monitoramento Todoist

A operação deverá acompanhar, quando possível:

- taxa de sucesso da API;
- latência;
- 401/403;
- 429;
- 5xx;
- retries;
- backlog de sincronização.

### 18O.7 Filas

Deverão ser monitorados pelo menos:

- quantidade de operações pendentes;
- idade da operação pendente mais antiga;
- falhas permanentes;
- crescimento anormal de backlog.

### 18O.8 Alertas

A infraestrutura/operação deverá ser capaz de alertar sobre condições relevantes, incluindo:

- aplicação indisponível;
- banco indisponível;
- worker parado;
- fila excessiva;
- taxa elevada de erros;
- falhas críticas de infraestrutura.

### 18O.9 Logs estruturados

Logs do backend deverão ser estruturados quando apropriado e utilizar identificadores como `request_id` e `operation_id`.

Níveis mínimos:

- DEBUG;
- INFO;
- WARNING;
- ERROR;
- CRITICAL.

Produção não deverá registrar DEBUG continuamente por padrão.

### 18O.10 Métricas

Deverão ser observáveis, quando aplicável:

- requests;
- taxa de erros;
- latência;
- duração de sincronização;
- duração de recálculo;
- tamanho de projetos;
- backlog;
- chamadas Todoist;
- webhooks recebidos/processados.

Tracing distribuído completo poderá ficar para fase futura.

### 18O.11 Backups sob responsabilidade da infraestrutura

A aplicação **não executará, agendará, armazenará nem removerá backups**.

Backups, snapshots atômicos, retenção e restauração serão responsabilidade da infraestrutura.

A infraestrutura deverá proteger os bancos e demais volumes persistentes necessários de forma consistente.

A aplicação deverá documentar quais componentes persistentes precisam ser protegidos/restaurados em conjunto.

Não haverá configuração de RPO/RTO dentro da aplicação.

### 18O.12 Reconciliação após indisponibilidade

Webhooks eventualmente perdidos durante indisponibilidade deverão ser recuperados pela estratégia de reconciliação incremental.

Após retorno do serviço, o core deverá reconciliar diferenças e recalcular os Gantts necessários.

### 18O.13 Degradação parcial

Indisponibilidade do Todoist não deverá necessariamente indisponibilizar toda a aplicação.

Consulta do último estado conhecido poderá continuar, com indicação clara de degradação e tratamento apropriado das escritas.

Indisponibilidade do banco, por outro lado, deverá tornar a aplicação não pronta para operação normal.

### 18O.14 HTTPS, certificados e e-mail

Produção deverá utilizar HTTPS.

Renovação de certificados deverá ser automatizada pela infraestrutura quando aplicável.

O serviço de e-mail passwordless deverá ser monitorável quanto a falhas e latência.

### 18O.15 Dependências e builds

Dependências PHP/JavaScript deverão utilizar versões controladas/lockfiles.

Atualizações de segurança deverão poder ser aplicadas independentemente de grandes releases funcionais.

### 18O.16 Segredos e rotação

Segredos deverão poder ser rotacionados.

A criptografia de tokens OAuth deverá prever estratégia de rotação de chave, por exemplo por `key_id` ou mecanismo equivalente.

### 18O.17 Privacidade operacional

Logs e métricas deverão coletar apenas dados necessários.

Títulos completos de tarefas não deverão ser enviados a observabilidade externa quando identificadores forem suficientes.

### 18O.18 Versão da aplicação

A aplicação deverá expor discretamente versão/build em área de diagnóstico.

### 18O.19 Ambiente local

O repositório deverá documentar procedimento reproduzível para iniciar aplicação, banco, worker e scheduler em ambiente de desenvolvimento.

Containerização poderá ser utilizada, sem ser requisito de negócio.

### 18O.20 Documentação operacional

O desenvolvimento deverá entregar documentação de:

- instalação;
- configuração;
- deployment;
- migrações;
- workers;
- scheduler;
- health checks;
- logs;
- monitoramento;
- rotação de segredos;
- recuperação de filas.

Backup/restauração serão documentados apenas quanto aos componentes que a infraestrutura precisa proteger, não como funcionalidade da aplicação.

### 18O.21 Regra geral

> A produção deverá ser operável, observável e recuperável. A aplicação não implementará backups; essa responsabilidade pertence à infraestrutura. Falhas externas não poderão provocar perda silenciosa de dados próprios, e filas, workers, deployment e segredos deverão possuir procedimentos reproduzíveis de operação.

## 18P. Entregáveis técnicos e definição de pronto

### 18P.1 Código e repositório

O entregável deverá conter todo o código necessário ao front-end, backend PHP, workers, scheduler, migrações, testes e scripts de execução.

Um desenvolvedor novo deverá conseguir clonar o repositório e subir o ambiente seguindo a documentação.

### 18P.2 README e configuração

O repositório deverá possuir README principal com objetivo, arquitetura resumida, requisitos, instalação, configuração, execução e testes.

Deverá existir `.env.example` ou mecanismo equivalente sem segredos reais.

### 18P.3 Migrações e dados de desenvolvimento

Toda estrutura do MySQL deverá ser criada por migrações versionadas.

Poderão existir seeds/fixtures sintéticos para desenvolvimento e demonstração do Gantt.

### 18P.4 Documentação da arquitetura

Deverá existir documentação dos principais fluxos e componentes, incluindo:

- browser → backend/core;
- banco MySQL;
- Todoist/OAuth;
- webhook;
- SSE;
- filas/workers;
- reconciliação.

### 18P.5 Responsabilidades do core

A documentação deverá explicitar que calendário, dependências, cálculo, reagendamento, caminho crítico, grupos e validações pertencem ao core, não ao front-end.

### 18P.6 Modelo de dados e API interna

As principais entidades e relacionamentos deverão ser documentados.

A API interna utilizada pelo front-end deverá possuir contrato documentado, preferencialmente OpenAPI ou equivalente, com formato consistente de erros.

### 18P.7 Integração Todoist

Deverá existir documentação específica de OAuth, campos lidos/escritos, datas, deadlines, conclusão, webhooks, rate limits, retries e reconciliação.

### 18P.8 Mapa de autoridade dos dados

O projeto deverá manter mapa explícito indicando a fonte autoritativa de cada classe de informação, incluindo campos nativos Todoist, dependências, calendário, overrides, configurações e histórico.

### 18P.9 Documentação matemática

As fórmulas e regras de FS, SS, FF, SF, duração, calendário, grupos e caminho crítico deverão existir em uma seção técnica consolidada com exemplos concretos.

### 18P.10 Diagramas e ADRs

Deverão existir diagramas simples dos fluxos principais.

Decisões arquiteturais importantes poderão ser registradas em Architecture Decision Records, incluindo pelo menos as decisões sobre:

- Todoist como fonte dos campos nativos;
- datas de planejamento sem horário;
- core autoritativo;
- estratégia de atualização em tempo real;
- stack PHP + MySQL 9.7.

### 18P.11 Documentação operacional

Deverão ser documentados deployment, migrações, workers, scheduler, health checks, logs, monitoramento e segredos.

A responsabilidade de backup/restauração permanecerá da infraestrutura.

### 18P.12 Testes como entregável

A suíte de testes será parte obrigatória do entregável.

Fixtures e casos de referência deverão ser versionados.

### 18P.13 Golden cases

Deverá existir conjunto de casos matemáticos dourados com entrada e saída conhecidas para proteção contra regressões, incluindo exemplos de FS, feriados, múltiplas predecessoras, grupos e cascatas.

### 18P.14 Definition of Done

Uma funcionalidade somente será considerada concluída quando, conforme aplicável, possuir:

- regra implementada;
- persistência;
- integração;
- validação;
- tratamento de erros;
- feedback visual;
- responsividade;
- auditoria;
- testes;
- documentação.

### 18P.15 Pendências e limitações

Mocks temporários, bypasses ou TODOs que afetem integridade não poderão permanecer silenciosamente na release.

As limitações deliberadas do MVP deverão ser registradas explicitamente.

### 18P.16 Backlog futuro

Itens futuros deverão ser consolidados em backlog único, classificados por prioridade/tipo, evitando ideias futuras dispersas pela especificação.

### 18P.17 Ambiguidades

Quando existir ambiguidade capaz de alterar regra de negócio, persistência, integração Todoist ou cálculo matemático, o implementador deverá solicitar decisão em vez de criar silenciosamente uma regra nova.

Detalhes internos sem impacto no comportamento poderão ser escolhidos livremente pelo implementador.

### 18P.18 Rastreabilidade

Requisitos críticos deverão poder ser relacionados aos testes que os comprovam.

A versão final poderá adotar IDs normativos de requisito e matriz requisito → teste.

### 18P.19 Versionamento da especificação

A especificação aprovada deverá possuir versão, iniciando em `1.0` após a revisão transversal final.

Alterações posteriores deverão ser registradas em changelog.

### 18P.20 Autoridade da especificação

> Este documento consolidado será a fonte autoritativa das regras funcionais e técnicas aprovadas para o MVP, até que seja formalmente substituído. Código que contradiga requisito vigente deverá ser tratado como defeito, salvo alteração posterior aprovada da especificação.

### 18P.21 Regra geral

> O MVP será considerado especificado e entregável somente quando código, banco, integração, testes e documentação forem reproduzíveis e rastreáveis às regras aprovadas. Ambiguidades de negócio não poderão ser resolvidas silenciosamente pelo implementador.

## 19A. Sincronização, concorrência e fonte da verdade

### 19A.1 Fonte da verdade

O Todoist será a fonte persistente da verdade para todos os campos nativos das tarefas. O banco próprio será a fonte da verdade exclusivamente para metadados e configurações da aplicação.

### 19A.2 Fluxo de escrita

Toda escrita deverá seguir:

`Interface → Core → validação → Todoist → Core → Interface`

O front-end não deverá escrever diretamente na API do Todoist.

### 19A.3 Webhooks

Webhooks serão tratados como notificações de mudança, e não como garantia isolada do estado definitivo.

Quando necessário, o core deverá confirmar o estado corrente na API antes de aplicar regras derivadas.

O processamento deverá ser idempotente e tolerar:

- eventos duplicados;
- eventos fora de ordem;
- eventos atrasados;
- eventos perdidos.

Quando houver identificador de evento disponível, ele deverá ser utilizado para deduplicação.

### 19A.4 Reconciliação periódica

Webhooks fornecerão atualização rápida, enquanto reconciliação periódica garantirá consistência eventual.

A reconciliação deverá ocorrer, no mínimo:

- ao abrir um gráfico;
- através da ação manual **Sincronizar agora**;
- periodicamente para projetos ativos;
- ao retornar para uma interface que permaneceu inativa por período relevante.

A frequência será configuração técnica do servidor. Como referência inicial para o MVP, poderá ser utilizado intervalo aproximado de cinco minutos para projetos ativos, respeitando os limites da API do Todoist.

### 19A.5 Atualização da interface

O core deverá propagar alterações às interfaces conectadas sem exigir reload manual.

Poderão ser utilizados WebSocket, Server-Sent Events ou mecanismo equivalente. Para comunicação predominantemente servidor → navegador, SSE é uma opção adequada para o MVP.

### 19A.6 Edição simultânea

Alterações concorrentes não deverão sobrescrever silenciosamente edições do usuário.

Se uma atualização externa afetar campo diferente daquele em edição local, ela poderá ser aplicada normalmente.

Se afetar o mesmo campo ainda em edição, a interface deverá indicar conflito e apresentar o estado atual antes de aceitar nova gravação.

### 19A.7 Drag concorrente

Durante um drag, o gesto visual local poderá continuar mesmo que chegue atualização externa.

Ao finalizar o gesto, o core deverá validar novamente o estado corrente. Havendo conflito relevante, a gravação será cancelada e o usuário informado.

### 19A.8 Retorno de alterações próprias por webhook

Alterações enviadas pela aplicação poderão retornar por webhook e isso deverá ser considerado normal.

O recebimento do evento não poderá provocar nova escrita equivalente no Todoist, evitando loops de sincronização.

### 19A.9 Falhas temporárias

Em falhas temporárias da API:

- a interface deverá indicar operação pendente ou erro de sincronização;
- não deverá afirmar que a alteração foi definitivamente persistida;
- o core deverá realizar novas tentativas controladas com backoff.

### 19A.10 Falhas permanentes

Erros permanentes, como token revogado, tarefa inexistente ou perda de permissão, deverão interromper novas tentativas automáticas e informar claramente a ação necessária.

### 19A.11 Fila persistente de sincronização

O backend deverá possuir fila persistente para operações pendentes.

Cada operação deverá registrar, no mínimo:

- entidade;
- tipo de operação;
- momento de criação;
- estado;
- quantidade de tentativas;
- último erro.

Operações relativas à mesma tarefa deverão preservar sua ordem e não deverão ser executadas concorrentemente quando houver dependência entre elas.

### 19A.12 Operações em cascata

Reagendamentos que alterem várias tarefas deverão ser tratados como uma única operação lógica, com identificador próprio, ainda que produzam múltiplas operações individuais na API do Todoist.

### 19A.13 Exclusão de tarefa

Quando uma tarefa for removida do Todoist:

- ela deverá sair da visualização ativa;
- metadados e dependências relacionados deverão ser marcados como órfãos ou inativos;
- os registros próprios não deverão ser apagados imediatamente, permitindo diagnóstico e tratamento posterior.

### 19A.14 Tarefa movida para outro projeto

Quando uma tarefa sair do projeto Todoist associado ao gráfico:

- deverá ser removida da árvore atual;
- dependências quebradas deverão tornar-se inativas e ser sinalizadas;
- caso retorne mantendo o mesmo ID, essas relações poderão ser reativadas.

### 19A.15 Alteração de hierarquia

A hierarquia do Todoist prevalecerá.

Mudanças de pai ou seção deverão reorganizar automaticamente a interface e provocar recálculo dos grupos antigo e novo, ancestrais e dependências afetadas.

### 19A.16 Alteração externa que viola precedência

No modo manual, uma alteração feita no Todoist que viole precedência deverá ser aceita como estado nativo, sinalizada como inconsistente e incluída na próxima simulação de reagendamento.

No modo automático, o core deverá corrigir a inconsistência conforme as regras do projeto.

### 19A.17 Exceção para datas de grupos

Datas de tarefas-pai são derivadas pelo core somente para cálculo e exibição no Gantt.

O core nunca deverá gravar no Todoist os valores derivados. Sem autorização de limpeza, datas alteradas diretamente no Todoist permanecem intactas na origem e são ignoradas na projeção do agrupador. Com a autorização habilitada, o core deverá apenas remover a data e o deadline da tarefa-pai.

### 19A.18 Abertura de projeto

Ao abrir um gráfico, o sistema deverá iniciar reconciliação com o Todoist.

A interface poderá exibir imediatamente o estado disponível, acompanhada do indicador **Sincronizando...**, sendo atualizada conforme a reconciliação terminar.

### 19A.19 Estado de sincronização

A Barra do Sistema deverá possuir indicador discreto com estados equivalentes a:

- sincronizado;
- sincronizando;
- alterações pendentes;
- erro de sincronização.

Também deverá existir a ação **Sincronizar agora**, que força reconciliação com o Todoist.

### 19A.20 Navegador offline

O MVP não oferecerá edição offline.

Enquanto não houver conexão:

- dados previamente carregados poderão permanecer visíveis;
- a interface deverá informar o estado offline;
- operações de edição deverão permanecer desabilitadas até a reconexão.

Edição offline com sincronização posterior ficará para versão futura.

### 19A.21 Auditoria técnica mínima

O backend deverá registrar eventos relevantes de sincronização, incluindo:

- webhooks recebidos;
- alterações enviadas ao Todoist;
- respostas e erros;
- reconciliações;
- reagendamentos;
- correções automáticas;
- origem da alteração: usuário, Todoist ou algoritmo.

Não será necessária interface completa de auditoria no MVP.

### 19A.22 Regra geral de consistência

A arquitetura seguirá o princípio:

> Webhook fornece velocidade; reconciliação garante consistência; Todoist é a fonte da verdade dos campos nativos; o banco próprio é a fonte da verdade dos metadados da aplicação; divergências com risco de perda de edição do usuário nunca deverão ser resolvidas silenciosamente.

## 19B. Transação e aplicação do reagendamento em cascata

### 19B.1 Unidade lógica

Cada reagendamento confirmado deverá ser tratado como uma única operação lógica, identificada por `recalculation_id`, contendo todas as alterações individuais previstas.

### 19B.2 Snapshot

Antes da aplicação, o core deverá registrar snapshot lógico das tarefas afetadas, contendo pelo menos:

- ID Todoist;
- data inicial anterior;
- deadline anterior;
- nova data inicial;
- novo deadline;
- duração;
- dependências relevantes;
- estado de conclusão;
- versão ou timestamp conhecido.

### 19B.3 Revalidação antes da aplicação

Entre a simulação e a confirmação, as tarefas poderão ter mudado.

Antes do commit, o core deverá revalidar o estado. Se uma mudança relevante invalidar a simulação:

- a aplicação não deverá começar;
- o usuário deverá ser informado;
- uma nova simulação deverá ser calculada.

### 19B.4 Ordem topológica e batches

A aplicação deverá respeitar a ordem topológica das dependências.

As escritas poderão ser agrupadas em batches utilizando o endpoint de sincronização do Todoist, respeitando o limite vigente da API.

Como regra de implementação:

- tarefas independentes dentro da mesma camada topológica poderão ser enviadas no mesmo batch;
- uma camada sucessora somente deverá ser processada após confirmação suficiente da camada anterior;
- grupos derivados deverão ser recalculados depois das tarefas-folha, de baixo para cima.

### 19B.5 Identificação idempotente por comando

Cada comando enviado à Sync API deverá possuir UUID próprio e persistente associado à alteração individual.

Em retries, o mesmo UUID deverá ser reutilizado para a mesma operação lógica, aproveitando o comportamento idempotente disponibilizado pela API.

O resultado individual de cada comando deverá ser avaliado através do status retornado pela Sync API.

### 19B.6 Falha parcial

A aplicação não deverá assumir atomicidade transacional entre múltiplas chamadas ou múltiplos comandos remotos.

Se parte das alterações for aplicada e outra parte falhar:

- não deverá existir rollback remoto automático imediato;
- o `recalculation_id` deverá ser marcado como `PARCIALMENTE_APLICADO`;
- alterações já confirmadas deverão ser registradas como aplicadas;
- falhas temporárias deverão permanecer pendentes para retry;
- falhas permanentes deverão ser explicitamente registradas e exibidas;
- ramificações dependentes de uma predecessora não aplicada não deverão avançar.

### 19B.7 Estados da operação

Estados do reagendamento:

- `SIMULADO`;
- `AGUARDANDO_CONFIRMACAO`;
- `APLICANDO`;
- `PARCIALMENTE_APLICADO`;
- `CONCLUIDO`;
- `FALHA`;
- `CANCELADO`.

Estados das alterações individuais:

- `PENDENTE`;
- `APLICANDO`;
- `APLICADO`;
- `SEM_ALTERACAO_NECESSARIA`;
- `PENDENTE_RETRY`;
- `FALHA_PERMANENTE`.

### 19B.8 Retries

Falhas temporárias deverão ser reprocessadas automaticamente com backoff.

Antes de repetir uma escrita, o core deverá verificar se o estado desejado já está presente. Se estiver, a alteração poderá ser considerada aplicada sem nova escrita.

### 19B.9 Falhas permanentes

Falhas permanentes interrompem retries automáticos daquela alteração.

O sistema deverá informar claramente as tarefas afetadas e a causa conhecida.

### 19B.10 Interface durante aplicação

Durante a aplicação:

- somente tarefas envolvidas deverão ser bloqueadas para edição quando possível;
- o restante do projeto poderá permanecer utilizável;
- a interface deverá apresentar progresso discreto;
- estados parciais não poderão ser apresentados como concluídos.

### 19B.11 Alteração externa concorrente

Antes de cada escrita relevante, o core deverá validar se a alteração ainda é aplicável.

Conflitos externos em tarefas ainda não processadas deverão interromper a ramificação afetada e exigir reavaliação.

Alterações externas em tarefas já aplicadas serão tratadas como novos eventos após a conclusão daquela escrita.

### 19B.12 Confirmação visual

Antes de aplicar um reagendamento manual, a interface deverá apresentar:

- quantidade de tarefas afetadas;
- deslocamentos previstos;
- primeira e última data afetadas;
- tarefas críticas afetadas;
- inconsistências detectadas;
- posição original em formato ghost;
- nova posição simulada.

### 19B.13 Cancelamento

O cancelamento será permitido apenas antes da primeira escrita remota.

Depois que a aplicação começar, o sistema não deverá apresentar cancelamento como se houvesse rollback transacional garantido.

### 19B.14 Desfazer

Undo completo ficará para fase futura.

O snapshot deverá, entretanto, ser preservado para permitir futuramente uma operação **Desfazer reagendamento**, implementada como nova simulação e nova aplicação.

### 19B.15 Histórico

O banco deverá manter registro estruturado de cada reagendamento com:

- ID;
- usuário;
- projeto;
- origem manual ou automática;
- timestamps;
- estado;
- alterações antes/depois;
- erros;
- tentativas;
- comandos enviados.

### 19B.16 Reagendamento automático

O modo automático utilizará exatamente o mesmo mecanismo de simulação, validação, snapshot, aplicação, retries e auditoria.

A única diferença será a ausência da etapa de confirmação manual.

### 19B.17 Reprocessamento

Operações pendentes poderão ser reprocessadas utilizando o mesmo `recalculation_id` enquanto o estado original continuar válido.

Se alterações posteriores tornarem o plano antigo obsoleto, ele deverá ser invalidado e uma nova simulação será necessária.

### 19B.18 Limites e processamento em lotes

O sistema não deverá impor inicialmente um limite funcional arbitrário para a quantidade de tarefas reagendadas.

Operações grandes deverão ser processadas internamente em lotes, respeitando os limites vigentes da API Todoist, permanecendo uma única operação lógica para o usuário.

### 19B.19 Reconciliação após aplicação

Após operações em lote ou situações de falha parcial, o core deverá utilizar sincronização incremental/reconciliação para confirmar o estado corrente das tarefas envolvidas.

### 19B.20 Regra de atomicidade

> A aplicação não deverá assumir atomicidade transacional sobre múltiplas chamadas à API do Todoist. O reagendamento será consistente no domínio lógico da aplicação por meio de snapshot, estados intermediários, UUIDs idempotentes, retries, processamento topológico e reconciliação.

## 20. Reagendamento

### 20.1 Princípio geral

O sistema não deverá alterar automaticamente as datas por padrão.

Ao identificar tarefas atrasadas ou violações de precedência, deverá informar que existem tarefas que podem precisar de reagendamento.

### 20.2 Simulação

O usuário poderá executar a função **Simular Reagendamento**.

Durante a simulação:

- nenhuma alteração deverá ser gravada;
- o cálculo deverá ocorrer somente em memória;
- as tarefas impactadas deverão ser reposicionadas visualmente;
- a posição original deverá permanecer visível como um elemento "ghost", semitransparente;
- a nova posição deverá ser exibida claramente;
- o usuário deverá conseguir identificar quais tarefas serão alteradas.

### 20.3 Reagendamento em cascata

O algoritmo deverá:

1. localizar tarefas atrasadas ou inconsistentes;
2. aplicar as regras de dependência;
3. respeitar dias úteis;
4. recalcular sucessoras;
5. continuar o cálculo em cascata até que não existam novas violações.

### 20.4 Confirmação

Somente após confirmação explícita do usuário:

- as novas datas serão persistidas;
- campos nativos serão atualizados no Todoist;
- metadados próprios, quando aplicável, serão atualizados no banco.

### 20.5 Tarefas concluídas

Tarefas concluídas nunca deverão ser reagendadas.

### 20.6 Reagendamento automático opcional

O sistema poderá possuir uma configuração de projeto para habilitar reagendamento automático.

Quando habilitado, tarefas pendentes poderão ser recalculadas automaticamente quando uma predecessora atrasar ou permanecer aberta além da previsão.

O modo padrão do MVP deverá permanecer manual.

---

## 21. Escala temporal

O gráfico deverá suportar diferentes escalas de visualização, no mínimo:

- diária;
- semanal;
- mensal.

A unidade lógica da tarefa continuará sendo sempre o dia.

Mudanças de escala afetam apenas a visualização e não alteram a precisão dos dados.

---

## 22. Definição visual fixa do MVP

O MVP deverá utilizar uma identidade visual definida pela implementação, sem oferecer personalização de cores pelo usuário.

Deverão ser visualmente diferenciados:

- não iniciada;
- em execução;
- concluída;
- atrasada;
- caminho crítico;
- tarefa ativa;
- tarefa selecionada;
- posição original durante simulação;
- posição simulada;
- grupos;
- dias não úteis;
- linhas de dependência.

A prioridade do Todoist poderá ser apresentada por:

- ícone;
- marcador;
- indicador lateral.

A prioridade não deverá necessariamente modificar a cor principal da barra, para não conflitar com a indicação de status.

Personalização de temas, cores e aparência ficará fora do escopo do MVP.

---

## 23. Responsividade

A interface deverá ser totalmente responsiva.

Deverá funcionar adequadamente desde celulares até monitores widescreen 4K.

### 23.1 Desktop e telas grandes

Em telas largas:

- árvore e gráfico deverão permanecer lado a lado;
- deverá ser possível aproveitar a largura adicional;
- o Gantt deverá expandir a área temporal sem limitar artificialmente a largura.

### 23.2 Mobile

Em celulares:

- os controles deverão ser utilizáveis por toque;
- o layout deverá reorganizar-se sem perder funcionalidades essenciais;
- o painel lateral de edição poderá ocupar a tela inteira;
- tabela e gráfico poderão utilizar colapso, rolagem ou alternância de visualização conforme necessário.

### 23.3 Entrada

A interface deverá ser utilizável tanto com:

- mouse;
- trackpad;
- toque.

---

## 24. Autenticação do sistema

O MVP utilizará autenticação simples sem senha permanente.

### 24.1 Login

Fluxo proposto:

1. usuário informa o e-mail;
2. sistema envia:
   - código temporário; ou
   - link de autenticação;
3. usuário confirma o acesso;
4. sistema cria uma sessão autenticada;
5. um token de sessão é salvo em cookie.

### 24.2 Sessões múltiplas

O mesmo usuário poderá permanecer autenticado simultaneamente em diferentes dispositivos.

Exemplos:

- desktop;
- celular;
- tablet.

Cada dispositivo deverá possuir sua própria sessão/token.

### 24.3 Revogação

A arquitetura deverá permitir revogar sessões individualmente, ainda que uma interface completa de gerenciamento de sessões possa ser simplificada no primeiro MVP.

### 24.4 Segurança mínima

Mesmo sendo um MVP simples:

- tokens de sessão não deverão ser armazenados em texto puro quando isso puder ser evitado;
- cookies deverão utilizar parâmetros de segurança compatíveis com HTTPS;
- credenciais do Todoist deverão ser armazenadas de forma protegida.

---

## 25. Integração da conta do Todoist

No primeiro acesso, após autenticar-se, o usuário deverá conectar sua conta Todoist por meio do fluxo OAuth oficial.

O fluxo detalhado encontra-se na seção de OAuth deste documento.

No MVP:

- um usuário poderá conectar apenas uma conta Todoist;
- haverá apenas uma integração Todoist ativa por usuário;
- a integração com Todoist é obrigatória para o uso operacional do Gantt;
- token pessoal informado manualmente pelo usuário não será o fluxo normal de integração.

---

## 26. OAuth

OAuth fará parte do MVP inicial.

A integração de cada usuário com o Todoist deverá ser realizada por meio do fluxo OAuth oficial da aplicação.

### 26.1 Objetivos

O uso de OAuth no MVP deverá permitir:

- autorização individual de cada usuário;
- obtenção e armazenamento seguro das credenciais necessárias à integração;
- associação inequívoca entre usuário da aplicação e conta Todoist;
- habilitação correta do fluxo de webhooks da aplicação;
- revogação da integração sem necessidade de manipulação manual de token.

### 26.2 Fluxo esperado

Após autenticar-se na aplicação:

1. o usuário inicia a conexão com o Todoist;
2. é redirecionado ao fluxo OAuth do Todoist;
3. autoriza a aplicação;
4. o Todoist retorna ao callback HTTPS cadastrado;
5. o backend conclui a troca de credenciais;
6. a integração é associada ao usuário;
7. a sincronização inicial é executada;
8. os projetos disponíveis são carregados.

### 26.3 Requisitos técnicos

A aplicação deverá possuir:

- domínio próprio;
- HTTPS válido;
- callback OAuth registrado;
- credenciais de aplicação protegidas no servidor;
- armazenamento seguro dos tokens de integração;
- mecanismo de revogação e reconexão.

### 26.4 Regra do MVP

No MVP:

- cada usuário poderá conectar apenas uma conta Todoist;
- a integração Todoist será obrigatória para utilização do sistema;
- o fluxo manual de token pessoal não será o fluxo principal de autenticação da integração.


---

## 27. Fluxo de primeiro acesso

Fluxo esperado:

1. usuário acessa o sistema;
2. informa o e-mail;
3. recebe código ou link;
4. conclui autenticação;
5. sistema cria sessão;
6. usuário escolhe conectar sua conta Todoist;
7. sistema inicia o fluxo OAuth;
8. usuário autoriza a aplicação no Todoist;
9. callback OAuth conclui a integração;
10. sistema executa a sincronização inicial;
11. sistema carrega projetos disponíveis;
12. usuário cria um gráfico escolhendo um projeto;
13. sistema importa a estrutura;
14. gráfico é exibido.

---

## 28. Tratamento de erros

O sistema deverá tratar de forma clara:

- indisponibilidade da API do Todoist;
- credencial inválida;
- credencial revogada;
- falha de webhook;
- erro durante sincronização;
- tentativa de editar tarefa removida;
- dependência apontando para tarefa inexistente;
- conflito de atualização.

Falhas de sincronização não deverão resultar em alteração silenciosa ou perda de informação.

---

## 29. Itens fora do escopo do MVP e backlog futuro

Os seguintes recursos ficam explicitamente fora do MVP atual, salvo revisão posterior aprovada:

- milestones/marcos;
- restrições avançadas de data;
- linha de base/baseline;
- comparação analítica avançada entre planejamento original e realizado;
- percentual manual de progresso;
- estados personalizados de tarefa;
- edição/exibição avançada de folga/slack;
- lag/lead configurável nas dependências;
- personalização de cores e temas;
- configurações visuais extensas;
- múltiplas contas Todoist por usuário;
- dependências entre projetos Todoist diferentes;
- grupos como sucessores;
- relações grupo→grupo;
- planejamento em horas ou frações de dia;
- suporte completo a tarefas recorrentes;
- Undo genérico;
- mini mapa/overview da timeline;
- filtros/views salvos;
- ordenação arbitrária da hierarquia principal;
- colaboração própria entre usuários, papéis e compartilhamento de Gantt;
- links públicos;
- exportação de histórico;
- importação/exportação e templates de configurações/calendários;
- painel administrativo web do servidor;
- edição offline/offline-first;
- central completa de notificações;
- tracing distribuído completo.

Itens futuros deverão ser mantidos em backlog consolidado e não tratados como comportamento implícito do MVP.

---

## 30. Requisitos técnicos e stack obrigatório

A implementação deverá ser uma aplicação web.

### 30.1 Backend

O backend/core deverá ser implementado em **PHP**.

A versão mínima/exata do PHP deverá ser fixada na fase de bootstrap técnico do repositório e documentada no ambiente de execução, preservando compatibilidade com as bibliotecas escolhidas e com o ciclo de suporte de produção.

### 30.2 Servidor HTTP

A aplicação PHP será servida em produção por **Apache HTTP Server** ou servidor HTTP/reverse proxy equivalente compatível com a arquitetura de deployment.

A configuração deverá suportar:

- HTTPS;
- callbacks OAuth;
- endpoint de webhooks;
- SSE quando utilizado;
- compressão HTTP;
- headers de segurança;
- encaminhamento correto para workers/endpoints da aplicação quando aplicável.

### 30.3 Banco de dados

O banco relacional obrigatório será **MySQL 9.7**.

O schema, migrações, constraints, índices e testes de integração deverão ser compatíveis com essa versão.

A aplicação não deverá depender de comportamento específico de outro SGBD para sua operação normal.

### 30.4 Componentes

A aplicação deverá possuir, conceitualmente:

- front-end responsivo;
- backend/core em PHP;
- integração com API do Todoist;
- receptor de webhooks;
- banco MySQL 9.7;
- sistema de autenticação;
- gerenciamento de sessões;
- canal de atualização do core para a interface;
- fila/workers persistentes quando necessários;
- scheduler;
- módulo de cálculo de dependências;
- módulo de reagendamento;
- módulo de calendário;
- módulo de auditoria.

---

## 31. Regra de separação de responsabilidades

### Todoist

Responsável por:

- existência das tarefas;
- conteúdo nativo das tarefas;
- projeto e seção;
- hierarquia;
- prioridade;
- datas;
- deadline;
- conclusão.

### Banco próprio

Responsável por:

- usuários;
- sessões;
- integração com Todoist;
- gráficos configurados;
- dependências;
- tipos de dependência;
- calendário;
- feriados;
- configurações do projeto;
- metadados exclusivos da aplicação.

### Core

Responsável por:

- regras de negócio;
- sincronização;
- cálculo de datas;
- precedência;
- caminho crítico;
- reagendamento;
- consistência dos dados;
- comunicação com o front-end.

### Front-end

Responsável por:

- apresentação;
- interação;
- edição;
- visualização do Gantt;
- recebimento dos estados calculados pelo core.

---

## 32. Critérios principais de aceite do MVP

O MVP somente poderá ser considerado funcional quando, além dos testes e critérios detalhados neste documento, for possível demonstrar de ponta a ponta:

1. autenticação passwordless e sessões múltiplas;
2. integração Todoist via OAuth;
3. recebimento de webhooks e reconciliação incremental;
4. seleção de um projeto Todoist e criação de um único Gantt correspondente;
5. carregamento de seções, tarefas, subtarefas e hierarquia;
6. criação, edição, conclusão, reabertura e exclusão de tarefas pelo Gantt;
7. representação de tarefas sem data, datas virtuais e `OperationalToday`;
8. edição temporal por drag e resize em dias inteiros;
9. calendário próprio por Gantt, dias úteis e exceções;
10. criação, alteração e remoção de dependências FS, SS, FF e SF;
11. bloqueio de ciclos, autodependências e relações proibidas;
12. grupos derivados, com grupo permitido como predecessor e proibido como sucessor no MVP;
13. cálculo consistente de duração, precedência e caminho crítico;
14. tratamento de conclusão real e `completion_date_override`;
15. simulação de reagendamento com ghost antes da persistência;
16. reagendamento manual e automático com cascata e tratamento de falha parcial;
17. inserção de tarefa no meio de uma rota;
18. exclusão de tarefa com opção de continuidade da rota;
19. tratamento distinto de tarefa movida para outro projeto e tarefa efetivamente excluída;
20. sincronização sem reload entre Todoist, core e interfaces conectadas;
21. tratamento de conflitos e operações pendentes;
22. busca, filtros e navegação, incluindo dependências com elementos ocultos;
23. ocultação de concluídas sem remover grupos ou dependências do modelo;
24. histórico/auditoria rastreável por operação;
25. isolamento completo entre usuários;
26. funcionamento responsivo em celular, tablet, desktop e widescreen/4K;
27. operação de referência com aproximadamente 2.000 tarefas por Gantt;
28. execução da suíte de testes e ausência de release blockers definidos;
29. operação sobre backend PHP e banco MySQL 9.7;
30. documentação e entregáveis previstos na Definition of Done.

---

## 33. Estado final da revisão transversal

A revisão transversal funcional, arquitetural e técnica foi concluída.

As regras antigas substituídas ao longo da evolução da especificação foram corrigidas, e as decisões técnicas de implementação consideradas bloqueantes foram fechadas.

Não permanecem lacunas conhecidas que impeçam o início do desenvolvimento.

Detalhes internos menores poderão ser decididos durante a implementação, desde que respeitem integralmente os requisitos e contratos deste documento.

Qualquer ambiguidade futura capaz de alterar regra de negócio, persistência, integração Todoist, cálculo matemático, segurança ou experiência definida deverá retornar para decisão explícita antes de ser implementada.

---

## 33A. Arquitetura técnica final aprovada

As decisões técnicas abaixo estão fechadas e fazem parte da Especificação v1.0.

### 33A.1 PHP

O backend deverá utilizar **PHP 8.4 ou superior dentro da linha compatível aprovada pelo projeto**.

O ambiente atual de referência utiliza PHP 8.4.21.

O projeto deverá declarar explicitamente sua versão mínima suportada e manter a versão efetivamente utilizada documentada no ambiente de deployment.

### 33A.2 Framework backend

O backend deverá utilizar **Laravel**.

Laravel será responsável pela infraestrutura da aplicação, incluindo quando aplicável:

- roteamento;
- middleware;
- validação;
- migrations;
- ORM/persistência;
- sessões;
- filas;
- scheduler;
- rate limiting;
- integração com testes;
- organização da camada de aplicação.

O motor matemático do Gantt deverá permanecer em uma camada de domínio própria, tão desacoplada do framework quanto razoavelmente possível.

### 33A.3 Arquitetura do backend

A aplicação será um **monólito modular**.

Os principais módulos/domínios deverão incluir conceitualmente:

- autenticação;
- integração Todoist;
- projetos/Gantt;
- tarefas;
- dependências;
- scheduling/core;
- calendário;
- sincronização;
- auditoria;
- operações/jobs.

Microserviços ficam fora do MVP.

### 33A.4 Front-end

A área operacional autenticada deverá utilizar:

- **Vue 3**;
- **TypeScript**;
- store reativa centralizada, preferencialmente Pinia ou equivalente.

### 33A.5 SPA

Após a autenticação, **toda a interface operacional principal será uma única SPA — Single-Page Application**.

A SPA deverá conter, sem reload completo durante a operação normal:

- Barra do Sistema;
- hierarquia/tabela;
- timeline;
- gráfico de Gantt;
- barras;
- grupos;
- dependências;
- Barra de Itens Selecionados;
- painel de edição;
- busca;
- filtros;
- configurações do projeto;
- simulações;
- reagendamentos;
- histórico relacionado ao projeto.

Login passwordless, callbacks OAuth e rotas técnicas poderão existir fora da SPA.

A SPA deverá consumir dados e comandos do backend por API JSON e receber atualizações assíncronas do core via SSE.

### 33A.6 API interna

A comunicação da SPA com o backend deverá utilizar **API HTTP JSON**, versionada conceitualmente em `/api/v1`.

O contrato deverá ser documentado por OpenAPI ou mecanismo equivalente.

Datas civis deverão utilizar `YYYY-MM-DD`.

Timestamps deverão utilizar formato ISO 8601 apropriado.

### 33A.7 Atualização em tempo real

A estratégia padrão do MVP para servidor → navegador será **Server-Sent Events — SSE**.

Comandos do navegador continuarão sendo enviados por HTTP normal.

O cliente deverá possuir reconexão com backoff e posterior reconciliação quando a conexão SSE for interrompida.

### 33A.8 Gantt próprio

O componente de Gantt será **desenvolvido integralmente como parte da aplicação**.

Não será utilizada uma biblioteca de Gantt de terceiros como base estrutural da visualização, interação ou lógica principal.

Essa decisão existe para preservar domínio total sobre o principal diferencial do produto, incluindo:

- hierarquia;
- virtualização;
- barras;
- grupos em formato de resumo;
- dependências ortogonais;
- ghosts;
- caminho crítico;
- dependências ocultas;
- inserção em rota;
- mobile/touch;
- evolução futura do comportamento.

Bibliotecas genéricas de baixo nível poderão ser utilizadas quando não impuserem modelo próprio de Gantt ou regras de negócio.

### 33A.9 Renderização das dependências

A implementação inicial deverá utilizar **SVG sobreposto à timeline** para as linhas de dependência e seus elementos interativos.

A arquitetura deverá permitir substituição da camada de desenho se testes de performance demonstrarem necessidade.

Canvas poderá ser avaliado futuramente especificamente para a camada gráfica, sem mover regras de negócio para o renderizador.

### 33A.10 Spike técnico obrigatório do Gantt

Antes de consolidar toda a implementação visual, deverá ser criado um spike técnico do renderizador próprio.

O spike deverá demonstrar, no mínimo:

- 2.000 tarefas;
- árvore hierárquica;
- virtualização vertical;
- virtualização/janela horizontal;
- dependências;
- drag;
- resize;
- zoom diário/semanal/mensal;
- scroll sincronizado;
- grupos;
- ghosts;
- filtros;
- touch;
- responsividade.

Também deverá existir cenário de stress com aproximadamente 5.000 tarefas para observar degradação sem exigir a mesma garantia de performance do cenário nominal.

O spike será um gate técnico para a implementação principal.

### 33A.11 Banco de dados

O banco obrigatório será **MySQL 9.7**.

Deverá utilizar `utf8mb4` e collation Unicode apropriada.

Schema, migrations, índices, constraints e testes de integração deverão ser compatíveis com essa versão.

### 33A.12 Servidor HTTP e PHP

A implantação de referência deverá utilizar **Apache HTTP Server + PHP-FPM**, ou arquitetura equivalente compatível com os requisitos.

Deverá suportar adequadamente:

- HTTPS;
- OAuth;
- webhooks;
- SSE;
- compressão;
- headers de segurança.

### 33A.13 Filas

O MVP utilizará inicialmente o sistema de filas do Laravel com backend persistente em **MySQL**, salvo decisão técnica posterior fundamentada por desempenho.

A arquitetura deverá permitir migração futura para Redis sem alteração das regras de negócio.

Redis não será requisito obrigatório do MVP.

### 33A.14 Scheduler

Tarefas periódicas deverão utilizar Laravel Scheduler ou mecanismo equivalente da aplicação, acionado pela infraestrutura.

### 33A.15 Sessões

A autenticação do navegador deverá utilizar sessão server-side por dispositivo e cookie seguro.

JWT não será utilizado como mecanismo principal de sessão do browser no MVP.

### 33A.16 Identificadores internos

Entidades próprias deverão utilizar identificadores internos independentes dos IDs Todoist.

Preferencialmente deverão ser utilizados UUIDv7 ou ULID, conforme suporte maduro disponível no stack.

IDs Todoist continuarão sendo tratados como identificadores externos opacos.

### 33A.17 Transações locais

Transações MySQL deverão ser curtas.

Nunca deverá permanecer uma transação de banco aberta aguardando resposta HTTP do Todoist.

Operações que atravessam banco e Todoist deverão utilizar o modelo já especificado de operação lógica, fila, idempotência, reconciliação e recuperação.

### 33A.18 CSS e componentes visuais

A implementação poderá utilizar **Tailwind CSS** e componentes próprios.

Bibliotecas headless poderão ser utilizadas para primitives de interface quando úteis.

Nenhuma biblioteca visual deverá ditar as regras do Gantt.

### 33A.19 Internacionalização

A arquitetura do front-end deverá nascer preparada para i18n.

O idioma inicial do MVP será **Português do Brasil (`pt-BR`)**.

Locale e timezone são conceitos independentes.

Strings da interface não deverão ficar espalhadas de forma que impeça internacionalização futura.

### 33A.20 PWA e offline

O MVP não será uma PWA offline-first.

Service workers complexos, edição offline e sincronização posterior ficam fora do escopo atual.

### 33A.21 Testes do front-end

Além dos testes do core PHP, o front-end deverá possuir testes unitários/de componente quando aplicáveis e testes end-to-end.

A ferramenta recomendada para E2E é **Playwright** ou equivalente tecnicamente justificado.

### 33A.22 Todoist Adapter

Toda comunicação com a API Todoist deverá passar por uma camada dedicada de **Todoist Adapter/Gateway**.

Módulos de cálculo não poderão chamar diretamente endpoints do Todoist.

A camada deverá centralizar:

- autenticação;
- chamadas;
- serialização;
- datas;
- rate limits;
- retries;
- erros;
- contract tests.

### 33A.23 Observabilidade

A aplicação deverá emitir logs estruturados, métricas e health endpoints.

A infraestrutura poderá escolher a plataforma concreta de coleta.

O produto não ficará acoplado a um fornecedor específico de observabilidade.

### 33A.24 Ordem recomendada de implementação

A sequência técnica recomendada será:

1. bootstrap PHP/Laravel/MySQL;
2. modelo de dados e migrations;
3. core matemático e golden tests;
4. Todoist Adapter, OAuth e contract tests;
5. autenticação e sessões;
6. API interna;
7. spike técnico do Gantt próprio;
8. front-end operacional SPA;
9. sincronização, webhooks e SSE;
10. operações compostas e reagendamento;
11. auditoria;
12. performance, segurança e E2E;
13. hardening e release.

### 33A.25 Regra de fechamento técnico

As escolhas acima estão aprovadas para a Especificação v1.0.

Detalhes internos menores poderão ser definidos durante o desenvolvimento desde que não alterem:

- comportamento funcional;
- contratos;
- segurança;
- persistência;
- regras matemáticas;
- experiência especificada;
- autoridade do core.

## 34. Diretriz do MVP

A principal diretriz é manter o produto simples.

Sempre que houver conflito entre:

- adicionar um recurso avançado; ou
- manter comportamento previsível e fácil de implementar,

o MVP deverá privilegiar a solução mais simples, desde que preserve:

- hierarquia;
- datas;
- dependências;
- caminho crítico;
- calendário;
- sincronização;
- reagendamento;
- usabilidade do Gantt.
