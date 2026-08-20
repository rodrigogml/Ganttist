# Tarefas Ganttist - MVP alinhado Ã  especificaÃ§Ã£o v1.0

Escopo: corrigir, substituir e completar o vertical slice existente atÃ© cobrir as oito specs aprovadas, sem incluir funcionalidades fora do MVP.

**Legenda de status:** `[ ]` Pendente Â· `[~]` Em andamento Â· `[x]` ConcluÃ­do Â· `[!]` Bloqueado
**Criticidade:** `[C]` seguranÃ§a/operaÃ§Ã£o bloqueante Â· `[A]` funcionalidade essencial Â· `[M]` necessÃ¡ria sem bloqueio imediato.

## FASE 1 - DecisÃµes e fundaÃ§Ãµes

### 1.1 Fechar parÃ¢metros operacionais configurÃ¡veis `[C]`

Ref: `docs/checklists/mvp-quality-gate.md` CHK014â€“015, CHK018

- [x] 1.1.1 Registrar responsÃ¡veis, ambiente e valores aprovados de SLO/RPO/RTO ou marcar configuraÃ§Ã£o pendente de deployment. <!-- docs/operations-baseline.md -->
- [x] 1.1.2 Definir polÃ­tica de compliance/retenÃ§Ã£o e mapeÃ¡-la a auditoria, exclusÃ£o de conta e backups. <!-- docs/operations-baseline.md -->
- [x] 1.1.3 Definir metas de performance percebida e capacidade para o gate 2k/5k. <!-- docs/operations-baseline.md -->

### 1.2 Consolidar contratos e modelo autorizado `[A]`

Ref: ConstituiÃ§Ã£o Iâ€“III; auditoria Â§Achados transversais

- [x] 1.2.1 Criar contratos versionados de workspace, comandos, operaÃ§Ã£o e eventos. <!-- docs/contracts/planning-boundary.md -->
- [x] 1.2.2 Criar schemas/mapper de boundary e testes de paridade APIâ€“SPA. <!-- `WorkspaceController::fromTodoist` mapeia a borda; `workspace-contract.ts` valida a resposta antes do store e Vitest cobre preservaÃ§Ã£o da projeÃ§Ã£o em contrato invÃ¡lido -->
- [x] 1.2.3 Documentar modelo de dados final e migrations de transiÃ§Ã£o sem perda de metadados. <!-- docs/data-model.md -->

## FASE 2 - DomÃ­nio determinÃ­stico e grafo

### 2.1 Completar calendÃ¡rio, tarefas e grupos `[A]`

Ref: `calendar-task-dates/spec.md` FR-001â€“008; INT-CALENDAR-001/002

- [x] 2.1.1 Carregar calendÃ¡rio/configuraÃ§Ãµes reais por Gantt no core. <!-- ProjectCalendarService usado por simulaÃ§Ã£o e aplicaÃ§Ã£o -->
- [x] 2.1.2 Corrigir semÃ¢ntica de tarefa sem data, data virtual, conclusÃ£o e deadline nÃ£o Ãºtil. <!-- core e projeÃ§Ã£o cobertos por testes -->
- [x] 2.1.3 Implementar derivaÃ§Ã£o bottom-up de grupos e golden tests de calendÃ¡rio/grupos. <!-- GroupScheduleCalculator + testes unitÃ¡rios e integraÃ§Ã£o de snapshot -->

### 2.2 Completar precedÃªncia e criticidade `[A]`

Ref: `dependencies-critical-path/spec.md` FR-001â€“008; INT-DEPS-001/002

- [x] 2.2.1 Centralizar validaÃ§Ã£o de escopo, grupo predecessor, duplicidade e ciclo. <!-- DependencyScopeValidator + testes de API -->
- [x] 2.2.2 Implementar forward/backward pass completo, folga e criticidade para FS/SS/FF/SF. <!-- golden tests FS/SS/FF/SF, folga paralela e conclusÃ£o efetiva -->
- [x] 2.2.3 Criar golden tests para mÃºltiplas predecessoras, grupos, conclusÃ£o e filtros ocultos. <!-- golden tests do core + relaÃ§Ã£o oculta revelÃ¡vel no store -->

## FASE 3 - OperaÃ§Ãµes, persistÃªncia e Todoist

### 3.1 Modelar operaÃ§Ã£o lÃ³gica e cascata recuperÃ¡vel `[C]`

Ref: `rescheduling-operations/spec.md` FR-001â€“008; INT-OPS-001/002

- [x] 3.1.1 Persistir comando, snapshot, itens, estados, idempotÃªncia e cadeia causal. <!-- snapshots por item, sequÃªncia, commandId e audit_events causal -->
- [x] 3.1.2 Implementar simulaÃ§Ã£o/revalidaÃ§Ã£o/batches sem transaÃ§Ã£o local aguardando Todoist. <!-- intenÃ§Ã£o autorizada, revalidaÃ§Ã£o de snapshot e execuÃ§Ã£o sequencial por camada -->
- [x] 3.1.3 Implementar retries, falha parcial, reconciliaÃ§Ã£o e testes de operaÃ§Ã£o composta. <!-- ProcessRecalculation, backoff e testes de interrupÃ§Ã£o/retomada -->

### 3.2 Completar adapter e reconciliaÃ§Ã£o Todoist `[C]`

Ref: `todoist-synchronization/spec.md` FR-001â€“008; INT-SYNC-001/002

- [x] 3.2.1 Expandir contract tests do adapter e tratamento de token revogado/rate limit. <!-- contract de escrita autenticada; 401/403 exige reautorizaÃ§Ã£o e 429 preserva evento pendente/degradado -->
- [x] 3.2.2 Processar webhook/eventos idempotentes em fila e reconciliar snapshots incrementais. <!-- HMAC, deduplicaÃ§Ã£o antes da fila, escopo por projeto e reconciliaÃ§Ã£o restaurÃ¡vel -->
- [x] 3.2.3 Publicar eventos de workspace, detectar conflito e testar mÃºltiplos clientes/ordem/eco. <!-- feed autorizado e ordenado por audit event, causationId e operaÃ§Ã£o em conflito -->

### 3.3 Completar acesso e isolamento `[C]`

Ref: `access-todoist/spec.md` FR-001â€“008; INT-ACCESS-001â€“003

- [x] 3.3.1 Implementar gestÃ£o/revogaÃ§Ã£o de sessÃµes e exclusÃ£o de conta. <!-- API isolada e painel acessÃ­vel da conta -->
- [x] 3.3.2 Implementar rotaÃ§Ã£o de chaves/tokens e hardening de OAuth/webhook. <!-- OAuth state atÃ´mico, rotaÃ§Ã£o registrada, HMAC e validaÃ§Ã£o de payload -->
- [x] 3.3.3 Criar testes de isolamento, enumeraÃ§Ã£o, expiraÃ§Ã£o e homologaÃ§Ã£o externa controlada. <!-- suites passwordless, OAuth HTTP fake e webhooks -->

## FASE 4 - ProjeÃ§Ã£o e interfaces

### 4.1 Entregar projeÃ§Ã£o autorizada do workspace `[A]`

Ref: `gantt-workspace/spec.md` FR-001â€“007; INT-WORKSPACE-001

- [x] 4.1.1 Substituir projeÃ§Ã£o simplificada por Ã¡rvore, grupos, estados e calendÃ¡rio calculados. <!-- contrato enriquecido por snapshot real/calendÃ¡rio/engine -->
- [x] 4.1.2 Integrar DTO autorizado no store e estados loading/vazio/stale/erro. <!-- store e SPA preservam leitura anterior em falha -->
- [x] 4.1.3 Criar integraÃ§Ã£o/E2E de seleÃ§Ã£o, hierarquia e atualizaÃ§Ã£o sem reload. <!-- `App.test.ts` cobre seleÃ§Ã£o de projeto, hierarquia e reconciliaÃ§Ã£o sem remontar a SPA -->

### 4.2 Executar spike e renderer Gantt acessÃ­vel `[A]`

Ref: `gantt-navigation-experience/spec.md` FR-001â€“008; INT-NAV-001/002

- [x] 4.2.1 Implementar virtualizaÃ§Ã£o vertical/horizontal e janela temporal sem intervalo fixo. <!-- janela vertical/horizontal limitada por viewport; intervalo deriva das tarefas -->
- [x] 4.2.2 Implementar busca, filtros, relaÃ§Ãµes ocultas, teclado, touch e responsividade. <!-- busca/filtros, dependÃªncias ocultas revelÃ¡veis, Ã¡rvore por teclado, Pointer Events e breakpoints -->
- [~] 4.2.3 Medir 2k/5k, testar dispositivos reais e registrar gate do spike. <!-- benchmark Chromium headless no staging: 2k/5k com 32 nós no DOM; matriz de dispositivos físicos permanece pendente; evidência em docs/performance-evidence.md -->

### 4.3 Implementar fluxos de calendÃ¡rio, dependÃªncia e operaÃ§Ã£o `[A]`

Ref: INT-CALENDAR-001/002, INT-DEPS-001/002, INT-OPS-001/002

- [x] 4.3.1 Implementar telas/painÃ©is e estados canÃ´nicos de calendÃ¡rio/datas. <!-- painel versionado, prÃ©via/confirmacÃ£o manual, automÃ¡tico e aviso de calendÃ¡rio -->
- [x] 4.3.2 Implementar criaÃ§Ã£o/inspeÃ§Ã£o acessÃ­vel de dependÃªncias e criticidade. <!-- painel textual, confirmaÃ§Ã£o explÃ­cita, validaÃ§Ã£o de ciclo/escopo e indicaÃ§Ã£o de criticidade -->
- [x] 4.3.3 Implementar ghosts, confirmaÃ§Ã£o, recuperaÃ§Ã£o e rota com testes de componente/E2E. <!-- `App.test.ts` cobre ghost, confirmaÃ§Ã£o explÃ­cita, operaÃ§Ã£o concluÃ­da e reconciliaÃ§Ã£o; testes de API cobrem retry/conflito -->

### 4.4 Implementar estados de sincronizaÃ§Ã£o e histÃ³rico `[A]`

Ref: INT-SYNC-001/002; INT-AUDIT-001

- [x] 4.4.1 Implementar badges, conflitos e recuperaÃ§Ã£o sincronizados por eventos reais. <!-- SSE retomÃ¡vel, diagnÃ³stico de pendÃªncias/conflitos e reconexÃ£o/reconciliaÃ§Ã£o -->
- [x] 4.4.2 Implementar writer de auditoria, consulta paginada e cadeia causal. <!-- AuditWriter, API filtrÃ¡vel por cursor e causationId -->
- [x] 4.4.3 Implementar painel de histÃ³rico acessÃ­vel e testes de isolamento/paginaÃ§Ã£o. <!-- painel sob demanda e teste de owner/cursor/filtro -->

## FASE 5 - Qualidade, operaÃ§Ã£o e release

### 5.1 Fechar matriz de testes e observabilidade `[C]`

Ref: ConstituiÃ§Ã£o IV; especificaÃ§Ã£o Â§18N/18O

- [~] 5.1.1 Expandir suites golden, integraÃ§Ã£o MySQL, contract Todoist, E2E e carga. <!-- PHPUnit/MySQL, Vitest, typecheck, build, Pint, audits e benchmark executados; E2E autenticado e carga sustentada continuam gate externo -->
- [x] 5.1.2 Configurar mÃ©tricas, logs estruturados, health/readiness, filas e alertas aprovados. <!-- staging validado: /health, /ready, /metrics; regras em etc/monitoring/ganttist-alerts.yml -->
- [~] 5.1.3 Executar checklist de aceite, testes exploratÃ³rios e registrar bugs/limitaÃ§Ãµes. <!-- aceite automatizado atualizado; OAuth/webhook/e-mail/SSE multi-cliente e exploraÃ§Ã£o visual ainda exigem credenciais/execuÃ§Ã£o manual -->

## Matriz de DependÃªncias

```mermaid
flowchart TD
 F1[Fase 1] --> F2[Fase 2]
 F1 --> F3[Fase 3]
 F2 --> F3
 F2 --> F4[Fase 4]
 F3 --> F4
 F3 --> F5[Fase 5]
 F4 --> F5
```

## Cobertura de Interfaces

| Surface ID | Coverage | Interaction IDs | Task IDs |
|---|---|---|---|
| SURF-WEB-ACCESS | FULL | INT-ACCESS-001â€“003 | 3.3 |
| SURF-WEB-OPERATIONS | FULL | INT-WORKSPACE, CALENDAR, DEPS, OPS, SYNC, NAV, AUDIT | 4.1â€“4.4 |
| SURF-EMAIL-ACCESS | FULL | INT-ACCESS-003 | 3.3 |

## Resumo Quantitativo

| Fase | Tarefas | Subtarefas | Criticidade |
|---|---:|---:|---|
| 1 - FundaÃ§Ãµes | 2 | 6 | C/A |
| 2 - DomÃ­nio | 2 | 6 | A |
| 3 - OperaÃ§Ã£o/integraÃ§Ã£o | 3 | 9 | C |
| 4 - Interfaces | 4 | 12 | A |
| 5 - Qualidade | 1 | 3 | C |
| **Total** | **12** | **36** | - |

## Escopo Coberto

| Item | DescriÃ§Ã£o | Fase |
|---|---|---|
| MVP-CORE | calendÃ¡rio, grupos, dependÃªncias e criticidade | 2 |
| MVP-SYNC | operaÃ§Ãµes, Todoist, acesso e recuperaÃ§Ã£o | 3 |
| MVP-UX | workspace, Gantt, estados e histÃ³rico | 4 |
| MVP-QUALITY | testes, operaÃ§Ã£o e release gates | 5 |

## Escopo ExcluÃ­do

| Item | DescriÃ§Ã£o | Motivo |
|---|---|---|
| FUTURE-1 | baseline, milestones, colaboraÃ§Ã£o, offline-first e exportaÃ§Ã£o | explicitamente fora do MVP |
| EXECUTION | implementaÃ§Ã£o de qualquer tarefa | aguarda revisÃ£o e autorizaÃ§Ã£o do usuÃ¡rio |
