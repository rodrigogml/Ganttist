# Implementation Plan: Tarefas Recorrentes e Soneca

**Feature**: `recurring-tasks-snooze` | **Date**: 2026-09-24 | **Spec**: [spec.md](spec.md)

## Summary

Introduzir uma série recorrente com cursor lógico, regra estruturada e histórico imutável sem transformar o motor de scheduling em gerador de séries. Um domínio de recorrência calcula a ocorrência seguinte; o planejamento atual agenda a ocorrência aberta; uma projeção compartilhada calcula estados e preview de soneca. O cálculo de rede finita ignora recorrências contínuas para CPM, folga, término e progresso, mantendo-as na projeção operacional como sucessoras possíveis.

## Technical Context

**Language/Version**: PHP 8.4, TypeScript 5.9.
**Primary Dependencies**: Laravel 12; Vue 3; Pinia; Vite.
**Storage**: MySQL local, migrations Laravel; cache/sessão existentes não são fonte de recorrência.
**Testing**: PHPUnit, Vitest, Playwright, `vue-tsc` e build Vite.
**Target Platform**: SPA web responsiva em navegadores modernos; API JSON autenticada.
**Project Type**: Monólito web Laravel com SPA acoplada.
**Performance Goals**: Preview de soneca em até 1 segundo p95 para projeto de até 2.000 tarefas.
**Constraints**: Datas civis, calendário de trabalho autoritativo, nenhuma data útil calculada no navegador, uma ocorrência aberta por série, sem cron/worker para gerar ocorrências.
**Scale/Scope**: Até 2.000 tarefas por projeto; operações somente intraprojeto; histórico paginado.

## Interaction Surface Architecture

**Surface Catalog**: [../../architecture/interaction-surfaces.md](../../architecture/interaction-surfaces.md)
**Interface Design Applicability**: REQUIRED — há editor, ações rápidas, preview, confirmação, histórico e adaptações Gantt/Tarefas.

| Surface ID | Feature Coverage | Technology Decision | Module/Repository | Notes |
|------------|------------------|---------------------|-------------------|-------|
| SURF-WEB-OPERATIONS | FULL | Vue 3 + TypeScript no navegador | `resources/js`, `resources/css` | SPA responsiva existente; backend é autoridade de parser, calendário, cursor e impacto. |
| SURF-WEB-ACCESS | N/A | — | — | Recorrência não modifica autenticação. |
| SURF-EMAIL-ACCESS | N/A | — | — | Não há notificação de recorrência neste escopo. |

## Constitution Check

*GATE: PASS antes do Phase 0 e rechecado após Phase 1.*

| Princípio | Status | Notas |
|-----------|--------|-------|
| I. Fonte local de verdade | PASS | Regra, cursor, histórico e soneca são locais; nenhuma integração externa participa. |
| II. Core determinístico | PASS | Parser, calendário, próxima ocorrência, preview e rede finita residem em domínio PHP com relógio controlável. |
| III. Integridade | PASS | Uma ocorrência aberta, histórico único, cursor esperado, ciclos já existentes e proibição de predecessora recorrente são validados no servidor. |
| IV. Acesso explícito | PASS | Todas as rotas preservam membro/projeto e papéis existente. |
| V. Qualidade, segurança e experiência | PASS | Há testes de domínio, integração e interface; teclado, toque, feedback e limites de dados constam da spec. |

## Project Structure

### Documentation

```text
docs/specs/recurring-tasks-snooze/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── recurrence-api.md
└── interface-spec.md              # etapa 6
```

### Source Code

```text
app/
├── Domain/
│   ├── Recurrence/                # novo: regra, parser, formatter, cálculo e transições
│   └── Scheduling/                # adaptar escopo operacional versus rede finita
├── Http/Controllers/Api/
│   └── ProjectController.php       # borda HTTP; delega comandos/consultas novos
├── Services/
│   ├── ProjectWorkspaceProjectionService.php # novo: extrai projeção reutilizável do controller
│   └── TaskRecurrenceService.php   # novo: orquestra comandos transacionais
database/migrations/
└── 2026_09_24_000113_add_task_recurrence_and_occurrences.php
resources/js/
├── ProjectPlanningPage.vue
├── contracts/workspace-contract.ts
├── types.ts
└── stores/workspace.ts
tests/
├── Unit/Recurrence/
├── Unit/Scheduling/
└── Feature/
```

**Structure Decision**: O domínio de recorrência não depende de Laravel. `TaskRecurrenceService` é a fronteira de caso de uso e transação; `ProjectWorkspaceProjectionService` centraliza o cálculo hoje concentrado em `ProjectController::workspace()` para que leitura e preview usem a mesma projeção. Controller e SPA não duplicam regra de data ou cascata.

## Design e Sequência de Implementação

1. Criar migration exclusivamente aditiva: colunas de recorrência em `project_tasks` e tabela `projectTaskOccurrence`, FKs, checks e índices definidos no modelo de dados. Não preencher tarefas existentes nem alterar históricos.
2. Implementar value objects imutáveis de regra e ocorrência, parser/formatter PT-BR V1 e calculador de próxima data. Injetar `WorkCalendar` e relógio no domínio; cobrir aliases, listas, ordinais, início/fim, regras fixas, intervalos em dias úteis e intervalos de calendário em semanas, meses e anos, inclusive fim de mês e 29 de fevereiro.
3. Estender a entrada/projeção de scheduling com participação na rede finita. Projetar toda ocorrência corrente, mas filtrar recorrentes contínuas somente no passe de término, folga e criticidade; recalcular resumo/progresso com a mesma distinção e usar `project_schedule_dependencies` normalizada por `SectionDependencyNormalizer`, nunca a fonte legada `project_task_dependencies`.
4. Extrair a montagem de workspace e simulação do controller para serviço reutilizável. Permitir uma cópia efêmera da ocorrência com planejamento normalizado para comparar antes/depois e classificar impacto.
5. Criar comandos de interpretar/salvar/remover/encerrar recorrência, preview/confirmar soneca, concluir ocorrência e paginação de histórico. Aplicar autorização, versão/cursor esperado e bloqueio da tarefa dentro de uma transação curta.
6. Validar proibição de predecessora recorrente em criação/edição de dependência, mudança de estrutura e ativação/edição de regra, incluindo a expansão de seções.
7. Ampliar contrato de workspace, tipos e parser de resposta antes de conectar os componentes. Atualizar Gantt, Tarefas, editor e menu de contexto após o design de interface; usar `DefaultSubmitButton` e `v-default-form` em qualquer formulário novo de persistência.
8. Cobrir domínio, API, contratos, acessibilidade, teclado/toque e E2E com os cenários do quickstart; executar suíte, checagem de tipos e build.

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
|--------|------------|-----------|------------------|
| DB columns | Convenção local vigente: `recurrenceRule`, `recurrenceCursor`, tabela `projectTaskOccurrence`; chaves históricas existentes preservadas | migration, FKs, checks e índices | `database/migrations/*` |
| Domain values | PascalCase tipos; camelCase propriedades | constructors e value objects | `app/Domain/Recurrence` |
| Backend request/response | camelCase | validação de request e serviços | `contracts/recurrence-api.md` |
| Frontend types | camelCase | `workspace-contract.ts` e tipos de comando | `resources/js/contracts`, `resources/js/types.ts` |
| API payload | camelCase | request e parser de resposta | `contracts/recurrence-api.md` |
| URL path | kebab-case | rotas Laravel | `routes/api.php` |

**Mapper layer (DB <-> DTO)**: `TaskRecurrenceService` mapeia estado persistido em value objects; `ProjectWorkspaceProjectionService` mapeia valores de domínio para a resposta de workspace; `workspace-contract.ts` valida a resposta antes do store.

**Validação de schema**: ambos os lados. Backend valida intenções e estados transacionais; frontend valida resposta de workspace e respostas de comando antes de atualizar estado reativo.

## Validation Scenarios

- Regras fixas preservam padrão após soneca; regras por intervalo usam `max(cursor, agendamento, conclusão)`.
- Datas rápidas, data manual, duração e feriados passam pelo calendário e normalizador de planejamento.
- Cursor/versão obsoletos e chave de conclusão repetida distinguem conflito de replay idempotente.
- Recorrentes como sucessoras recebem bloqueio; qualquer predecessora recorrente direta ou de seção é recusada.
- Projeção operacional inclui recorrente; rede finita, CPM, folga, término e progresso a excluem.
- Preview usa o mesmo resultado de leitura do workspace e confirmação revalida o snapshot.
- Intervalos em dias contam dias úteis; intervalos em semanas, meses e anos usam calendário, aplicam fim de mês/29 de fevereiro e só então normalizam o agendamento pelo calendário do projeto.
- `stats.finite` e `stats.operational` obedecem exatamente o schema de `contracts/recurrence-api.md`, retornando zero para contagens operacionais sem recorrências.
- Contratos, telas, teclado e toque preservam a mesma ocorrência em Gantt e Tarefas.

## Complexity Tracking

Nenhuma violação da constituição é necessária.
