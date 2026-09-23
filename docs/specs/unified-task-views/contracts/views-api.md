# Contracts: Busca Unificada e Views de Tarefas

Todos os endpoints exigem sessão autenticada e acesso de consulta ao projeto. Os campos JSON usam `camelCase`; datas e timestamps seguem as convenções já expostas pela API do projeto.

## View representation

| Field | Type | Description |
|---|---|---|
| id | string | Identificador local da view |
| name | string | Nome privado no projeto |
| query | string | Expressão de busca canônica |
| visualState | object | Snapshot visual versionado |
| formatVersion | integer | Versão do formato |
| createdAt | string | Momento de criação |
| updatedAt | string | Momento da última alteração |

## List views

**Method**: `GET /api/v1/projects/{projectId}/views`

**Auth**: Obrigatória; acesso de consulta ao projeto.

### Response (200)

| Field | Type | Description |
|---|---|---|
| data | array of View representation | Views pertencentes somente ao usuário autenticado naquele projeto; na primeira listagem, inclui a criação sob demanda das duas views iniciais editáveis |

## Create view

**Method**: `POST /api/v1/projects/{projectId}/views`

**Auth**: Obrigatória; acesso de consulta ao projeto.

### Request

| Field | Type | Required | Validation |
|---|---|---|---|
| name | string | yes | Nome não vazio, máximo definido pela feature, único para usuário e projeto |
| query | string | yes | Sintaxe de query válida; valores desconhecidos permitidos |
| visualState | object | yes | Formato e versão de estado reconhecidos |
| formatVersion | integer | yes | Versão suportada |

### Response (201)

| Field | Type | Description |
|---|---|---|
| data | View representation | View criada |

## Update or overwrite view

**Method**: `PUT /api/v1/projects/{projectId}/views/{viewId}`

**Auth**: Obrigatória; acesso de consulta e propriedade da view.

### Request

Mesmo formato de criação. A interface solicita confirmação antes desta chamada quando a operação significa sobrescrever uma view existente.

### Response (200)

| Field | Type | Description |
|---|---|---|
| data | View representation | View atualizada |

## Delete view

**Method**: `DELETE /api/v1/projects/{projectId}/views/{viewId}`

**Auth**: Obrigatória; acesso de consulta e propriedade da view.

### Response (204)

Sem corpo.

## Import view

**Method**: `POST /api/v1/projects/{projectId}/views/import`

**Auth**: Obrigatória; acesso de consulta ao projeto.

### Request

| Field | Type | Required | Validation |
|---|---|---|---|
| file | object | yes | Arquivo de view estruturado e versão suportada |
| conflictStrategy | enum | yes when name collides | `create`, `overwrite` ou `cancel` |
| targetViewId | string | only for overwrite | View do usuário autenticado no projeto atual |

### Response (200 or 201)

| Field | Type | Description |
|---|---|---|
| data | View representation | View criada ou atualizada |
| warnings | array of string | Referências textuais não resolvidas, quando detectáveis |

### Error Responses

| Status | Code | Description |
|---|---|---|
| 403 | PROJECT_ACCESS_DENIED | Usuário não tem acesso ao projeto ou tenta alterar view alheia |
| 404 | VIEW_NOT_FOUND | View de destino não pertence ao usuário no projeto |
| 409 | VIEW_NAME_CONFLICT | Nome já existe e ainda não há estratégia de conflito escolhida |
| 422 | INVALID_VIEW_FILE | Estrutura, versão ou query estruturalmente inválida |
