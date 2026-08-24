# Aceite do MVP â€” evidÃªncia de execuÃ§Ã£o

**Data da Ãºltima execuÃ§Ã£o:** 2026-08-18
**Escopo:** critÃ©rios de aceite da especificaÃ§Ã£o do cliente Â§Â§18N e 32.
**Ambiente local:** PHP 8.3.7, MySQL (`granttist`), frontend Vite/Vitest.

## EvidÃªncias automatizadas executadas

| CritÃ©rio | EvidÃªncia | Resultado |
|---|---|---|
| CalendÃ¡rio, dias Ãºteis, tarefas sem data, grupos e conclusÃ£o efetiva | suÃ­te de domÃ­nio e golden tests | aprovado |
| PrecedÃªncias FS/SS/FF/SF, ciclos, duplicidade, criticidade e auditoria de escrita | testes de `SchedulingEngine` e API de dependÃªncias | aprovado |
| Isolamento de usuÃ¡rio/projeto, sessÃ£o, OAuth simulado e revogaÃ§Ã£o Todoist auditÃ¡vel | testes de acesso, OAuth, seleÃ§Ã£o de projeto e workspace | aprovado |
| OperaÃ§Ãµes idempotentes, retry, conflito, auditoria e continuidade de exclusÃ£o | testes de operaÃ§Ã£o, `TaskApiTest`, `AuditApiTest` | aprovado |
| Contrato Todoist de leitura e escrita | testes do adapter HTTP e fake determinÃ­stico | aprovado contra mock HTTP |
| ProjeÃ§Ã£o workspace e contrato APIâ€“SPA | `WorkspaceApiTest`, store contract e `App.test.ts` | aprovado |
| Ghosts, confirmaÃ§Ã£o e reconciliaÃ§Ã£o sem reload | `App.test.ts` e `CalendarPanel.test.ts` | aprovado |
| SaÃºde, readiness, mÃ©tricas e correlaÃ§Ã£o de requisiÃ§Ã£o | `ObservabilityApiTest` | aprovado |
| ConfiguraÃ§Ã£o obrigatÃ³ria de produÃ§Ã£o | `app:production-readiness` | aprovado em cenÃ¡rio configurado; bloqueia valores locais inseguros |
| Hardening HTTP de base | middleware de cabeÃ§alhos de seguranÃ§a | aprovado por teste de resposta global |
| OtimizaÃ§Ã£o de rotas para produÃ§Ã£o | `route:cache` | aprovado e limpo pelo teste automatizado |
| Schema de produÃ§Ã£o | `artisan migrate:status` no MySQL local | todas as migrations aplicadas |
| RegressÃ£o geral | PHPUnit | 72 testes / 301 asserÃ§Ãµes aprovadas |
| RegressÃ£o frontend | Vitest, `vue-tsc`, build Vite | 13 testes, tipos e build aprovados |
| Escala algorÃ­tmica local | amostragem de scroll da janela virtual em 2k/5k | aprovado; hardware real pendente |
| Benchmark no staging | Chromium headless em `/benchmark?size=2000` e `?size=5000` | aprovado; 32 nós no DOM em ambos os cenários |
| Health/readiness/métricas no staging | `GET /api/v1/health`, `/api/v1/ready`, `/api/v1/metrics` | aprovado; HTTP 200, banco/fila disponíveis, métricas sem pendências |

## Gates que requerem ambiente externo

| Gate | Estado | EvidÃªncia necessÃ¡ria antes do release |
|---|---|---|
| OAuth e escrita Todoist reais | pendente | conta de homologaÃ§Ã£o, credenciais, callback HTTPS e registro de resultado |
| Webhook pÃºblico e replay | pendente | endpoint HTTPS pÃºblico, segredo e entrega real assinada |
| E-mail passwordless | pendente | SMTP de homologaÃ§Ã£o e caixa de teste |
| SSE em mÃºltiplas abas | pendente | duas sessÃµes autenticadas em ambiente com conexÃ£o longa |
| Escala 2k/5k e dispositivos | parcial | benchmark Chromium registrado; matriz manual de dispositivos ainda pendente |
| Workers, scheduler, alertas, backup e restauraÃ§Ã£o | pendente | infraestrutura de staging, execuÃ§Ã£o monitorada e runbook aprovado |
| ExploraÃ§Ã£o visual/interativa | pendente | rodada manual registrada por responsÃ¡vel de produto/QA |

## DecisÃ£o de release

O cÃ³digo estÃ¡ **aprovado para homologaÃ§Ã£o** pelos gates automatizados locais. NÃ£o estÃ¡ aprovado para produÃ§Ã£o enquanto algum gate externo desta matriz permanecer pendente. As pendÃªncias tambÃ©m estÃ£o mantidas em `docs/pending.md` para nÃ£o serem mascaradas por fixtures ou mocks.
