# Implementation Plan: Busca Unificada e Views de Tarefas

**Feature**: `unified-task-views` | **Date**: 2026-09-22 | **Spec**: [spec.md](spec.md)

## Summary

Unificar a busca e os filtros da barra de comandos em uma DSL textual avaliada sobre a projeção autorizada do workspace. Persistir views privadas por usuário e projeto como snapshots versionados, com contratos para criação, alteração, remoção, importação e exportação. Consultas não são registradas automaticamente; funil, autocomplete e ajuda atuam sobre a mesma expressão, sem estado concorrente.

## Technical Context

**Language/Version**: PHP 8.4, TypeScript 5.9
**Primary Dependencies**: Laravel 12; Vue 3.5; Pinia 3; Vite 7
**Storage**: MySQL `utf8mb4`; armazenamento local existente somente para preferências transitórias
**Testing**: PHPUnit 11, Vitest 4, Playwright
**Target Platform**: Aplicação web responsiva em navegadores modernos
**Project Type**: Monólito modular web com API JSON e SPA
**Performance Goals**: Em projeto de até 2.000 tarefas, 95% das alterações de consulta atualizam resultado e contagem em até 1 segundo após pausa de digitação
**Constraints**: Manter `/` e os operadores existentes; uma única fonte de verdade; views isoladas por usuário e projeto; consulta não amplia acesso
**Scale/Scope**: Um workspace de projeto por vez, até 2.000 tarefas no cenário nominal

## Interaction Surface Architecture

**Surface Catalog**: [docs/architecture/interaction-surfaces.md](../../architecture/interaction-surfaces.md)
**Interface Design Applicability**: REQUIRED — a feature altera barra de comandos, autocomplete, construtor, menus, confirmação, importação e feedback em desktop e touch.

| Surface ID | Feature Coverage | Technology Decision | Module/Repository | Notes |
|---|---|---|---|---|
| SURF-WEB-OPERATIONS | FULL | Vue 3, TypeScript, navegador moderno | `resources/js/ProjectPlanningPage.vue`, `resources/js/stores`, `resources/js/utils` | SPA responsiva; preservar tokens e acessibilidade existentes |

## Constitution Check

*GATE: Passou antes do Phase 0 e foi rechecado após Phase 1.*

| Princípio | Status | Notas |
|---|---|---|
| I. Fonte de verdade local | PASS | Views, histórico e queries persistem localmente; sem integração externa. |
| II. Core determinístico independente da interface | PASS | A feature consulta projeções; não duplica calendário, precedência ou status. |
| III. Integridade de estrutura, dependências e estados | PASS | Somente consulta projeções, sem alterar tarefas ou relações. |
| IV. Acesso explícito e pessoas separadas de contas | PASS | Todas as operações são escopadas por usuário e projeto; nomes não concedem acesso. |
| V. Qualidade, segurança e experiência | PASS | Há validação, isolamento, contratos, testes, teclado, toque e feedback acessível. |

## Architecture and Delivery Approach

1. Estender o analisador atual para produzir árvore de expressão com texto livre e predicados, posições de diagnóstico e valores normalizados.
2. Criar avaliador sobre a projeção de tarefa autorizada para status, datas, prioridade, seção e responsável, sem modificar a projeção de status.
3. Centralizar no workspace a query bruta, última válida, análise, advertências, contagem e preferências visuais aplicáveis.
4. Trocar o estado independente do funil por leitura e composição da query, preservando texto livre e precedência booleana.
5. Persistir views e histórico em endpoints protegidos; manter estado transitório sem autosave e confirmar somente sobrescrita.
6. Exportar/importar documento versionado, validando forma e resolvendo apenas colisão de nome; referências desconhecidas permanecem texto válido.
7. Cobrir parser/evaluator, autorização, serialização, endpoint, interface e E2E antes de remover o fluxo antigo.

## DSL Canonical

| Categoria | Forma | Exemplos | Semântica |
|---|---|---|---|
| Texto livre | termo existente | `reuniao`, `reuniao*cliente` | Mantém busca textual e curingas no título |
| Conjunção | `&` | `status:aberta & responsavel:eu` | Ambos os lados correspondem |
| Disjunção | `|` | `status:aberta | status:atrasada` | Ao menos um lado corresponde |
| Negação | `!` | `!status:concluida` | Exclui correspondências |
| Agrupamento | `(...)` | `(status:aberta | status:atrasada) & responsavel:eu` | Define precedência |
| Status | `status:valor` | `status:aberta`, `status:em-andamento` | Forma salva/exportada; rótulos de interface podem conter espaços e acentos |
| Responsável | `responsavel:valor` | `responsavel:eu`, `responsavel:sem`, `responsavel:outros`, `responsavel:"Ana Silva"` | Valores especiais são portáveis; nome é tolerante |
| Data | `data:valor` | `data:hoje`, `data:amanha`, `data:proximos-7-dias`, `data:2026-09-22..2026-09-30` | Relativos avaliam no dia civil atual; intervalo fixo é inclusivo |
| Prioridade | `prioridade:valor` | `prioridade:alta`, `prioridade:1` | Mapeia rótulos às prioridades existentes |
| Seção | `secao:valor` | `secao:"Lançamento"`, `secao:sem` | Seção direta da tarefa |

Aliases de apresentação são resolvidos para a mesma semântica canônica. Ajuda documenta escape, aspas e valores com espaço. Dependências e caminho crítico não integram o MVP.

## Project Structure

### Documentation (this feature)

```text
docs/specs/unified-task-views/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── views-api.md
└── interface-spec.md       # etapa 6
```

### Source Code (repository root)

```text
app/
├── Http/Controllers/Api/ProjectController.php
├── Services/ProjectAccess.php
└── Models/
database/migrations/
resources/js/
├── ProjectPlanningPage.vue
├── stores/workspace.ts
├── utils/task-query.ts
├── contracts/workspace-contract.ts
└── types.ts
tests/
├── Feature/LocalProjectsApiTest.php
├── e2e/
└── Unit/
```

**Structure Decision**: A DSL fica junto às utilidades de consulta do frontend porque opera sobre projeção já autorizada. Persistência e contrato de views ficam na borda do projeto, podendo ser extraídos do controlador atual sem mudar a API. Nenhuma regra de calendário ou status é duplicada.

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
|---|---|---|---|
| DB columns | snake_case | migration, constraints, índices e FKs | `database/migrations/` |
| Backend DTO | camelCase | validação de request e presenter | módulo de views |
| Frontend DTO | camelCase | parser de contrato e tipos | `resources/js/contracts/`, `resources/js/types.ts` |
| API payload | camelCase | contrato e testes de integração | `contracts/views-api.md` |
| URL query/path params | kebab-case para recurso; identificadores existentes | rotas e testes | `routes/api.php` |

**Mapper layer (DB <-> DTO)**: presenter ou serializador dedicado do módulo de views converte `snake_case` em `camelCase` e nunca expõe `user_id` ou `project_id` de outra origem.

**Validação de schema**: request no backend, resposta no parser do frontend e ambos em teste de roundtrip. O arquivo importado é validado antes de qualquer escrita.

## Validation Strategy

| Camada | Cobertura |
|---|---|
| Unidade | Precedência, texto, escape, predicados, datas relativas, responsáveis especiais, diagnósticos e referências desconhecidas |
| Estado de workspace | Última query válida, conjunto igual entre modos, composição do funil, restauração e ausência de autosave |
| Integração | Autorização, isolamento usuário/projeto, colisões, importação e serialização |
| Contrato | Resposta real corresponde ao contrato camelCase e parser da SPA a aceita |
| E2E | `/`, autocomplete, funil, ajuda, salvar/sobrescrever, aplicar, exportar/importar, vazio e teclado/toque |
| Regressão | Busca atual, foco de relações, exceções e zoom/hierarquia continuam funcionando |

## Complexity Tracking

Nenhuma violação da constituição foi identificada.
