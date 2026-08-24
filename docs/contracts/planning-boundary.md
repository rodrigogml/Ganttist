# Contrato de Fronteira de Planejamento

## Autoridade

| Dado | Fonte de verdade | Cliente pode enviar |
|---|---|---|
| Tarefa, hierarquia, data, deadline, conclusão | Todoist reconciliado | intenção de alteração somente |
| Calendário e configurações | banco próprio por Gantt | alteração de configuração versionada |
| Dependências, operação e auditoria | banco próprio + core | comando identificado, nunca grafo completo autorizado |
| Duração, grupos, criticidade, cascata e ghosts | core | nunca como valor autoritativo |

## Convenções

- Banco: `snake_case`; API/SPA: `camelCase`; datas civis: `YYYY-MM-DD`.
- Todo comando de escrita contém `commandId` ULID idempotente e `expectedVersion` quando altera configuração/estado versionado.
- Responses usam `{ data, meta }`; erros incluem `message`, `code`, `errors?` e `operationId?`.

## Workspace

`GET /api/v1/workspace` devolve projeto, calendário, tarefas projetadas, dependências, stats e `meta.version`. Tarefa inclui `planned`, `derived`, `virtualStart?`, `syncStatus`, `critical` e `totalFloat`; grupos são `derived=true` e não editáveis.

## Comandos

| Comando | Entrada autorizada | Saída |
|---|---|---|
| Simular | `commandId`, `intent`, `expectedVersion` | operação/snapshot/ghosts calculados |
| Aplicar | `operationId`, `expectedVersion` | estado de operação e itens |
| Dependência | IDs de pontas, tipo, `commandId` | dependência validada/projeção |
| Calendário | patch de configurações, `expectedVersion`, `commandId` | configuração versionada/operação quando houver impacto |
| Automação | `autoScheduleBlockedTasks`, `clearParentTaskDates`, `expectedVersion`, `commandId` | configuração versionada; aplicação assíncrona auditada; pais nunca recebem datas derivadas |

O backend busca tarefas, duração e dependências persistidas antes de calcular. Payload que tentar substituir esses dados é inválido.

## Eventos

Eventos de workspace contêm `eventId`, `projectId`, `version`, `type`, `occurredAt` e referência de operação; o cliente reconcilia via workspace após perda/reconexão.
