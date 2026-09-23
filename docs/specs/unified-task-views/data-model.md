# Data Model: Busca Unificada e Views de Tarefas

## Entity: View

| Field | Type | Constraints | Notes |
|---|---|---|---|
| id | ULID | PK | Identificador da view privada |
| project_id | ULID | NOT NULL, FK para projeto | Escopo obrigatório da view |
| user_id | ULID | NOT NULL, FK para usuário | Dono exclusivo da view |
| name | string | NOT NULL, 1–100 caracteres, único por usuário e projeto | Nome exibido e usado para detectar colisão na importação |
| query | text | NOT NULL, tamanho limitado | Expressão canônica e portável |
| visual_state | JSON object | NOT NULL, formato versionado | Snapshot das preferências comuns, Tarefas e Gantt |
| format_version | positive integer | NOT NULL | Versão do snapshot e do arquivo exportável |
| created_at | timestamp | NOT NULL | Auditoria de criação |
| updated_at | timestamp | NOT NULL | Auditoria de alteração |

### Relationships

- Projeto 1:N View via `project_id`.
- Usuário 1:N View via `user_id`.
- A combinação `project_id`, `user_id` e `name` é única.

### State Transitions

```text
created -> updated -> deleted
```

Excluir uma view não altera tarefas, pessoas, projeto, query temporária nem outra view.

## Entity: Estado Visual da View

| Field | Type | Constraints | Notes |
|---|---|---|---|
| common | object | Required | Agrupamento, subagrupamento, ordenação, subordenação e estado de hierarquia que se aplicam ao workspace |
| tasks | object | Required, pode estar vazio | Colunas, ordem e demais preferências exclusivas de Tarefas |
| gantt | object | Required, pode estar vazio | Zoom e demais preferências exclusivas de Gantt |
| schema_version | positive integer | Required | Permite migração e importação compatível |

O estado não contém tarefas, pessoas, permissões, dados de projeto ou valores calculados. Preferências desconhecidas em uma versão mais nova são preservadas quando possível e ignoradas com segurança quando não puderem ser aplicadas.

## Entity: Arquivo de View

| Field | Type | Constraints | Notes |
|---|---|---|---|
| kind | literal string | Required: `ganttist-task-view` | Identifica o tipo de arquivo |
| format_version | positive integer | Required | Define o leitor compatível |
| view | object | Required | Nome, query e estado visual da view |

O arquivo não contém `id`, `project_id`, `user_id`, dados de tarefas, dados de pessoas, membros ou permissões de origem. Ao importar, o sistema cria nova identidade local ou atualiza explicitamente a view escolhida pelo usuário.

## Entity: Consulta Recente

| Field | Type | Constraints | Notes |
|---|---|---|---|
| project_id | ULID | Escopo do projeto | Nunca reutilizada em outro projeto sem ação explícita |
| user_id | ULID | Dono do histórico | Isolamento por usuário |
| query | text | Expressão estruturalmente válida | Texto reaplicável, não uma view |
| used_at | timestamp | Ordenação decrescente | Atualizado quando o usuário aplica consulta |

O histórico é limitado por usuário e projeto; entradas duplicadas são consolidadas pela expressão normalizada.
