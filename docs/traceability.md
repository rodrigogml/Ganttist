# Matriz de rastreabilidade inicial

| Requisito crítico | Implementação | Evidência automatizada | Estado |
|---|---|---|---|
| Datas inteiras e duração útil | `WorkCalendar`, `TaskPlan` | `WorkCalendarTest` | entregue |
| OperationalToday | `WorkCalendar::operationalToday` | teste domingo → segunda | entregue |
| FS/SS/FF/SF, grupos, conclusão e preservação | `SchedulingEngine`, `GroupScheduleCalculator` | golden tests de FS/SS/FF/SF, folga e conclusão efetiva | entregue no core |
| Ciclos/autodependência/duplicidade | `Dependency`, topological sort, constraints | cycle test | entregue no core |
| Tarefa concluída imóvel | `SchedulingEngine` | completed task test | entregue |
| Tarefa sem data e início virtual | `SchedulingEngine` | unscheduled virtual test | entregue |
| Calendário por Gantt | migrations + `WorkCalendar` | exception tests + MySQL migration | entregue |
| Modelo de dados próprio | migration `create_ganttist_domain` | migration MySQL 9.7 executada | entregue |
| API versionada e fronteira SPA | `routes/api.php`, `WorkspaceController::fromTodoist`, `workspace-contract.ts` | `WorkspaceApiTest`, Vitest de contrato do store | entregue |
| Gantt próprio/SVG/responsivo | Vue SPA | build Vite | vertical slice |
| Passwordless | `AuthController`, `login_challenges` | teste a ampliar | vertical slice |
| OAuth/webhook | controllers, adapter e schema | webhook depende de secret real | contrato; homologação pendente |
| Fila/auditoria/reagendamento | operações idempotentes, jobs, `AuditWriter` | testes de operação, retry, conflito e auditoria | entregue em ambiente local; operação de produção pendente |
| 2.000/5.000 tarefas | renderer virtual e `/benchmark` protegido por feature flag | medição manual por navegador/dispositivo pendente | ferramenta entregue; gate de hardware pendente |
