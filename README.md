# Ganttist

Planejamento Gantt sobre tarefas do Todoist, com calendário de trabalho, dependências FS/SS/FF/SF, caminho crítico, simulação e sincronização rastreável. Esta iteração entrega um vertical slice executável do MVP e explicita integrações externas ainda pendentes.

## Stack

- PHP 8.4+ em produção (o bootstrap local foi validado provisoriamente com PHP 8.3.7, autorizado pelo cliente)
- Laravel 12, monólito modular
- Vue 3 + TypeScript + Pinia + Tailwind CSS 4
- MySQL 9.7.2, `utf8mb4`
- API JSON `/api/v1`, SSE como contrato de evolução
- Gantt próprio; SVG para dependências

## Início rápido no ambiente fornecido

As ferramentas portáteis ficam em `.tools/` e são ignoradas pelo Git. O MySQL local deve estar ativo.

```powershell
Copy-Item .env.example .env
# Preencha DB_PASSWORD e gere a chave:
php -d extension=mbstring -d extension=openssl -d extension=fileinfo artisan key:generate
php -d extension=mbstring -d extension=openssl -d extension=fileinfo artisan migrate
$env:PATH=(Resolve-Path '.tools/node-v22.14.0-win-x64').Path+';'+$env:PATH
npm install
npm run build
php -d extension=mbstring -d extension=openssl -d extension=fileinfo artisan serve
```

Acesse `http://127.0.0.1:8000`. A workspace inicial usa fixtures sintéticas premium para permitir avaliação sem uma conta Todoist. Nenhum campo nativo de tarefa é persistido como fonte autoritativa.

## Testes

```powershell
php -d extension=mbstring -d extension=openssl -d extension=fileinfo -d xdebug.mode=off vendor/phpunit/phpunit/phpunit
$env:PATH=(Resolve-Path '.tools/node-v22.14.0-win-x64').Path+';'+$env:PATH
npm run build
```

## Estrutura

- `app/Domain/Scheduling`: motor de calendário, precedência, cascata e criticidade, independente de Laravel
- `app/Infrastructure/Todoist`: adapter único para API Todoist
- `app/Http/Controllers`: API, passwordless, OAuth e webhook
- `resources/js`: SPA e Gantt próprio
- `database/migrations`: identidade, integração, projetos, calendários, dependências, operações, fila e auditoria
- `docs`: briefing, arquitetura, contratos, matemática, rastreabilidade e pendências

## Segurança e configuração

Não versione `.env`. Tokens Todoist são destinados a criptografia em repouso; tokens de login são armazenados apenas por hash; sessões são server-side; webhooks validam HMAC; endpoints possuem rate limit. Em produção, defina HTTPS, cookies seguros, `APP_DEBUG=false`, segredos OAuth/webhook e um mailer transacional.

## Estado da entrega

Consulte [docs/discovery-briefing.md](docs/discovery-briefing.md) e [docs/pending.md](docs/pending.md). OAuth real, webhooks públicos, e-mail e homologação em PHP 8.4 aguardam infraestrutura/credenciais, sem bloquear o núcleo e a interface demonstráveis.
