# Auditoria de CÃ³digo vs. EspecificaÃ§Ã£o do Cliente

**Data**: 2026-08-17
**Escopo**: anÃ¡lise read-only do estado existente contra `docs/especificacao_ganttist_v1.0.md` e as oito specs SDD.
**Regra de classificaÃ§Ã£o**: **Aproveitar** = comportamento e testes suficientes; **Corrigir** = base reutilizÃ¡vel, mas viola requisito; **Substituir** = abordagem incompatÃ­vel; **Ausente** = nÃ£o hÃ¡ implementaÃ§Ã£o observÃ¡vel.

## EvidÃªncia de validaÃ§Ã£o executada

- `vendor/phpunit/phpunit/phpunit`: **17 testes / 45 asserÃ§Ãµes, verde** (PHP local 8.3.7).
- `npm run build`: **verde**.
- Essas evidÃªncias confirmam somente o vertical slice coberto; nÃ£o provam cobertura integral do MVP.

## Matriz por feature

| Feature | Estado | EvidÃªncia aproveitÃ¡vel | Lacunas e desvios verificados | DireÃ§Ã£o para o plano |
|---|---|---|---|---|
| Acesso e conta Todoist | Corrigir | Passwordless com desafio de uso Ãºnico, sessÃ£o, OAuth state e token criptografado; testes de auth/OAuth. | NÃ£o hÃ¡ gestÃ£o de mÃºltiplas sessÃµes, revogaÃ§Ã£o por dispositivo, exclusÃ£o de conta, polÃ­tica de rotaÃ§Ã£o de chave nem testes de isolamento completo. OAuth nÃ£o estÃ¡ homologado em ambiente real. | Preservar fluxos e schema; completar lifecycle, seguranÃ§a e testes. |
| Workspace de projeto Gantt | Corrigir | SeleÃ§Ã£o de projeto, unicidade por usuÃ¡rio/projeto, construÃ§Ã£o de hierarquia Todoist, demo controlada. | Workspace calcula grupos superficialmente, nÃ£o deriva grupos de baixo para cima, nÃ£o persiste/serve configuraÃ§Ãµes reais de calendÃ¡rio, nem aplica estados/criticidade calculados. | Preservar adaptaÃ§Ã£o bÃ¡sica de snapshot; separar projeÃ§Ã£o do workspace do domÃ­nio. |
| CalendÃ¡rio e datas | Corrigir | `WorkCalendar`, exceÃ§Ãµes, dias Ãºteis, OperationalToday e testes bÃ¡sicos. | Controller de simulaÃ§Ã£o usa calendÃ¡rio padrÃ£o e payload do cliente; nÃ£o usa configuraÃ§Ãµes por Gantt, polÃ­ticas configuradas, datas derivadas de grupo ou regras completas de conclusÃ£o/nÃ£o planejada. `SchedulingEngine` materializa inÃ­cio em tarefa sem data no resultado. | Preservar primitives do calendÃ¡rio; redesenhar orquestraÃ§Ã£o e ampliar golden cases. |
| DependÃªncias e caminho crÃ­tico | Corrigir | Tipos FS/SS/FF/SF, autodependÃªncia, ciclo e ordenaÃ§Ã£o topolÃ³gica; persistÃªncia bÃ¡sica e UI de relaÃ§Ãµes. | NÃ£o valida pertencimento das tarefas, grupos predecessor/sucessor, mÃºltiplos tipos por par ou estados de relaÃ§Ã£o; criticidade/folga nÃ£o chega ao workspace. Cobertura de FF/SF, grupos e conclusÃ£o Ã© insuficiente. | Preservar tipos, grafo e teste-base; centralizar validaÃ§Ã£o e cÃ¡lculo. |
| OperaÃ§Ãµes e reagendamento | Substituir | SimulaÃ§Ã£o visual e chamadas de atualizaÃ§Ã£o de tarefa existentes. | SimulaÃ§Ã£o recebe tarefas/duraÃ§Ã£o do navegador; modo automÃ¡tico nÃ£o existe; aplicaÃ§Ã£o chama Todoist em sequÃªncia e sÃ³ grava resumo apÃ³s sucesso/falha, sem itens, idempotÃªncia operacional, revalidaÃ§Ã£o, batches, retry ou recuperaÃ§Ã£o de falha parcial. Drag ignora calendÃ¡rio e dependÃªncias. | Substituir a orquestraÃ§Ã£o por operaÃ§Ã£o lÃ³gica persistida; reaproveitar apenas affordances visuais. |
| SincronizaÃ§Ã£o Todoist | Corrigir | Gateway dedicado, webhook HMAC, deduplicaÃ§Ã£o de evento, polling SSE e rotina de snapshot/retry. | Webhook sÃ³ marca evento; reconciliaÃ§Ã£o nÃ£o aplica snapshot ao estado; SSE observa timestamp da integraÃ§Ã£o, nÃ£o eventos do projeto; nÃ£o hÃ¡ conflito por campo, ordem/eco robustos, fila processadora ou validaÃ§Ã£o multi-cliente. | Preservar gateway e verificaÃ§Ã£o HMAC; implementar pipeline de reconciliaÃ§Ã£o e operaÃ§Ã£o. |
| NavegaÃ§Ã£o e experiÃªncia Gantt | Corrigir | SPA, Ã¡rvore colapsÃ¡vel, zoom, seleÃ§Ã£o, filtros simples, SVG, painel e feedback bÃ¡sico; build verde. | Timeline fixa em intervalo de datas, sem virtualizaÃ§Ã£o, busca, teclado, navegaÃ§Ã£o profunda, touch adaptado, foco/acessibilidade completa ou benchmark 2k/5k. Filtros e itens ocultos nÃ£o tratam relaÃ§Ãµes conforme especificaÃ§Ã£o. | Preservar linguagem visual e componentes simples; criar spike de renderer e interface especificada. |
| Auditoria e rastreabilidade | Ausente | Schema inicial de `audit_events`, `sync_operations`, `recalculations` e itens. | NÃ£o hÃ¡ gravaÃ§Ã£o consistente de auditoria, consulta, paginaÃ§Ã£o, cadeia causal, retenÃ§Ã£o ou diagnÃ³stico de operaÃ§Ãµes. | Implementar como capability prÃ³pria, usando as tabelas somente apÃ³s validar o modelo. |

## Achados transversais prioritÃ¡rios

1. **Autoridade do core violada**: simulaÃ§Ã£o e aplicaÃ§Ã£o aceitam rede, duraÃ§Ã£o e calendÃ¡rio do navegador, em vez de derivÃ¡-los do projeto e dados autorizados.
2. **OperaÃ§Ãµes distribuÃ­das incompletas**: hÃ¡ chamadas externas dentro do fluxo sÃ­ncrono de aplicaÃ§Ã£o, sem itens persistentes suficientes para recuperaÃ§Ã£o; isto nÃ£o satisfaz a regra de atomicidade lÃ³gica.
3. **SeparaÃ§Ã£o entre demo e produto Ã© saudÃ¡vel, mas parcial**: a fixture Ã© explicitamente controlada; entretanto o modo real ainda projeta dados com regras simplificadas.
4. **Testes sÃ£o insuficientes como prova de MVP**: 17 testes confirmam fundaÃ§Ãµes, nÃ£o cobrem a matriz de aceite do cliente.
5. **Schema Ã© ponto de partida, nÃ£o evidÃªncia funcional**: a existÃªncia de tabelas para fila, auditoria e recalculaÃ§Ãµes nÃ£o demonstra workers, consistÃªncia, UI ou recuperaÃ§Ã£o.

## Componentes recomendados para reaproveitamento

- `WorkCalendar`, apÃ³s expansÃ£o dos contratos e golden tests.
- `Dependency`, topological sort e validaÃ§Ã£o bÃ¡sica do `SchedulingEngine`, apÃ³s correÃ§Ã£o das semÃ¢nticas de tarefas sem data, grupos e conclusÃ£o.
- `TodoistGateway`, gateways fake/HTTP, OAuth state e autenticaÃ§Ã£o passwordless.
- Migrations de identidade, integraÃ§Ã£o, projeto, dependÃªncia e calendÃ¡rio como base a ser reconciliada com os planos.
- Estrutura visual inicial da SPA e o SVG de relaÃ§Ãµes, sem considerar o atual renderer como soluÃ§Ã£o de escala.

## Componentes que nÃ£o devem orientar o comportamento futuro

- Payload de simulaÃ§Ã£o/aplicaÃ§Ã£o enviado pelo navegador como fonte de regras ou dados de planejamento.
- AplicaÃ§Ã£o sequencial sÃ­ncrona de cascata no Todoist.
- Intervalo temporal fixo e manipulaÃ§Ã£o de datas por dias corridos no frontend.
- Uso de timestamp de integraÃ§Ã£o como mecanismo suficiente de sincronizaÃ§Ã£o em tempo real.

## Limites da auditoria

- NÃ£o houve homologaÃ§Ã£o OAuth, webhook, e-mail ou SSE em infraestrutura pÃºblica, pois credenciais e ambiente nÃ£o estÃ£o no repositÃ³rio.
- NÃ£o foram executados benchmarks de 2.000/5.000 tarefas nem testes em dispositivos reais.
- Achados serÃ£o convertidos em tarefas verificÃ¡veis; nenhum cÃ³digo foi modificado.
