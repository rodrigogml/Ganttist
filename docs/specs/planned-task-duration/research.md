# Research: Duração Planejada da Tarefa

## Decision 1: duração explícita é opcional e medida em dias úteis

**Decision**: Persistir uma duração planejada opcional, inteira de 1 a 3650 dias úteis. A ausência do valor conserva o modo legado: a duração continua sendo inferida pelas datas disponíveis pelo core atual.

**Rationale**: permite estimar tarefas sem datas e evita alterar o significado de tarefas existentes, em especial as que só possuem deadline. O teto cobre planejamentos plurianuais e limita entradas acidentais que aumentariam o custo de cálculo. O calendário do projeto já é a autoridade para contar, somar e subtrair dias úteis.

**Alternatives considered**: tornar duração obrigatória com default `1` apagaria a distinção entre estimativa não informada e estimativa de um dia; armazenar dias corridos contrariaria a matemática já usada por dependências e caminho crítico.

## Decision 2: a intenção de edição resolve o trio início, fim e duração

**Decision**: Os comandos de criação e atualização transportam, além dos valores, o campo de planejamento que foi alterado (`start`, `finish` ou `duration`) sempre que enviarem duração explícita junto de início ou fim. O backend normaliza o trio em uma única transação e é a autoridade final da regra.

**Rationale**: um payload que contém os três valores não permite deduzir de forma confiável se o usuário quis preservar fim ou duração. O mesmo contrato cobre formulário, resize e movimento do Gantt sem regras divergentes na SPA.

**Alternatives considered**: inferir pelo primeiro campo diferente falha em movimento e resize; deixar a SPA decidir sozinha permite que clientes concorrentes persistam combinações inconsistentes.

## Decision 3: início ausente com fim + duração é uma âncora de fim

**Decision**: Quando duração explícita e fim planejado existirem sem início planejado, a projeção obtém o início considerado subtraindo `duração - 1` dias úteis do fim. O início planejado continua nulo.

**Rationale**: essa representação preserva a intenção de prazo e habilita cálculos FF/SF, sem transformar previsão derivada em compromisso persistido.

**Alternatives considered**: usar `OperationalToday` como início ignora o prazo e torna a duração dependente do dia de consulta; preencher o início calculado no registro impediria distinguir intenção de projeção.

## Decision 4: conflito de prazo, duração e precedência é visível

**Decision**: Quando as restrições tornam impossível satisfazer simultaneamente duração explícita e fim planejado, o core mantém os valores planejados imutáveis, calcula o intervalo conforme a política de projeção do projeto e devolve uma violação explicável no item afetado.

**Rationale**: alterar o prazo ou reduzir esforço sem comando do usuário mascara um risco real do cronograma. A violação permite que o usuário escolha entre editar prazo, duração ou relação.

**Alternatives considered**: deslocar automaticamente o fim compromete o prazo informado; comprimir a duração torna a estimativa falsa; rejeitar toda relação impediria planejar cenários que precisam de intervenção.

## Decision 5: um resolvedor único atende os dois cálculos atuais

**Decision**: Introduzir no domínio uma normalização de parâmetros planejados compartilhada por `TaskProjectionCalculator` e `TaskPlan`, em vez de cada motor inferir duração a partir das datas.

**Rationale**: o workspace usa projeção para status/datas e `SchedulingEngine` para folga/criticidade. Ambos precisam da mesma duração e da mesma âncora temporal para continuar determinísticos.

**Alternatives considered**: corrigir apenas o cálculo exibido no Gantt deixaria criticidade e grupos incorretos; duplicar as regras nos dois motores recriaria a divergência atual.
