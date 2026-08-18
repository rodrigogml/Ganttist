# Baseline Operacional do MVP

**Data**: 2026-08-17
**Status**: Default aprovado para implementaÃ§Ã£o; valores de produÃ§Ã£o devem ser revisados pelo responsÃ¡vel operacional antes do release.

## Responsabilidade e ambientes

O time de produto aprova alteraÃ§Ãµes de regra de negÃ³cio; o responsÃ¡vel de infraestrutura aprova deployment, segredos, backup, alertas e restauraÃ§Ã£o. Desenvolvimento, staging e produÃ§Ã£o usam bancos, credenciais OAuth e callbacks separados. Staging nunca usa dados de produÃ§Ã£o.

## Objetivos operacionais

| ParÃ¢metro | Default | Justificativa |
|---|---:|---|
| Disponibilidade mensal | 99,5% | Meta inicial compatÃ­vel com MVP dependente de Todoist, sem esconder degradaÃ§Ã£o externa. |
| RPO | 24 h | Backups sÃ£o responsabilidade da infraestrutura; limite inicial conservador. |
| RTO | 8 h | RecuperaÃ§Ã£o exige infraestrutura, workers e reconciliaÃ§Ã£o posterior. |
| Alerta de fila | item mais antigo > 15 min ou falha permanente | Evita atraso silencioso de sincronizaÃ§Ã£o. |
| Alerta de Todoist | 401/403 imediato; 429/5xx sustentado por 5 min | Token revogado e indisponibilidade exigem reaÃ§Ã£o distinta. |

## Privacidade e retenÃ§Ã£o

- Aplicar LGPD como baseline: minimizaÃ§Ã£o, finalidade, exclusÃ£o de conta e proteÃ§Ã£o de dados pessoais.
- Eventos de auditoria: retenÃ§Ã£o padrÃ£o de 365 dias; limpeza programada e configurÃ¡vel por `AUDIT_RETENTION_DAYS`.
- Backups: retenÃ§Ã£o padrÃ£o de 30 dias, definida e executada pela infraestrutura; o aplicativo apenas documenta componentes a proteger.
- Logs nÃ£o incluem tokens, e-mails em texto claro, tÃ­tulos completos de tarefas ou payloads integrais quando identificadores bastarem.

## Gates de escala

| CenÃ¡rio | CritÃ©rio de aprovaÃ§Ã£o |
|---|---|
| 2.000 tarefas | OperaÃ§Ãµes de scroll, zoom, seleÃ§Ã£o, filtro, drag e relaÃ§Ãµes permanecem utilizÃ¡veis sem travamento perceptÃ­vel em hardware de referÃªncia. |
| 5.000 tarefas | DegradaÃ§Ã£o Ã© controlada, sem perda de dados, bloqueio do navegador ou corrupÃ§Ã£o de seleÃ§Ã£o. |
| Reagendamento | Preview e confirmaÃ§Ã£o permanecem responsivos para cascata de atÃ© 2.000 tarefas; operaÃ§Ãµes maiores sÃ£o processadas em batches com progresso. |

## DecisÃµes futuras

Esses valores sÃ£o defaults configurÃ¡veis, nÃ£o regras de domÃ­nio. Qualquer alteraÃ§Ã£o de retenÃ§Ã£o, RPO/RTO, disponibilidade ou capacidade deve ser auditada e documentada antes do deployment.
