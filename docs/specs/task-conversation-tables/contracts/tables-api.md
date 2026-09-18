# Contracts: Tabelas na conversa da tarefa

Todos os endpoints requerem usuário autenticado e participação no projeto. Campos JSON seguem `snake_case`, preservando o contrato atual da conversa; a persistência mapeia seus nomes físicos para esse DTO.

## Tabelas no contexto de tarefa

**Method**: GET `/api/v1/projects/{projectId}/tasks/{taskId}/context`

Além de `comments` e `collaborators`, a resposta acrescenta `tables`.

| Field | Type | Description |
|---|---|---|
| tables[].id | string | Identidade da tabela. |
| tables[].document | object | Documento estruturado publicado. |
| tables[].document_version | integer | Versão salva. |
| tables[].author_id / author_name | string | Publicador exibido. |
| tables[].posted_at | ISO timestamp | Data de publicação. |
| tables[].editable | boolean | Permissão global de escrita do solicitante. |

## Publicar tabela

**Method**: POST `/api/v1/projects/{projectId}/tasks/{taskId}/tables`

**Auth**: proprietário ou editor.

### Request

| Field | Type | Required | Validation |
|---|---|---|---|
| document | object | yes | Uma aba; máximo 200 linhas, 100 colunas e 500 KB serializados. |

### Response (201)

Retorna o bloco de tabela completo no mesmo formato de `tables[]`.

## Adquirir reserva de edição

**Method**: POST `/api/v1/projects/{projectId}/tasks/{taskId}/tables/{tableId}/edit-lock`

**Auth**: proprietário ou editor.

### Response (200)

| Field | Type | Description |
|---|---|---|
| lock_token | string | Capacidade opaca para aquela sessão de edição. |
| expires_at | ISO timestamp | Vencimento da reserva, 10 segundos após a concessão. |

### Error Responses

| Status | Code | Description |
|---|---|---|
| 403 | WRITE_FORBIDDEN | Papel sem permissão de escrita. |
| 404 | TABLE_NOT_FOUND | Tabela não pertence à tarefa/projeto. |
| 409 | TABLE_LOCKED | Reserva ativa de outro participante. |

## Renovar ou liberar reserva

**Method**: PUT / DELETE `/api/v1/projects/{projectId}/tasks/{taskId}/tables/{tableId}/edit-lock`

### Request for PUT / DELETE

| Field | Type | Required | Validation |
|---|---|---|---|
| lock_token | string | yes | Deve ser a capacidade vigente da tabela e do usuário. |

PUT retorna `lock_token` e novo `expires_at`. Se expirou mas ninguém adquiriu a reserva, a mesma capacidade é renovada. Se outra pessoa a adquiriu, retorna `409 TABLE_LOCK_LOST`.

DELETE retorna `204` e torna a tabela imediatamente disponível. Uma capacidade inválida ou perdida retorna `409 TABLE_LOCK_LOST`.

## Salvar tabela publicada

**Method**: PUT `/api/v1/projects/{projectId}/tasks/{taskId}/tables/{tableId}`

**Auth**: proprietário ou editor com reserva válida.

### Request

| Field | Type | Required | Validation |
|---|---|---|---|
| document | object | yes | Mesmos limites da publicação. |
| lock_token | string | yes | Capacidade vigente, não expirada e pertencente ao solicitante. |

### Response (200)

Retorna o bloco de tabela atualizado, com `document_version` incrementada.

### Error Responses

| Status | Code | Description |
|---|---|---|
| 409 | TABLE_LOCK_LOST | A tabela foi adquirida por outra pessoa ou a reserva não está mais válida. |
| 422 | TABLE_DOCUMENT_INVALID | Documento, limites ou estrutura inválidos. |

## Excluir tabela publicada

**Method**: DELETE `/api/v1/projects/{projectId}/tasks/{taskId}/tables/{tableId}`

**Auth**: proprietário ou editor com reserva válida.

Aceita `lock_token` e retorna `204`; usa os mesmos erros de reserva do salvamento.
