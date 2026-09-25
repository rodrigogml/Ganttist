# Contracts: Recorrência, ocorrência e soneca

Todos os recursos exigem membro do projeto. Datas usam `YYYY-MM-DD`; corpos usam camelCase; valores derivados são produzidos somente pelo servidor.

## Interpretar ou salvar recorrência

### Interpretar expressão

**Method**: `POST /api/v1/projects/{projectId}/tasks/{taskId}/recurrence/interpret`

| Campo | Tipo | Obrigatório | Validação |
|---|---|---|---|
| expression | string | sim | expressão PT-BR dentro do limite publicado. |

**Response (200)**: `canonicalExpression`, `rule`, `firstLogicalDate` e diagnósticos não bloqueantes.

### Criar ou alterar regra

**Method**: `PUT /api/v1/projects/{projectId}/tasks/{taskId}/recurrence`

| Campo | Tipo | Obrigatório | Validação |
|---|---|---|---|
| source | `expression` \| `editor` | sim | define a forma de entrada. |
| expression | string | condicional | obrigatório quando `source=expression`. |
| rule | object | condicional | obrigatório quando `source=editor`; deve satisfazer o schema de regra V1. |
| expectedRecurrenceVersion | integer | não | quando presente, deve corresponder à versão atual. |

**Response (200)**: `taskId`, `recurrence`, `occurrence`, `recurrenceVersion` e `workspaceRevision`.

**Errors**: `422 RECURRENCE_EXPRESSION_INVALID`, `422 RECURRENCE_DEPENDENCY_FORBIDDEN`, `409 RECURRENCE_VERSION_CONFLICT`, `403 FORBIDDEN`, `404 TASK_NOT_FOUND`.

### Remover ou encerrar

**Method**: `DELETE /api/v1/projects/{projectId}/tasks/{taskId}/recurrence` remove a regra e preserva a tarefa aberta; `POST /api/v1/projects/{projectId}/tasks/{taskId}/recurrence/complete-forever` encerra a série e conclui definitivamente a tarefa.

Ambos recebem `expectedRecurrenceVersion` e respondem com a ocorrência/tarefa resultante e `workspaceRevision`.

## Preview e confirmação de soneca

### Preview

**Method**: `POST /api/v1/projects/{projectId}/tasks/{taskId}/occurrence/snooze-preview`

| Campo | Tipo | Obrigatório | Validação |
|---|---|---|---|
| expectedLogicalDate | date | sim | deve ser a ocorrência corrente. |
| option | `nextWorkday` \| `nextWeek` \| `workdays` \| `date` | sim | opção de soneca. |
| workdays | integer | condicional | apenas 3 ou 7 quando `option=workdays`. |
| targetDate | date | condicional | obrigatório quando `option=date`. |

**Response (200)**: `normalizedTargetDate`, `occurrenceBefore`, `occurrenceAfter`, `impact`, `previewToken` e `workspaceRevision`. `impact` contém tarefas afetadas, alteração de término finito quando aplicável, mudanças de criticidade e violações novas, cada uma com severidade `informational`, `warning` ou `critical`.

### Confirmar

**Method**: `POST /api/v1/projects/{projectId}/tasks/{taskId}/occurrence/snooze`

Repete a intenção de preview e acrescenta `previewToken`. O servidor revalida cursor e estado. A resposta inclui a ocorrência atualizada, impacto aplicado e `workspaceRevision`.

**Errors**: `409 OCCURRENCE_CONFLICT` para cursor ou preview obsoleto; `422 SNOOZE_INVALID`; `403 FORBIDDEN`; `404 TASK_NOT_FOUND`.

## Concluir ocorrência e consultar histórico

### Concluir

**Method**: `POST /api/v1/projects/{projectId}/tasks/{taskId}/occurrence/complete`

| Campo | Tipo | Obrigatório | Validação |
|---|---|---|---|
| expectedLogicalDate | date | sim | cursor atual da tarefa. |
| completionCommandId | UUID | sim | chave de idempotência por tentativa de conclusão. |
| actualCompletionDate | date | não | padrão é a data local atual. |

**Response (200)**: `taskId`, `completedOccurrence`, `nextOccurrence` nula ou preenchida, `taskCompleted`, `idempotent` e `workspaceRevision`.

**Errors**: `409 OCCURRENCE_CONFLICT` quando o cursor já foi avançado por outro comando; a repetição da mesma chave recebe a resposta original; `422 COMPLETION_DATE_INVALID`; `403 FORBIDDEN`; `404 TASK_NOT_FOUND`.

### Histórico

**Method**: `GET /api/v1/projects/{projectId}/tasks/{taskId}/occurrences?cursor={opaque}&limit={1..100}`

**Response (200)**: página ordenada da conclusão mais recente para a mais antiga, com `logicalDate`, `scheduledStart`, `scheduledFinish`, `completedAt`, `canonicalExpressionAtCompletion` e cursor seguinte.

## Workspace

Cada tarefa acrescenta:

| Campo | Tipo | Descrição |
|---|---|---|
| recurrence | object \| null | regra canônica, expressão legível, versão e término. |
| occurrence | object \| null | data lógica, planejamento solicitado, datas consideradas, estado e indicação de soneca. |
| participatesInFiniteNetwork | boolean | indica se compõe indicadores finitos globais. |
| occurrenceHistoryCount | integer | total de conclusões registradas. |

`stats` separa métricas finitas do projeto e contagens operacionais de ocorrências, sem alterar retroativamente a interpretação de projetos sem recorrências:

```json
{
  "finite": {
    "totalTasks": 18,
    "completedTasks": 12,
    "progressPercent": 67,
    "projectFinish": "2026-10-20",
    "criticalTaskCount": 4
  },
  "operational": {
    "recurringTaskCount": 3,
    "openRecurringOccurrenceCount": 3,
    "overdueRecurringOccurrenceCount": 1,
    "snoozedRecurringOccurrenceCount": 1
  }
}
```

Todos os campos de contagem são inteiros não negativos. `projectFinish` é `date` ou `null`; nos projetos sem trabalho finito elegível, é `null`. `finite` exclui tarefas recorrentes; `operational` mede somente as ocorrências correntes. Projetos sem recorrência retornam todas as contagens operacionais como `0`, nunca `null`.
