# Research: Tarefas Recorrentes e Soneca

## Decision 1: Recorrência é um domínio separado do scheduling

**Decision**: Criar o domínio `App\Domain\Recurrence` para validar regras, interpretar PT-BR, formatar a regra canônica e calcular a próxima ocorrência. O domínio de scheduling recebe somente a ocorrência corrente concretizada.

**Rationale**: `TaskPlanningNormalizer`, `TaskProjectionCalculator` e `SchedulingEngine` já resolvem duração, calendário, precedência e criticidade de uma tarefa concreta. Misturar série e CPM criaria fim infinito, duplicaria regras de calendário e quebraria a semântica atual de conclusão.

**Alternatives considered**: Armazenar somente texto e recalculá-lo em cada leitura foi rejeitado por ambiguidade e evolução de gramática. Gerar antecipadamente todas as instâncias foi rejeitado porque cria manutenção de séries infinitas e dependências por ocorrência fora do escopo.

## Decision 2: Cursor lógico e agendamento atual permanecem distintos

**Decision**: A tarefa persiste a regra estruturada e a data lógica da ocorrência aberta. `planned_start`, `planned_finish` e duração existentes permanecem o planejamento da ocorrência aberta; uma tabela de histórico guarda snapshots de ocorrências concluídas.

**Rationale**: A soneca deve modificar a execução de hoje sem deslocar a fase de `toda segunda`. Reutilizar o planejamento existente conserva o normalizador e evita duplicar três campos que já têm semântica consolidada.

**Alternatives considered**: Uma coluna `postponedUntil` foi rejeitada porque perde duração e cria dois planejamentos concorrentes. Uma nova tabela para a ocorrência aberta foi rejeitada porque adiciona sincronização desnecessária entre a tarefa e sua instância atual.

## Decision 3: Conclusão é um comando transacional idempotente

**Decision**: O comando de conclusão recebe o cursor esperado e uma chave de comando. Ele bloqueia a tarefa, confirma que o cursor ainda é o esperado, grava exatamente um histórico e avança ou encerra a série na mesma transação. Repetir a mesma chave retorna o resultado original; cursor desatualizado com outra chave retorna conflito recuperável.

**Rationale**: A API atual apenas grava `completed_at` e não distingue retry de uma segunda conclusão intencional. A documentação oficial do Laravel oferece transações e `lockForUpdate`, que serializa a linha selecionada; a garantia de unicidade do histórico protege também contra erro lógico de aplicação. [Laravel Query Builder](https://laravel.com/framework/docs/9.x/queries), [Laravel Connection API](https://api.laravel.com/docs/12.x/Illuminate/Database/ConnectionInterface.html)

**Alternatives considered**: Confiar apenas em debounce de interface foi rejeitado porque não cobre rede, múltiplas abas ou retry. Idempotência somente por data lógica foi rejeitada porque não distingue um comando antigo de uma intenção posterior legítima.

## Decision 4: CPM finito e projeção operacional são escopos distintos

**Decision**: Projetar todas as tarefas abertas para estado, bloqueio e datas consideradas, mas calcular término, folga e caminho crítico apenas sobre tarefas finitas. Dependências de recorrente como predecessora são rejeitadas antes da projeção.

**Rationale**: Uma recorrência contínua não possui término global; removê-la integralmente impediria que recebesse bloqueio como sucessora. Um indicador de participação na rede finita permite preservar ambos os comportamentos sem inferir um horizonte arbitrário.

**Alternatives considered**: Incluir recorrentes até uma janela temporal foi rejeitado por tornar CPM e progresso dependentes de configuração oculta. Tratar uma recorrente como concluída após cada ocorrência foi rejeitado porque reabriria bloqueios de sucessoras e violaria a regra de dependência.

## Decision 5: Parser PT-BR limitado, estruturado e canônico

**Decision**: Implementar parser determinístico para a gramática V1 e formatter canônico, compartilhados entre endpoint de interpretação e persistência. A gramática aceita aliases publicados de dia, semana, mês e ano; listas; ordinais; dia útil; início e fim; intervalos em dias úteis; e intervalos de calendário em semanas, meses e anos. Dias contam somente dias úteis; semanas, meses e anos preservam a data lógica de calendário, aplicam último dia válido do mês e 28 de fevereiro para 29 de fevereiro anual em ano não bissexto, antes da normalização do agendamento.

**Rationale**: O editor visual e a linguagem natural precisam chegar ao mesmo `RecurrenceRule`, e o backend é a autoridade para datas. O conjunto aprovado tem cobertura operacional alta sem exigir NLP aberto.

**Alternatives considered**: Usar serviço de IA ou aceitar texto sem validação foi rejeitado por não ser determinístico. Adicionar uma biblioteca genérica de RFC de calendário foi rejeitado porque não cobre a gramática PT-BR, o calendário de dias úteis do projeto ou as regras de avanço aprovadas.

## Decision 6: Preview é simulação efêmera revalidada na confirmação

**Decision**: A soneca terá comandos distintos de preview e confirmação. A confirmação traz o cursor e um token de preview; o servidor recalcula sobre o estado atual e recusa confirmação quando o snapshot deixou de ser válido.

**Rationale**: O preview precisa usar a mesma projeção do workspace e jamais virar escrita implícita. Recalcular evita aplicar uma cascata vista antes de uma mudança concorrente.

**Alternatives considered**: Aplicar e oferecer undo foi rejeitado porque não satisfaz a exigência de decisão prévia. Confiar no diff fornecido pelo navegador foi rejeitado porque o cliente não é autoridade de calendário, dependências ou criticidade.
