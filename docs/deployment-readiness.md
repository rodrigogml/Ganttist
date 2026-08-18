# Preparação para implantação

## O que já pode ser automatizado

- O workflow `.github/workflows/quality.yml` instala PHP 8.4 e Node 22, inicia MySQL efêmero e executa migrations por meio da suíte Laravel.
- A pipeline audita dependências PHP e frontend de produção, executa formatação PHP em modo de verificação, testes backend, testes frontend, checagem TypeScript e build de produção.
- `scripts/test.ps1` executa o mesmo conjunto localmente no Windows, usando o MySQL configurado no ambiente.
- Regras de alerta e procedimento de resposta estão em `etc/monitoring/ganttist-alerts.yml` e `docs/operations-runbook.md`.
- `php artisan app:production-readiness` falha sem imprimir segredos se HTTPS, chave, cookies seguros, MySQL, fila, SMTP ou credenciais Todoist não estiverem configurados.
- Respostas incluem cabeçalhos globais contra clickjacking, MIME sniffing e permissões não usadas; HSTS é emitido em produção ou HTTPS.

## Variáveis obrigatórias na implantação

| Variável | Finalidade | Fonte |
|---|---|---|
| `APP_KEY` | criptografia de tokens e sessões | segredo gerado por ambiente |
| `DB_*` | MySQL de produção | infraestrutura |
| `TODOIST_CLIENT_ID` / `TODOIST_CLIENT_SECRET` | OAuth Todoist | aplicativo Todoist de produção |
| `TODOIST_WEBHOOK_SECRET` | validação HMAC | configuração do webhook Todoist |
| `MAIL_*` | envio passwordless | provedor SMTP transacional |
| `APP_URL` | callback OAuth e cookies | URL HTTPS pública |

## Ordem de implantação

1. Provisionar MySQL, Redis/fila, worker, scheduler e coleta de métricas.
2. Configurar segredos e `APP_DEBUG=false`; executar `php artisan migrate --force`.
3. Publicar assets gerados por `npm run build`, executar `php artisan route:cache` e habilitar HTTPS/cookies seguros.
   Execute `php artisan app:production-readiness` antes de promover a instância.
4. Configurar callback OAuth e webhook HTTPS no Todoist; validar HMAC e reconciliação com conta de homologação.
5. Configurar SMTP e executar login passwordless com caixa de teste.
6. Registrar benchmark 2k/5k, SSE multiaba e exploração visual no aceite de release.

Nenhuma variável secreta é incluída no repositório ou no workflow; a pipeline usa somente o adapter fake para validar o contrato sem acesso externo.
