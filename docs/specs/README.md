# Catálogo de Features — Ganttist

Este catálogo decompõe a especificação do cliente v1.0 em capacidades independentes. Cada spec descreve **o que** precisa ser entregue; decisões de implementação serão tratadas no planejamento.

| Ordem | Feature | Responsabilidade | Depende de |
|---|---|---|---|
| 1 | [access-todoist](access-todoist/spec.md) | Acesso passwordless, sessões e conexão da conta Todoist | — |
| 2 | [gantt-workspace](gantt-workspace/spec.md) | Escolha de projeto, hierarquia e visualização principal | access-todoist |
| 3 | [calendar-task-dates](calendar-task-dates/spec.md) | Calendário, datas, duração, grupos e estados temporais | gantt-workspace |
| 4 | [dependencies-critical-path](dependencies-critical-path/spec.md) | Precedências, validação de grafo e criticidade | calendar-task-dates |
| 5 | [rescheduling-operations](rescheduling-operations/spec.md) | Edição, simulação, cascata e ciclo de vida de tarefas | 3, 4 |
| 6 | [todoist-synchronization](todoist-synchronization/spec.md) | Sincronização, conflitos, falhas e atualização entre clientes | 1–5 |
| 7 | [gantt-navigation-experience](gantt-navigation-experience/spec.md) | Busca, filtros, navegação, responsividade e acessibilidade | gantt-workspace |
| 8 | [audit-traceability](audit-traceability/spec.md) | Histórico, auditoria e diagnóstico de operações | 1–6 |

As features 1–8 formam o MVP. Baseline, marcos, colaboração, dependências entre projetos, offline-first, exportações e administração ficam fora deste ciclo conforme a especificação do cliente.
