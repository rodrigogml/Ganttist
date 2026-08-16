# Pendências e limitações explícitas

## Bloqueadas por ambiente externo

- **OAuth Todoist real:** exige `TODOIST_CLIENT_ID`, `TODOIST_CLIENT_SECRET`, conta e callback HTTPS cadastrado. Redirect/callback e gateway estão contratados; homologação permanece pendente.
- **Webhook público:** exige URL HTTPS alcançável e secret fornecido pelo Todoist. O receptor HMAC/idempotente existe; entrega real e replay permanecem pendentes.
- **Passwordless por e-mail:** desafios one-time funcionam e, em local, o token é registrado com segurança operacional limitada. Envio real depende do mailer/credenciais.
- **SSE multi-cliente:** contrato arquitetural definido; publicação distribuída depende do ambiente persistente de workers e endpoint público de longa duração.
- **Produção:** Apache + PHP-FPM, TLS, scheduler, workers, monitoramento, alertas, backup/restauração e rollback dependem da infraestrutura.
- **PHP alvo:** validação local feita em 8.3.7 por autorização. Revisão final obrigatória em PHP 8.4.21+.

## Implementação parcial nesta iteração

- Adapter Todoist cobre leitura de projetos/snapshot e escrita temporal, mas faltam todos os contract tests contra mock oficial e conta sandbox.
- O vertical slice da SPA usa fixture local; criação/exclusão/conclusão real e operações compostas ainda precisam ligar comandos ao adapter/fila.
- O renderizador próprio demonstra hierarquia, zoom, grupos, relações SVG, criticidade, filtros, seleção e responsividade; drag/resize persistente, touch avançado e virtualização nominal de 2.000/5.000 precisam do spike medido.
- Auditoria, fila e operação lógica possuem schema; orquestração completa de retries/reconciliação ainda deve ser concluída.
- Critical path implementado para tarefas executáveis; golden suite precisa expandir FF/SF, grupos, conclusão tardia e stress.

Nenhuma dessas limitações é silenciosa nem foi mascarada por dados reais inexistentes.
