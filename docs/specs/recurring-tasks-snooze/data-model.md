# Data Model: Tarefas Recorrentes e Soneca

## Entity: Tarefa recorrente

A tarefa continua sendo `project_tasks`; as colunas aditivas usam a convenção camelCase mais recente do domínio local.

| Campo físico | Tipo | Restrições | Notas |
|---|---|---|---|
| recurrenceRule | JSON nulo | nulo ou regra V1 válida | Fonte estruturada da frequência, intervalos, seletoras, início, fim e versão. |
| recurrenceCursor | data nula | obrigatório quando `recurrenceRule` é não nulo | Data lógica da única ocorrência aberta. |
| recurrenceVersion | inteiro sem sinal | obrigatório, padrão `0` | Incrementa quando a regra é criada, alterada, removida ou encerrada. |

`planned_start`, `planned_finish` e `plannedDurationWorkdays` continuam a representar o planejamento solicitado para a ocorrência aberta. `completed_at` permanece nulo enquanto a série está ativa; recebe a conclusão definitiva após término ou `Concluir definitivamente`.

## Entity: Histórico de ocorrência

Tabela nova: `projectTaskOccurrence`.

| Campo físico | Tipo | Restrições | Notas |
|---|---|---|---|
| id | ULID | chave primária | Identificador do registro histórico. |
| projectId | ULID | FK obrigatória para projeto, exclusão em cascata | Permite autorização e consulta por projeto sem junção obrigatória. |
| taskId | ULID | FK obrigatória para tarefa, exclusão em cascata | Série à qual a ocorrência pertence. |
| logicalDate | data | obrigatório | Data produzida pela regra antes de calendário, soneca ou dependências. |
| scheduledStart | data nula | — | Planejamento solicitado no momento da conclusão. |
| scheduledFinish | data nula | — | Planejamento solicitado no momento da conclusão. |
| completedAt | data | obrigatório | Data efetiva escolhida ou assumida na conclusão. |
| completedByUserId | ULID nulo | FK para usuário, `SET NULL` na exclusão | Autor da conclusão quando ainda disponível. |
| recurrenceVersion | inteiro sem sinal | obrigatório | Versão da série que produziu o registro. |
| recurrenceRuleSnapshot | JSON | obrigatório | Regra canônica no momento da conclusão; impede reinterpretação do histórico após edição. |
| completionCommandId | UUID | obrigatório, único | Chave de idempotência da conclusão. |
| createdAt | timestamp | obrigatório | Momento do registro. |
| updatedAt | timestamp | obrigatório | Auditoria técnica; igual a `createdAt` enquanto o histórico permanecer imutável. |

Índices: único em `(taskId, logicalDate)`; único em `completionCommandId`; índice de consulta `(projectId, taskId, completedAt)`.

### Relationships

- Projeto 1:N Histórico de ocorrência.
- Tarefa 1:N Histórico de ocorrência.
- Usuário 0:N Histórico de ocorrência concluída.
- Regra ativa 1:1 Ocorrência corrente lógica; ocorrências concluídas não são instâncias abertas adicionais.

### State Transitions

```text
sem recorrência --definir regra--> série ativa / ocorrência aberta
série ativa --soneca--> série ativa / mesma data lógica, novo planejamento
série ativa --concluir--> histórico + próxima ocorrência futura
série ativa --conclusão final ou concluir definitivamente--> tarefa definitivamente concluída
série ativa --remover recorrência--> tarefa não recorrente aberta
```

### Integridade e migração

- A migration é aditiva e não preenche tarefas existentes.
- `recurrenceRule` e `recurrenceCursor` devem estar ambos nulos ou ambos presentes; a aplicação valida a estrutura JSON e a coerência de data.
- Na V1, as FKs de projeto e tarefa usam exclusão em cascata: excluir uma tarefa ou projeto remove definitivamente seus históricos de ocorrência. Não há retenção, anonimização ou arquivamento independente.
- Remover uma regra de recorrência ou concluir definitivamente uma série preserva todos os históricos já registrados; somente a exclusão da tarefa ou do projeto os remove.
- `completionCommandId` é único enquanto o registro histórico existir; a exclusão em cascata também remove essa chave, não existindo garantia de replay após a exclusão da tarefa ou projeto.
- As operações que alteram cursor, planejamento, `completed_at` e histórico devem ocorrer na mesma transação.
