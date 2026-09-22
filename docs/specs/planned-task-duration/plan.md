# Implementation Plan: Duração Planejada da Tarefa

**Feature**: `planned-task-duration` | **Date**: 2026-09-21 | **Spec**: [spec.md](spec.md)

## Summary

Adicionar duração planejada explícita e opcional às tarefas locais, preservando dados legados e centralizando a resolução de início, fim e duração no domínio. A duração será usada pelos dois cálculos existentes — projeção/status e agendamento/criticidade — e será exposta pela API e pela SPA como intenção persistida, nunca como valor calculado pelo cliente.

As decisões de compatibilidade e conflitos estão em [research.md](research.md); o modelo e contrato detalhados estão em [data-model.md](data-model.md) e [contracts/tasks-api.md](contracts/tasks-api.md).

## Technical Context

**Language/Version**: PHP 8.4+, TypeScript 5.x.
**Primary Dependencies**: Laravel 12, Vue 3, Pinia.
**Storage**: MySQL, tabela local `project_tasks`.
**Testing**: PHPUnit, testes de feature Laravel, Vitest e Playwright.
**Target Platform**: SPA web responsiva.
**Project Type**: monólito modular PHP + SPA.
**Performance Goals**: cálculo determinístico do workspace sem consulta por tarefa adicional.
**Constraints**: datas civis `YYYY-MM-DD`; duração em dias úteis; relações FS/SS/FF/SF; compatibilidade com tarefas sem duração explícita; leitura e escrita autorizadas por projeto.
**Scale/Scope**: todos os fluxos locais de criar, editar, duplicar e projetar tarefas.

## Constitution Check

*GATE: PASS — rechecado após o design.*

| Princípio | Status | Notas |
|---|---|---|
| I. Fonte local de verdade | PASS | Duração é persistida em tarefa local; projeções não viram dados planejados. |
| II. Core determinístico | PASS | Resolvedor único de parâmetros usa calendário explícito e data de referência controlada. |
| III. Integridade e estados | PASS | Normalização atômica, duração positiva e precedência de status já documentada. |
| IV. Acesso explícito | PASS | Reutiliza autorização dos endpoints existentes. |
| V. Qualidade, segurança e UX | PASS | Testes de domínio, API, contrato, SPA e E2E cobrem as fronteiras e teclado/formulário padrão. |

## Project Structure

### Documentation

```text
docs/specs/planned-task-duration/
├── spec.md
├── research.md
├── data-model.md
├── plan.md
├── quickstart.md
└── contracts/
    └── tasks-api.md
```

### Source Code

```text
database/migrations/                         # migration aditiva da tarefa
app/Domain/Scheduling/
├── TaskProjectionInput.php                  # recebe duração explícita
├── TaskProjectionCalculator.php              # projeção, status e violação
├── TaskPlan.php                              # duração resolvida para criticidade
└── SchedulingEngine.php                      # folga/caminho crítico com a mesma duração
app/Http/Controllers/Api/ProjectController.php # normalização de comando e workspace
resources/js/
├── types.ts
├── contracts/workspace-contract.ts
└── ProjectPlanningPage.vue                   # editor e gestos do Gantt
tests/Unit/
├── TaskProjectionCalculatorTest.php
└── SchedulingEngineTest.php
tests/Feature/LocalProjectsApiTest.php
```

**Structure Decision**: criar um resolvedor de planejamento no domínio e fazê-lo alimentar `TaskProjectionInput` e `TaskPlan`. O controller apenas valida intenção, carrega o registro e persiste a saída normalizada; não duplica matemática de calendário.

## Design e Sequência de Implementação

1. Criar migration aditiva para duração opcional e atualizar o modelo de dados/documentação. Não fazer backfill de datas nem modificar tarefas existentes.
2. Criar `TaskPlanningNormalizer` (ou value object equivalente) no domínio. Ele recebe valores persistidos, patch e driver da edição; valida duração; devolve início, fim e duração explícita normalizados.
3. Estender `TaskProjectionInput` e `TaskPlan` com duração explícita/resolvida. Substituir os fallbacks isolados de `TaskProjectionCalculator` e `TaskPlan::fromDates` pelo resolvedor compartilhado.
4. Implementar âncora de fim: `fim + duração` sem início produz início considerado retroativo; duração sem datas conserva início virtual para cálculo sem persistência.
5. Produzir estado e motivo de violação no resultado de projeção quando o intervalo considerado não satisfizer prazo e duração. Propagar para seções, relações, caminho crítico e workspace sem escrita automática.
6. Estender os endpoints de tarefa com `plannedDurationWorkdays` e `planningDriver`; normalizar e persistir em transação. Atualizar duplicação e resumo/progresso para usar duração resolvida.
7. Expor campos planejados e derivados no workspace; atualizar tipo TypeScript e parser de contrato antes de consumir na tela.
8. Incluir campo numérico acessível no editor, com ajuda “dias úteis”; manter o contêiner `v-default-form` e `DefaultSubmitButton`. Ajustar movimento e resize para enviar o driver correto e reconciliar a resposta autorizada.
9. Cobrir domínio, API, contrato, SPA e E2E pelos cenários do quickstart, incluindo calendário não padrão, finais de semana, relações, seções, conclusão e tarefas legadas.

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
|---|---|---|---|
| DB | convenção da migration vigente | migration + restrição de domínio | `database/migrations/` |
| Domínio PHP | camelCase | value object + testes unitários | `app/Domain/Scheduling/` |
| Backend DTO | camelCase | `Request::validate` + normalizador | `ProjectController` |
| API payload | camelCase na escrita; campos atuais snake_case na leitura do workspace | testes de feature + parser TypeScript | `contracts/tasks-api.md` |
| Frontend | snake_case para campos do workspace; camelCase para comandos | parser + tipos TS | `resources/js/types.ts` |

**Mapper layer (DB ↔ DTO)**: `ProjectController` converte o registro de tarefa para os objetos de domínio e para a linha de workspace; o domínio não conhece HTTP ou banco.

**Validação de schema**: requests são validados no Laravel e normalizados pelo domínio; responses do workspace são validadas em `resources/js/contracts/workspace-contract.ts` antes de entrarem na store.

## Complexity Tracking

Nenhuma violação da constituição é necessária. O novo resolvedor é justificado por eliminar duplicação entre os dois motores de cálculo já existentes.
