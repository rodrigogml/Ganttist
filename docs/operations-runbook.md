# Runbook operacional do Ganttist

As regras em `etc/monitoring/ganttist-alerts.yml` usam `GET /api/v1/metrics`. A infraestrutura deve coletar esse endpoint e encaminhar alertas ao responsável operacional definido em `docs/operations-baseline.md`.

## Fila de sincronização

Quando `GanttistSyncQueueDelayed` permanecer ativo por cinco minutos, verificar workers, o estado das operações em `/api/v1/todoist/status` e as respostas do Todoist. Para erros transitórios, manter o retry; para token revogado, orientar a reautorização. Não apagar operações pendentes para silenciar o alerta.

## Falha ou conflito de sincronização

Quando `GanttistSyncFailure` disparar, consultar o histórico/auditoria e a operação afetada. Conflitos exigem reconciliação e novo cálculo sobre o snapshot atual; falhas permanentes requerem correção da causa antes de novo processamento.

## Reautorização do Todoist

Quando `GanttistTodoistReauthorizationRequired` disparar, informar o usuário afetado sem exibir token ou payload sensível. Após reautorização, executar reconciliação antes de declarar o projeto sincronizado.

## Saúde e readiness

`/api/v1/health` indica disponibilidade do processo; `/api/v1/ready` confirma conectividade com banco. O balanceador deve retirar instâncias que respondam `503` em readiness. Logs devem preservar o `X-Request-ID` de resposta para correlação, sem registrar segredos.
