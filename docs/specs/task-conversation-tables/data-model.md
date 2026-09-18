# Data Model: Tabelas na conversa da tarefa

## Entity: Task Table

| Field | Type | Constraints | Notes |
|---|---|---|---|
| id | ULID | primary key | Identidade do bloco de tabela. |
| projectId | ULID | required; pertence ao projeto | Mantém isolamento por projeto. |
| taskId | ULID | required; pertence à tarefa do mesmo projeto | Destino da conversa. |
| publishedByUserId | ULID | required | Pessoa exibida como publicadora; não limita edição posterior. |
| document | JSON | required; máximo 500 KB serializados | Documento de uma aba, incluindo valores, fórmulas, estilos, dimensões e mesclagens. |
| documentVersion | integer | required; inicia em 1 | Incrementa a cada salvamento; suporte a diagnóstico e contrato. |
| editLockUserId | ULID | nullable | Titular atual ou último titular da reserva. |
| editLockTokenHash | string | nullable | Hash de token opaco emitido na aquisição; nunca exposto em leitura. |
| editLockExpiresAt | timestamp | nullable | Após este instante, outro participante pode adquirir a reserva. |
| createdAt | timestamp | required | Data exibida na conversa. |
| updatedAt | timestamp | required | Último salvamento do documento. |

### Relationships

- Projeto 1:N Tabela de tarefa.
- Tarefa 1:N Tabela de tarefa.
- Usuário 1:N Tabela de tarefa publicada.
- Usuário 1:N Reserva de edição atual de tabela.

### State Transitions

```text
new document -> published/unlocked
published/unlocked -> locked(active)
locked(active) -> locked(renewed)
locked(active or expired) -> unlocked     [release]
locked(expired) -> locked(active)         [same holder renews, if not acquired]
locked(expired) -> locked(active)         [another writer acquires]
locked(active) -> published/unlocked      [save then release]
```

## Entity: Edit Lock Capability

| Field | Type | Constraints | Notes |
|---|---|---|---|
| token | opaque string | returned only on acquire; scoped to one table and user | Capacidade apresentada para renovar, salvar ou liberar. |
| expiresAt | timestamp | 10 seconds after acquire or renew | Informação de interface; servidor permanece fonte da verdade. |

Não é persistida como entidade independente: é a representação de resposta dos campos de reserva da tabela.

## Conversation Read Model

O contexto de tarefa retorna comentários existentes e tabelas. A SPA os normaliza para um bloco cronológico com `kind = comment | table`, usando `createdAt` como ordenação e `id` como desempate estável. O contador de comentários da tarefa passa a considerar ambos os tipos.
