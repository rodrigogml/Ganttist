# Modelo de dados — dependencias de secao

## project_schedule_dependencies

Cada relacao tem predecessor e sucessor tipados (`task` ou `section`) no mesmo projeto. Os IDs existentes de tarefas foram migrados como `task -> task`; a tabela anterior permanece apenas para rollback do ciclo.

| Campo | Regra |
|---|---|
| project_id | Projeto das duas pontas. |
| predecessor_kind / predecessor_id | Origem tipada da relacao. |
| successor_kind / successor_id | Destino tipado da relacao. |
| type | FS, SS, FF ou SF. |

Integridade de tipo, projeto, hierarquia, duplicidade e ciclo pertence ao servico de dominio, pois uma FK polimorfica nao pode garantir as duas tabelas de ponta.

## Janela derivada de secao

Uma secao nao persiste datas de planejamento. Seu inicio e fim sao derivados das projecoes de suas tarefas descendentes. Limites recebidos de inicio sao herdados pelas tarefas; limites de fim movem somente a tarefa pendente que controla o fim derivado. Conclusoes reais nunca sao reescritas.
