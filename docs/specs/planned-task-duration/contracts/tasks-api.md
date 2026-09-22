# Contracts: Task planning duration API

## Criar tarefa

**Method**: `POST /api/v1/projects/{projectId}/tasks`
**Auth**: membro editor ou proprietário do projeto.

### Request additions

| Campo | Tipo | Obrigatório | Validação |
|---|---|---|---|
| `plannedDurationWorkdays` | integer \| null | não | `null` ou inteiro entre 1 e 3650. |
| `planningDriver` | `start` \| `finish` \| `duration` \| null | condicional | obrigatório sempre que o comando transportar duração explícita junto de início ou fim; ausente preserva comandos legados sem duração explícita. |

`plannedStart` e `plannedFinish` permanecem campos `YYYY-MM-DD` opcionais. O servidor aplica a normalização antes de criar a tarefa.

### Response (201)

O formato de criação continua `{ "data": { "id": "..." } }`. A consulta subsequente ao workspace é a fonte do estado normalizado e das projeções.

## Atualizar tarefa

**Method**: `PUT /api/v1/projects/{projectId}/tasks/{taskId}`
**Auth**: membro editor ou proprietário do projeto.

### Request additions

| Campo | Tipo | Obrigatório | Validação |
|---|---|---|---|
| `plannedDurationWorkdays` | integer \| null | não | `null` remove somente a duração explícita; valores numéricos devem estar entre 1 e 3650; não remove datas. |
| `planningDriver` | `start` \| `finish` \| `duration` \| null | condicional | obrigatório sempre que o comando transportar duração explícita junto de início ou fim; identifica a alteração causal. |

### Normalização observável

| Driver | Pré-condição | Resultado persistido |
|---|---|---|
| `duration` | início presente | fim é calculado a partir do início e da duração. |
| `finish` | início presente | duração é calculada entre início e fim. |
| `finish` | início ausente, duração presente | fim e duração são preservados; início continua nulo. |
| `start` | duração presente | fim é calculado a partir do início e da duração. |

### Error responses

| Status | Código | Descrição |
|---|---|---|
| 422 | `VALIDATION_ERROR` | duração não está entre 1 e 3650, fim é anterior ao início, ou falta `planningDriver` quando duração explícita é enviada com início ou fim. |
| 403 | `FORBIDDEN` | usuário não pode editar o projeto. |
| 404 | `TASK_NOT_FOUND` | tarefa não pertence ao projeto informado. |

## Workspace

**Method**: `GET /api/v1/projects/{projectId}/workspace`

Cada tarefa-folha adiciona os campos abaixo, mantendo os nomes existentes de datas projetadas.

| Campo | Tipo | Descrição |
|---|---|---|
| `plannedDurationWorkdays` | integer \| null | Duração explicitamente persistida, sem inferência pelo cliente. |
| `resolved_duration_workdays` | integer | Duração efetivamente usada pelo core no cálculo atual. |
| `schedule_constraint_state` | `satisfied` \| `violated` | Indica se prazo, duração e restrições podem ser satisfeitos simultaneamente. |
| `schedule_constraint_reason` | string \| null | Explicação curta, própria para interface, da violação quando houver. |

O cliente só envia `plannedDurationWorkdays`; valores `resolved_*`, datas consideradas e estado de restrição são exclusivamente respostas autoritativas do core.
