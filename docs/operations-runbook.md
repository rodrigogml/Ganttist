# Runbook operacional do Ganttist

As regras em `etc/monitoring/ganttist-alerts.yml` usam `GET /api/v1/metrics`. A infraestrutura deve coletar esse endpoint e encaminhar alertas ao responsável operacional definido em `docs/operations-baseline.md`.

## Fila de sincronização

O worker persistente é instalado a partir de `etc/systemd/ganttist-queue.service`, executa como `www-data` e prioriza as filas `sync,planning`. Verificar com `systemctl status ganttist-queue` e reiniciar após mudanças de código que alterem jobs.

Quando `GanttistSyncQueueDelayed` permanecer ativo por cinco minutos, verificar workers, o estado das operações em `/api/v1/todoist/status` e as respostas do Todoist. Para erros transitórios, manter o retry; para token revogado, orientar a reautorização. Não apagar operações pendentes para silenciar o alerta.

O fallback de reconciliação é instalado a partir de `etc/cron/ganttist` e roda a cada minuto com trava exclusiva. Snapshots idênticos não publicam novos eventos; mudanças detectadas geram auditoria e atualização SSE para as abas conectadas.

## Falha ou conflito de sincronização

Quando `GanttistSyncFailure` disparar, consultar o histórico/auditoria e a operação afetada. Conflitos exigem reconciliação e novo cálculo sobre o snapshot atual; falhas permanentes requerem correção da causa antes de novo processamento.

## Reautorização do Todoist

Quando `GanttistTodoistReauthorizationRequired` disparar, informar o usuário afetado sem exibir token ou payload sensível. Após reautorização, executar reconciliação antes de declarar o projeto sincronizado.

## Saúde e readiness

`/api/v1/health` indica disponibilidade do processo; `/api/v1/ready` confirma conectividade com banco. O balanceador deve retirar instâncias que respondam `503` em readiness. Logs devem preservar o `X-Request-ID` de resposta para correlação, sem registrar segredos.

## Sessão do navegador

O cookie de sessão é temporário e deve desaparecer quando a janela do navegador for fechada (`SESSION_EXPIRE_ON_CLOSE=true`). A sessão server-side permanece válida por até 120 minutos sem atividade (`SESSION_LIFETIME=120`); atividades válidas renovam esse limite conforme a política do Laravel. A opção explícita de lembrar o login pode criar um recaller persistente.

Quando uma API autenticada responder `401` ou `419`, a SPA limpa o usuário, encerra o fluxo de eventos e exibe novamente a tela de login. Não tratar esse caso como falha de sincronização ou manter a projeção como se estivesse atualizada.
