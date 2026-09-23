# Registro de Validação

## Consulta nominal com 2.000 tarefas

- Cenário: `status:aberta & prioridade:alta` sobre 2.000 tarefas locais, com cálculo da contagem e da projeção visível.
- Evidência automatizada: `resources/js/stores/workspace.test.ts`.
- Critério: atualização abaixo de 1 segundo.
- Resultado: aprovado pelo teste automatizado em 2026-09-22.

## Suítes integrais

- PHP: 117 testes, 619 asserções aprovadas.
- Vitest: 88 testes aprovados.
- Tipos: `vue-tsc --noEmit` aprovado.
- Build: `npm run build` aprovado; permanecem apenas avisos preexistentes de diretivas `use client` de dependências e chunks grandes.
- E2E: 24 cenários aprovados, incluindo aplicação cruzada de view, atalho `/`, IME, ajuda, histórico, criação por `Ctrl+Enter`, colisão de importação, cópia e toque.

## Estados canônicos

- Vazio e validação: projeção unificada e diagnóstico preservam a última consulta válida.
- Remoto e stale: atualização inválida preserva a projeção anterior e mostra o estado desatualizado.
- Offline: busca e view carregada seguem legíveis; escrita informa que exige conexão.
- Acesso negado: endpoints isolam projeto e proprietário, retornando resposta segura.
- Portabilidade: download, conflito, cópia, sobrescrita, cancelamento e arquivo inválido têm cobertura E2E/Feature.
