# Pendências e limitações explícitas

## Bloqueadas por ambiente externo

- **OAuth Todoist real:** exige `TODOIST_CLIENT_ID`, `TODOIST_CLIENT_SECRET`, conta e callback HTTPS cadastrado. O código persiste o token criptografado e conduz a seleção de projeto; a homologação contra a conta real permanece pendente.
- **Webhook público:** exige URL HTTPS alcançável e `TODOIST_WEBHOOK_SECRET`. O receptor valida HMAC, deduplica, associa o evento ao usuário e invalida o marcador de sincronização; entrega e replay reais permanecem pendentes.
- **Passwordless por e-mail:** fluxo de envio, consumo único e interface estão implementados. A validação operacional de entrega depende do ambiente SMTP já configurado e de uma caixa de teste.
- **SSE multi-cliente:** endpoint autenticado, keepalive e reconexão automática estão implementados; a validação com múltiplas abas e infraestrutura de longa duração permanece pendente.
- **Produção:** Apache + PHP-FPM, TLS, scheduler, workers, monitoramento, alertas, backup/restauração e rollback dependem da infraestrutura.
- **Testes:** a suíte automatizada foi executada localmente contra o MySQL com 68 testes PHP/283 asserções e 12 testes frontend; a execução equivalente deve ocorrer em CI antes do release.

## Implementação parcial nesta iteração

- Adapter Todoist cobre leitura de projetos/snapshot, edição, conclusão/reabertura, retries e fake determinístico; ainda falta homologação contra a conta real.
- A SPA usa fixture somente quando `GANTTIST_DEMO_MODE=true`; no modo de produto exige integração, seleciona projeto, lê dados reais, gerencia dependências e aplica alterações calculadas.
- O renderizador próprio cobre hierarquia, zoom, grupos, relações SVG, criticidade, filtros, seleção, responsividade e drag/resize persistente; o benchmark virtual local cobre os cenários sintéticos de 2.000/5.000, mas a matriz real de navegadores e dispositivos ainda exige medição manual.
- Auditoria, fila, histórico de recálculo, retry, SSE e comando `todoist:sync` possuem implementação; métricas, regras de alerta portáveis e runbook foram entregues, enquanto sua ativação, workers e restauração permanecem configuração de produção.
- Critical path implementado para tarefas executáveis, incluindo golden suite de FS/SS/FF/SF, grupos e conclusão efetiva; a execução sob carga real continua pendente.

Nenhuma dessas limitações é silenciosa nem foi mascarada por dados reais inexistentes.
