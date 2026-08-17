# Pendências e limitações explícitas

## Bloqueadas por ambiente externo

- **OAuth Todoist real:** exige `TODOIST_CLIENT_ID`, `TODOIST_CLIENT_SECRET`, conta e callback HTTPS cadastrado. O código persiste o token criptografado e conduz a seleção de projeto; a homologação contra a conta real permanece pendente.
- **Webhook público:** exige URL HTTPS alcançável e `TODOIST_WEBHOOK_SECRET`. O receptor valida HMAC, deduplica, associa o evento ao usuário e invalida o marcador de sincronização; entrega e replay reais permanecem pendentes.
- **Passwordless por e-mail:** fluxo de envio, consumo único e interface estão implementados. A validação operacional de entrega depende do ambiente SMTP já configurado e de uma caixa de teste.
- **SSE multi-cliente:** contrato arquitetural definido; publicação distribuída depende do ambiente persistente de workers e endpoint público de longa duração.
- **Produção:** Apache + PHP-FPM, TLS, scheduler, workers, monitoramento, alertas, backup/restauração e rollback dependem da infraestrutura.
- **Testes:** o checkout de produção não possui dependências de desenvolvimento (`vendor/bin/phpunit`); a suíte está versionada, mas sua execução deve ocorrer em CI ou checkout de desenvolvimento.

## Implementação parcial nesta iteração

- Adapter Todoist cobre leitura de projetos/snapshot, edição, conclusão/reabertura e fake determinístico; ainda falta homologação contra a conta real.
- A SPA usa fixture somente quando `GANTTIST_DEMO_MODE=true`; no modo de produto exige integração, seleciona projeto, lê dados reais, gerencia dependências e aplica alterações calculadas.
- O renderizador próprio demonstra hierarquia, zoom, grupos, relações SVG, criticidade, filtros, seleção e responsividade; drag/resize persistente, touch avançado e virtualização nominal de 2.000/5.000 precisam do spike medido.
- Auditoria, fila, histórico de recálculo e comando `todoist:sync` possuem código base; ativação do scheduler, workers, alertas e execução de restauração são configuração de produção.
- Critical path implementado para tarefas executáveis; golden suite precisa expandir FF/SF, grupos, conclusão tardia e stress.

Nenhuma dessas limitações é silenciosa nem foi mascarada por dados reais inexistentes.
