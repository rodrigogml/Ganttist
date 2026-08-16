# Matriz de rastreabilidade inicial

| Requisito crítico | Implementação | Evidência automatizada | Estado |
|---|---|---|---|
| Datas inteiras e duração útil | `WorkCalendar`, `TaskPlan` | `WorkCalendarTest` | entregue |
| OperationalToday | `WorkCalendar::operationalToday` | teste domingo → segunda | entregue |
| FS/SS/FF/SF e preservação | `SchedulingEngine` | cascata FS e múltiplas restrições | parcial; ampliar golden cases |
| Ciclos/autodependência/duplicidade | `Dependency`, topological sort, constraints | cycle test | entregue no core |
| Tarefa concluída imóvel | `SchedulingEngine` | completed task test | entregue |
| Tarefa sem data e início virtual | `SchedulingEngine` | unscheduled virtual test | entregue |
| Calendário por Gantt | migrations + `WorkCalendar` | exception tests + MySQL migration | entregue |
| Modelo de dados próprio | migration `create_ganttist_domain` | migration MySQL 9.7 executada | entregue |
| API versionada | `routes/api.php` e OpenAPI | `WorkspaceApiTest` | entregue |
| Gantt próprio/SVG/responsivo | Vue SPA | build Vite | vertical slice |
| Passwordless | `AuthController`, `login_challenges` | teste a ampliar | vertical slice |
| OAuth/webhook | controllers, adapter e schema | webhook depende de secret real | contrato; homologação pendente |
| Fila/auditoria/reagendamento | migrations | migration MySQL | schema; orquestração pendente |
| 2.000/5.000 tarefas | arquitetura do renderer | benchmark pendente | pendente |
