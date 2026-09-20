# Rollout da biblioteca documental

Este procedimento mantém a navegação documental oculta por `DOCUMENTS_ENABLED=false` até que o ambiente passe pelos checks e pelo smoke test. Os endpoints permanecem disponíveis para a homologação controlada.

## 1. Dependências e configuração

- PHP 8.4 com `fileinfo`, `mbstring`, `pdo_mysql` e `pdo_sqlite`.
- Python 3.12 e `python -m pip install -r scripts/documents/requirements.txt`.
- `composer install --no-dev --prefer-dist --optimize-autoloader` e `npm ci && npm run build`.
- Aplicar os exemplos `etc/php/99-ganttist-documents.ini` e `etc/nginx/ganttist-documents.conf.example`, ajustando os caminhos da instalação.
- Garantir espaço no diretório temporário do PHP para um upload de 500 MiB e no diretório `storage/app/tmp/documents` para duas cópias temporárias do maior PDF processado.
- Definir `DOCUMENTS_DISK=local` ou `s3`, `DOCUMENTS_MAX_UPLOAD_KB=512000`, `DOCUMENTS_PROCESSOR_TIMEOUT=240` e `DOCUMENTS_QUEUE_RETRY_AFTER=360`.
- Para S3, configurar bucket privado, credenciais de menor privilégio e acesso apenas ao prefixo `projects/*/documents/*`. Não habilitar URL pública no bucket.

## 2. Banco, storage e worker

```text
php artisan migrate --force
php artisan documents:readiness --write
sudo install -m 0644 etc/systemd/ganttist-documents-queue.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now ganttist-documents-queue
sudo systemctl status ganttist-documents-queue
```

O comando de prontidão valida as seis tabelas documentais, o limite de 500 MiB, a janela `timeout/retry_after`, o script Python, a versão fixada do `pypdf` e uma gravação/leitura/remoção privada no disco configurado.

## 3. Smoke test de homologação

Com `DOCUMENTS_ENABLED=false`, usar uma conta de homologação e confirmar:

1. Upload de um PDF e de um arquivo não exibível inline; o segundo deve baixar como attachment com `nosniff`.
2. `GET`, `HEAD` e `Range: bytes=0-3` no conteúdo; o Range deve responder `206`, `Content-Range` e exatamente quatro bytes.
3. Criação de duas regiões em lote; o worker deve produzir dois PDFs vetoriais, preservar texto selecionável e registrar a revisão-fonte.
4. Nova revisão da origem; derivações automáticas devem entrar na fila sem substituir a revisão derivada atual antes do sucesso.
5. Preparação offline em um perfil de navegador de homologação; desligar a rede, reiniciar a aplicação e abrir o PDF, inclusive por Range.
6. Reconectar; a aplicação deve apenas oferecer a atualização, sem baixar os arquivos automaticamente.
7. Logout, troca de usuário e resposta `401/419`; IndexedDB e Cache Storage do usuário anterior devem ser removidos.
8. Arquivar/restaurar origem e derivados, confirmar conflitos esperados e executar uma exclusão definitiva de projeto para validar o purge assíncrono.

Verificar também os logs `document.created`, `document.revision.created`, `document.derivation.created`, `document.derivation.updated`, `document.derivation.processing`, `document.derivation.succeeded`, `document.derivation.failed`, `document.derivation.regenerate_requested`, `document.derivation.deactivated` e `document.derivation.dispatch_failed`, além da fila `documents` e da tabela `failed_jobs`.

## 4. Exposição e retorno

Após o smoke test, definir `DOCUMENTS_ENABLED=true`, limpar o cache de configuração (`php artisan optimize:clear && php artisan optimize`) e reiniciar os processos web. Isso libera a navegação e as rotas Vue documentais.

Para retorno rápido, definir novamente `DOCUMENTS_ENABLED=false` e reiniciar os processos web. As migrations são aditivas e os artefatos privados permanecem preservados; não executar `migrate:rollback` nem apagar prefixos de storage durante o rollback da aplicação.
