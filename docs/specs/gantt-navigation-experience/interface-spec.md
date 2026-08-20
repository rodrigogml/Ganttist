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
| INT-NAV-003 | SURF-WEB-OPERATIONS | PANEL | MODIFIED | Editor seguro de tarefa | duplo clique, Enter ou barra contextual |
| INT-NAV-004 | SURF-WEB-OPERATIONS | FLOW | MODIFIED | Movimentação civil de timeblock | arraste horizontal da tarefa |

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
- Clique duplo no corpo de uma tarefa ou em sua faixa temporal abre a edição sem alterar a seleção; seções estruturais não são editáveis. `Enter` no cursor e a ação explícita da barra contextual continuam disponíveis.
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

### INT-NAV-003 — Editor seguro de tarefa

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: editar uma tarefa sem perda silenciosa de dados e permitir consulta simultânea ao Gantt em telas amplas.
**Actors and Permissions**: dono do Gantt; somente linhas `task` são editáveis.
**Entry and Navigation**: duplo clique na linha ou faixa temporal, `Enter` sobre o cursor ou ação Editar para uma única seleção. O foco inicial segue para o título; ao fechar, retorna à linha que abriu o painel quando ela ainda estiver renderizada.
**Content and Data**: campos editáveis da tarefa, origem Todoist, dependências, exclusão, estado de alterações não salvas, modo flutuante/fixado e largura do painel.
**Actions and Behavior**:

- O painel flutuante entra pela direita, sem backdrop ou desfoque, não fecha por clique externo e mantém o workspace disponível para consulta.
- X e Cancelar solicitam fechamento. Sem alterações, fecham imediatamente; com alterações não salvas, oferecem `Continuar editando`, `Descartar alterações` e `Salvar alterações`.
- Abrir outra tarefa enquanto há alterações pendentes usa a mesma confirmação antes da troca. Recarregar ou abandonar a página aciona a proteção nativa do navegador.
- O botão de pin, imediatamente antes do X, alterna entre painel flutuante e painel incorporado ao layout.
- Fixado em desktop, o editor ocupa uma coluna à direita e reduz a área principal sem cobrir gráfico ou barra superior. Um separador acessível ajusta a largura entre 390 px e 50% da viewport; a largura também responde a teclado.
- Em viewport menor que 780 px o editor permanece sobreposto e ocupa a largura disponível; o pin não produz uma divisão inviável.
- Salvar só encerra o editor após sucesso. Falha remota preserva o rascunho e informa o erro.

**Validation and Feedback**: estado alterado é calculado comparando os campos editáveis com o snapshot de abertura; a confirmação identifica claramente a consequência de cada ação.
**Responsive/Adaptive Behavior**: desktop e widescreen oferecem modo fixado redimensionável; tablet estreito e telefone usam drawer sobreposto sem backdrop. Pointer arrasta o separador; teclado usa setas, Home e End.
**Accessibility**: `role="dialog"`, título associado, botões nomeados, pin com `aria-pressed`, separador com `role="separator"` e valores, confirmação com `role="alertdialog"`; não fecha por Escape ou clique acidental.
**Localization**: mensagens e rótulos em pt-BR; datas continuam civis no formato do controle do navegador.
**Components and Design System**: drawer existente, botões `soft-btn`/`primary`/`danger-btn`, novo guardião de alterações e separador de painel.
**Integration and Contracts**: atualização existente de tarefa Todoist, simulação de agenda e override de conclusão; nenhuma alteração de payload externo.
**Telemetry**: abertura por origem, descarte confirmado e modo fixado, sem conteúdo dos campos.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-nav-003.md
**Traceability**: US-3; FR-006, FR-007 e FR-008; contratos de tarefa e simulação existentes.

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | painel fechado | abrir por duplo clique, Enter ou Editar | ready |
| loading | N/A — rascunho parte da projeção já carregada | N/A | N/A |
| empty | N/A — seções e ausência de tarefa não abrem o editor | retornar ao workspace | initial |
| ready | painel flutuante ou fixado, rascunho intacto | editar, fixar, redimensionar, salvar, cancelar | processing/success/validation-error |
| processing | ação de salvar/excluir indicada e controles críticos bloqueados | aguardar | success/remote-error |
| success | painel fechado e projeção atualizada ou simulação criada | continuar no workspace | initial |
| validation-error | confirmação de alterações ou campo inválido dentro do painel | continuar, descartar ou corrigir | ready/initial |
| remote-error | mensagem de falha com rascunho preservado | corrigir e salvar novamente | processing |
| offline | último rascunho permanece no painel | aguardar conexão ou descartar conscientemente | ready |
| access-denied | sessão expirada redireciona ao acesso conforme regra global | refazer login | initial |
| partial-stale | projeção remota antiga sinalizada sem sobrescrever rascunho | salvar ou reconciliar depois | processing/ready |

### INT-NAV-004 — Movimentação civil de timeblock

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: alterar a data Todoist de uma tarefa diretamente no Gantt com feedback preciso, reversível durante o gesto e limitado a dias civis inteiros.
**Actors and Permissions**: dono do Gantt; somente tarefas folha são arrastáveis, enquanto seções e intervalos derivados permanecem imóveis.
**Entry and Navigation**: pointer down sobre o timeblock inicia o gesto; movimento horizontal escolhe a coluna de destino; pointer up confirma; `Esc` ou `pointercancel` cancela.
**Content and Data**: data inicial persistida ou referência visual de hoje, deadline explícito quando houver, largura de coluna, delta em dias, ghost e estado de sincronização.
**Actions and Behavior**:

- Durante o gesto, o timeblock original não se move nem é exibido; apenas um ghost sem texto acompanha horizontalmente o pointer dentro da própria linha.
- O ghost encaixa em colunas de dias civis inteiros. Movimento vertical não troca a tarefa de linha e horas/frações de dia não fazem parte do MVP.
- No drop, a nova data inicial é persistida imediatamente no Todoist. Se existir deadline explícito, ele recebe o mesmo delta civil e preserva a duração; sem deadline, continua ausente.
- Enquanto a escrita está em andamento, o ghost permanece no destino. No sucesso, a projeção reconciliada assume sua posição; na falha, a barra original reaparece e a mensagem permite tentar novamente.
- `Esc` durante o gesto restaura a representação original sem chamada externa.
- Timeblocks não exibem título ou outro texto interno e ocupam múltiplos inteiros da largura da coluna, do início ao deadline inclusive; sem deadline, ocupam exatamente uma coluna.
- Tarefas sem data persistida continuam com estado não programado, mas recebem um timeblock provisório de uma coluna na faixa de hoje. Arrastá-lo para um destino persiste a primeira data; antes disso, a representação não altera o Todoist.

**Validation and Feedback**: servidor revalida pertencimento da tarefa e deriva o deadline da fonte Todoist; a UI nunca envia duração como autoridade. Falha não deixa mutação otimista residual.
**Responsive/Adaptive Behavior**: pointer fino em desktop; em touch o mesmo gesto respeita eixo horizontal e alvos adequados. Alternativa de edição por campos permanece no painel.
**Accessibility**: timeblock possui nome acessível sem texto visível; `Esc` cancela; edição por formulário oferece alternativa completa ao drag.
**Localization**: datas são civis `YYYY-MM-DD`; “hoje” usa o timezone do workspace.
**Components and Design System**: timeblock, ghost de drag, toast e projeção reconciliada existentes.
**Integration and Contracts**: `PUT /api/v1/tasks/{taskId}/dates` com data inicial e `commandId`; adapter Todoist atualiza data e deadline calculado.
**Telemetry**: início, cancelamento, delta e resultado do drag, sem título da tarefa.
**Wireframe Requirement**: N/A
**Wireframe**: N/A — mudança gestual dentro da linha existente, descrita por estados e testes visuais.
**Traceability**: US-3; FR-005–008; contrato de tarefa Todoist.

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | timeblock persistido ou provisório em hoje | iniciar drag ou editar por formulário | ready |
| loading | ghost fixo no destino enquanto Todoist responde | aguardar | success/remote-error |
| empty | tarefa sem data usa bloco provisório em hoje | arrastar para definir data | loading |
| ready | ghost encaixado na coluna sob o pointer | mover, soltar ou cancelar | loading/initial |
| processing | equivalente a loading para escrita direta | aguardar | success/remote-error |
| success | projeção reconciliada na data persistida | continuar | initial |
| validation-error | destino ou tarefa não editável explicado | corrigir | initial |
| remote-error | barra original restaurada e erro informado | tentar novamente | ready |
| offline | drop falha sem alterar a fonte | tentar após reconexão | ready |
| access-denied | regra global de sessão redireciona ao login | refazer login | initial |
| partial-stale | atualização remota posterior vence na reconciliação | revisar posição | initial |

## Cross-Surface Rules

Filtros alteram apenas visibilidade. Relações e grupos permanecem no modelo, e toda ponta oculta oferece caminho de inspeção. Atualizações remotas preservam seleção e cursor somente para IDs existentes.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
|---|---|---|---|---|
| INT-NAV-001 | US-3 | FR-005–008 | SC-003 | workspace/preferências/eventos |
| INT-NAV-002 | US-1–2 | FR-001–004 | SC-001–002 | busca/filtros |
| INT-NAV-003 | US-3 | FR-006–008 | SC-003 | tarefa/simulação/Todoist |
| INT-NAV-004 | US-3 | FR-005–008 | SC-003 | tarefa/datas/Todoist |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
|---|---|---|---|
| INT-NAV-001 | REQUIRED | wireframes/int-nav-001.md | desktop amplo e telefone |
| INT-NAV-003 | REQUIRED | wireframes/int-nav-003.md | painel flutuante, fixado e confirmação de descarte |
| INT-NAV-004 | N/A | N/A | gesto e estados na linha existente; sem alteração estrutural |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: yes
- Placeholders or open decisions remaining: 0
