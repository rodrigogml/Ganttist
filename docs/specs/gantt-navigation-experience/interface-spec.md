# Interface Specification: Navegação e Experiência Gantt

**Feature**: `gantt-navigation-experience`
**Created**: 2026-08-17
**Updated**: 2026-08-19
**Status**: Ready for implementation
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
|---|---|---|---|---|---|
| SURF-WEB-OPERATIONS | WEB | Planejador | FULL | árvore, timeline, seleção, teclado, busca, filtros, responsividade e acessibilidade | minimapa e filtros salvos |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
|---|---|---|---|
| SURF-WEB-OPERATIONS | `App.vue`, `workspace.ts` | inspeção e homologação | controle de árvore pouco evidente; clique abre edição; seleção desloca o layout; scroll dos painéis diverge; viewport é fixo; linhas da timeline não têm chave estável. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
|---|---|---|---|---|---|
| INT-NAV-001 | SURF-WEB-OPERATIONS | SCREEN | MODIFIED | Árvore e timeline Gantt | workspace |
| INT-NAV-002 | SURF-WEB-OPERATIONS | PANEL | NEW | Busca e filtros | barra do sistema/atalho |

## Interaction Details

### INT-NAV-001 — Árvore e timeline Gantt

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: explorar cronogramas grandes mantendo linhas, tempo, seleção e contexto alinhados.
**Actors and Permissions**: dono do Gantt.
**Entry and Navigation**: workspace; atalhos para hoje, item selecionado e resultados.
**Content and Data**: árvore, escala temporal, grupos, barras, conectores, cursor e itens ocultos.
**Actions and Behavior**:

- Itens com filhos apresentam controle expandir/recolher com estado e rótulo acessíveis.
- Clique simples no corpo da linha ou barra move apenas o cursor, sem alterar a seleção ou abrir a edição.
- Seleção pelo mouse ocorre exclusivamente nos checkboxes das tarefas: clique alterna um item e `Shift+clique` adiciona o intervalo desde o último checkbox acionado. Tarefas Todoist que possuem subtarefas continuam sendo tarefas, mantêm checkbox e também apresentam o controle de expandir/recolher. Seções Todoist são estruturais, não apresentam checkbox e são ignoradas pelo intervalo. A interação não seleciona texto do Gantt.
- Todo nó com descendentes apresenta um chevron nomeado e visível. A indentação desenha conectores de árvore; ao apontar uma tarefa, seus ancestrais e o caminho de conectores até eles recebem destaque sem substituir o destaque da linha apontada.
- O último checkbox acionado sem `Shift` torna-se âncora; intervalos sucessivos preservam essa âncora. O último item clicado ou marcado torna-se cursor.
- Setas acima/abaixo movem o cursor e garantem visibilidade; esquerda/direita rolam a timeline; Espaço alterna a linha do cursor.
- Edição é ação explícita da barra contextual e fica disponível para exatamente um item.
- A barra contextual fica dentro do cartão, fixa acima da legenda, e não desloca o gráfico na página.
- Cada item é uma única linha virtualizada que contém a célula de tarefa fixa à esquerda e sua faixa temporal. Um único scroller controla os dois e não existe coleção de linhas espelhada.
- Quando o próprio gráfico está focado, setas e Espaço mantêm a mesma semântica da lista. `Shift+Tab` transfere o foco para a linha do cursor sem criar um segundo deslocamento vertical.
- Hover destaca a linha inteira nos dois painéis e se sobrepõe ao destaque de seleção; cursor usa borda esquerda distinta.
- Cada linha virtualizada usa o ID do item como chave estável.

**Validation and Feedback**: navegação não altera dados; foco fora da janela é revelado; seleção e cursor não dependem apenas de cor.
**Responsive/Adaptive Behavior**: desktop usa toda a largura e a altura restantes do viewport, inclusive 4K; tablet prioriza timeline; telefone alterna árvore/timeline.
**Accessibility**: árvore semântica, `aria-level`, `aria-expanded`, seleção anunciada, foco roving, controles nomeados e movimento reduzido.
**Localization**: datas e cabeçalhos no locale.
**Components and Design System**: renderer virtualizado, árvore, toolbar, barra contextual e legenda.
**Integration and Contracts**: projeção do workspace e eventos reconciliados; atualizações preservam cursor/seleção de IDs existentes.
**Telemetry**: zoom, navegação, seleção e degradação de renderização sem conteúdo das tarefas.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-nav-001.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | estrutura reservada | aguardar | loading |
| loading | skeleton | voltar | ready/error |
| empty | projeto/filtro vazio | limpar/voltar | ready |
| ready | janela virtualizada | navegar/selecionar/zoom | loading |
| processing | movimento indicado | cancelar | ready |
| success | item focalizado | continuar | ready |
| validation-error | ação inválida explicada | corrigir/cancelar | ready |
| remote-error | falha de carga | recarregar | loading |
| offline | último estado marcado | retentar | loading |
| access-denied | sem projeto/sessão | login | initial |
| partial-stale | projeção antiga | reconciliar | loading |

### INT-NAV-002 — Busca e filtros

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: localizar tarefa e reduzir ruído sem remover contexto.
**Actors and Permissions**: dono do Gantt.
**Entry and Navigation**: busca, toolbar e deep link.
**Content and Data**: termo, resultados, filtros, contagens e relações ocultas.
**Actions and Behavior**: pesquisar, filtrar, limpar, focalizar, revelar contexto e dependência.
**Validation and Feedback**: vazio difere de erro; filtros são removíveis e não alteram dados.
**Responsive/Adaptive Behavior**: popover desktop e painel no telefone.
**Accessibility**: foco no campo, resultados anunciados e navegação por teclado.
**Localization**: textos localizáveis.
**Components and Design System**: busca, chips, lista de resultados e badges.
**Integration and Contracts**: projeção paginável; relações ocultas são indicadas.
**Telemetry**: busca, filtro e foco sem conteúdo pesquisado.
**Wireframe Requirement**: N/A
**Wireframe**: N/A — painel complementar.

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | fechado | abrir | ready |
| loading | resultados em carga | cancelar | ready/error |
| empty | sem resultados | limpar/editar | ready |
| ready | resultados e filtros | focalizar/aplicar/remover | loading |
| processing | foco em curso | aguardar | success |
| success | resultado focalizado | continuar | ready |
| validation-error | termo inválido explicado | corrigir | ready |
| remote-error | busca indisponível | retentar | loading |
| offline | resultado local sinalizado | retentar | loading |
| access-denied | sem workspace | login | initial |
| partial-stale | resultado antigo | reconciliar | loading |

## Cross-Surface Rules

Filtros alteram apenas visibilidade. Relações e grupos permanecem no modelo, e toda ponta oculta oferece caminho de inspeção. Atualizações remotas preservam seleção e cursor somente para IDs existentes.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
|---|---|---|---|---|
| INT-NAV-001 | US-3 | FR-005–008 | SC-003 | workspace/preferências/eventos |
| INT-NAV-002 | US-1–2 | FR-001–004 | SC-001–002 | busca/filtros |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
|---|---|---|---|
| INT-NAV-001 | REQUIRED | wireframes/int-nav-001.md | desktop amplo e telefone |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: yes
- Placeholders or open decisions remaining: 0
