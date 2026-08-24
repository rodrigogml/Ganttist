# Modelo de Dados Canônico do MVP

## Entidades próprias

| Entidade | Chave | Relações e invariantes |
|---|---|---|
| `todoist_integrations` | ULID / `user_id` único | uma conta por usuário; token criptografado; estado de autorização. |
| `gantt_projects` | ULID / `(user_id,todoist_project_id)` único | um Gantt por projeto Todoist e usuário. |
| `project_settings` | ULID / `gantt_project_id` único | calendário, modo, políticas, `autoScheduleBlockedTasks`, `clearParentTaskDates` e versões otimistas independentes por aba; ambas as autorizações iniciam desligadas. |
| `calendar_exceptions` | ULID / `(gantt_project_id,date)` único | sobrepõe semana padrão. |
| `task_dependencies` | ULID / par+tipo único | intraprojeto, acíclico; grupo apenas predecessor. |
| `task_metadata` | ULID / tarefa+Gantt único | override de conclusão e metadados exclusivos. |
| `recalculations` e itens | ULID / `command_id` único | operação lógica, snapshot, estado e recuperação. |
| `sync_operations` | ULID / `command_id` único | fila idempotente para borda Todoist. |
| `todoist_events` | ULID / `external_event_id` único | deduplicação e reconciliação. |
| `audit_events` | ULID | append-only, origem, sujeito e cadeia causal. |

## Transições de operação

`pending → simulated → applying → applied`; falha temporária: `retryable`; falha definitiva: `failed`; reconciliação pode mover `applying/retryable → applied` ou `failed`. Itens seguem a mesma semântica. Nenhuma transação local permanece aberta durante chamada Todoist.

## Migração

As tabelas existentes são preservadas. Próxima migration acrescentará `version`, `operation_id`, `causation_id`, timestamps de processamento e índices necessários sem remover campos ou metadados existentes; preenchimento ocorre por valores seguros/default e reconciliação posterior.
