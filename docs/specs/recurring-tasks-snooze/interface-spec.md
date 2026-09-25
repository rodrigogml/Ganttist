# Interface Specification: Tarefas Recorrentes e Soneca

**Feature**: `recurring-tasks-snooze`
**Created**: 2026-09-24
**Status**: Draft
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [../../architecture/interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
|---|---|---|---|---|---|
| SURF-WEB-OPERATIONS | WEB | Proprietário, editor e leitor | FULL | Editor, indicação de ocorrência, soneca, preview, conclusão e histórico em Gantt e Tarefas | Dependências por ocorrência, recuperação de ocorrências perdidas e relatórios avançados |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
|---|---|---|---|
| SURF-WEB-OPERATIONS | Workspace e `resources/js/ProjectPlanningPage.vue` | Painel da tarefa, menu contextual e conclusão existentes | Edita planejamento, alterna conclusão definitiva e abre ações de contexto; não diferencia regra, ocorrência ou soneca. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
|----------------|------------|------|-------------|------|-------------|
| INT-WEB-001 | SURF-WEB-OPERATIONS | PANEL | MODIFIED | Editor de recorrência | Painel da tarefa, seção Planejamento |
| INT-WEB-002 | SURF-WEB-OPERATIONS | MENU AND DIALOG | NEW | Soneca e preview de impacto | Menu contextual, linha da tarefa ou painel |
| INT-WEB-003 | SURF-WEB-OPERATIONS | MENU AND DIALOG | MODIFIED | Conclusão e histórico de ocorrência | Controle de conclusão, menu contextual e painel |
| INT-WEB-004 | SURF-WEB-OPERATIONS | WORKSPACE ROW | MODIFIED | Estado recorrente em Gantt e Tarefas | Linhas, barras, filtros e seleção existentes |

## Interaction Details

### INT-WEB-001 — Editor de recorrência

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: Permitir configurar, revisar, remover ou encerrar uma série sem confundir regra permanente com a ocorrência aberta.
**Actors and Permissions**: Proprietário e editor podem alterar; leitor vê a seção em modo somente leitura e não vê controles de escrita.
**Entry and Navigation**: A seção `Recorrência` fica no painel de tarefa após planejamento e antes de conclusão. Ativar recorrência abre o editor; `Esc` descarta somente o rascunho não salvo e retorna ao controle que o abriu.
**Content and Data**: Alternância sem recorrência/recorrente; expressão natural; resumo canônico; escolha visual de frequência, intervalo e unidade (`dias úteis`, `semanas`, `meses` ou `anos`), dias, ordinal, início e término; base fixa ou por intervalo; prévia da próxima data lógica; diferença entre data lógica e agendamento atual; ações `Salvar recorrência`, `Remover recorrência` e `Concluir definitivamente` quando aplicáveis. O editor explica que somente intervalos em dias contam dias úteis; unidades de calendário preservam a data lógica e podem normalizar apenas o agendamento.
**Actions and Behavior**: Texto e controles visuais atualizam o mesmo rascunho. Expressão válida mostra interpretação antes de salvar; editar regra não é apresentado como soneca. Remover mantém a tarefa aberta; concluir definitivamente exige confirmação e encerra a série. O formulário possui uma única confirmação padrão de salvar.
**Validation and Feedback**: Erro de expressão identifica o trecho e preserva o texto. Campos incompatíveis, início posterior ao término, término sem ocorrência elegível e dependência que tornaria a tarefa predecessora recorrente impedem salvar com motivo acionável. Sucesso atualiza a ocorrência e anuncia a regra canônica.
**Responsive/Adaptive Behavior**: Desktop distribui expressão e editor visual em duas áreas da seção. Tablet empilha resumo após os controles. Telefone abre a edição em folha do painel, prioriza expressão, resumo e frequência; seletoras extensas ocupam largura inteira e ações ficam no rodapé visível acima do teclado virtual.
**Accessibility**: A seção é um `region` rotulado. A expressão possui descrição de exemplos e diagnóstico; interpretação é anunciada apenas após pausa ou ação explícita. Controles de dia usam grupos nomeados, não apenas cor. Foco inicial vai para expressão ao ativar; erro recebe foco após submissão; confirmação destrutiva contém e devolve foco. `Ctrl/Cmd+Enter` salva somente dentro deste formulário.
**Localization**: Rótulos e diagnósticos são pt-BR; a gramática aceita aliases publicados e mostra expressão canônica localizada. Datas usam o locale e timezone de planejamento; texto da tarefa nunca é interpretado automaticamente como regra.
**Components and Design System**: Reutiliza painel, campos, selects, DateInput, tabs, ajuda contextual, diálogo e toast existentes. O formulário usa `v-default-form` e `DefaultSubmitButton`; remover usa tokens de perigo e concluir definitivamente exige confirmação explícita.
**Integration and Contracts**: Usa interpretar, criar/alterar/remover/encerrar em [recurrence-api.md](contracts/recurrence-api.md); respostas atualizam o workspace autoritativo e nunca calculam regra no cliente.
**Telemetry**: Registrar abertura, origem visual/expressão, interpretação inválida, salvar, remover, iniciar/confirmar/cancelar encerramento, sem expressão, título, identificador ou datas da tarefa.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-001.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|-------|-----------------------|-------------------|-----------------|
| initial | Tarefa sem regra mostra ação Ativar recorrência | Ativar ou continuar editando tarefa | Ativar abre loading/ready |
| loading | Interpretação ou regra existente carrega sem bloquear painel | Cancelar rascunho | Ready ou remote-error |
| empty | Sem regra, com explicação curta e exemplos | Ativar recorrência | Ready |
| ready | Rascunho, resumo e próxima ocorrência claros | Editar, salvar, remover, concluir definitivamente | Processing ou validation-error |
| processing | Botão de confirmação indica progresso; rascunho fica estável | Cancelar apenas antes do envio | Success ou remote-error |
| success | Toast e resumo refletem regra/ocorrência confirmadas | Continuar ou fechar painel | Ready |
| validation-error | Mensagem junto ao campo; rascunho preservado | Corrigir e reenviar | Ready/processing |
| remote-error | Erro seguro e nova tentativa sem perder rascunho | Tentar novamente ou cancelar | Processing/ready |
| offline | Controles de escrita desabilitados com motivo; regra lida permanece visível | Consultar, fechar | Reconexão restaura ready |
| access-denied | Painel vira somente leitura ou fecha se acesso foi removido | Voltar ao workspace | Estado global trata saída |
| partial-stale | Banner informa que regra/ocorrência pode ter mudado; salvar exige atualização | Atualizar ou descartar | Workspace atualizado entra em ready |

### INT-WEB-002 — Soneca e preview de impacto

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: Reagendar apenas a ocorrência aberta com decisão informada sobre consequências no planejamento.
**Actors and Permissions**: Proprietário e editor podem solicitar e confirmar; leitor vê indicação de soneca, mas não abre ações de escrita.
**Entry and Navigation**: `Adiar ocorrência` aparece no menu contextual, no painel e na linha selecionada. O menu oferece Amanhã, Próxima semana, 3 dias, 7 dias e Data escolhida. Qualquer escolha abre preview; `Esc`, Voltar ou Cancelar preserva o estado persistido.
**Content and Data**: Data lógica, agendamento atual, alvo normalizado, duração preservada, resumo de sucessoras afetadas, alteração de término finito, criticidade e violações. Severidade é apresentada por texto, ícone e cor. Preview crítico destaca consequência antes da confirmação.
**Actions and Behavior**: Escolher opção solicita preview; data não útil informa o primeiro dia útil adotado. Confirmar aplica somente a ocorrência corrente; cancelar não escreve. Preview vencido por alteração concorrente pede atualização e nova revisão. Uma soneca não abre nova ocorrência nem muda regra.
**Validation and Feedback**: Alvo inválido, cursor substituído, preview obsoleto ou falta de permissão mostram motivo e preservam a tarefa atual. Confirmação de impacto crítico requer ação explícita `Confirmar soneca`; impacto informativo ou alerta continua visível sem bloquear cancelamento.
**Responsive/Adaptive Behavior**: Desktop usa menu ancorado e diálogo lateral/central com diff antes/depois. Tablet mantém diálogo amplo e lista rolável. Telefone abre folha de tela quase inteira: escolhas grandes no topo, impacto rolável no meio e Cancelar/Confirmar fixos no rodapé; nenhuma ação depende de hover ou arrastar.
**Accessibility**: Menu é navegável por setas, Enter e Espaço. Preview é diálogo rotulado, foco inicial no título e foco final devolvido ao acionador. Lista de impactos tem contagem e severidade textual; mudança de data normalizada e sucesso são anunciados. Alvos touch respeitam tamanho do design system; Esc sempre cancela antes de persistir.
**Localization**: Termos canônicos: `Adiar ocorrência`, `Amanhã`, `Próxima semana`, `dias úteis`, `Data lógica`, `Agendada para` e `Impacto no planejamento`. Datas são localizadas; números de tarefas e dias pluralizam em pt-BR.
**Components and Design System**: Reutiliza menu contextual, diálogo, DateInput, badges de status, toast e tokens existentes. Cancelar usa ação secundária; confirmação usa `DefaultSubmitButton` dentro de `v-default-form`; não introduz cor primária nova.
**Integration and Contracts**: Consome preview e confirmação em [recurrence-api.md](contracts/recurrence-api.md). Enquanto o preview está aberto, a interface conserva o token e revisão recebidos; confirmação revalida no servidor e recarrega workspace após sucesso ou conflito.
**Telemetry**: Registrar abertura, opção escolhida, normalização de data, severidade agregada, confirmação, cancelamento e conflito obsoleto; nunca enviar título, data, IDs ou lista de tarefas afetadas.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-002.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|-------|-----------------------|-------------------|-----------------|
| initial | Ação de adiar disponível para ocorrência aberta | Escolher atalho ou data | Loading |
| loading | Opção selecionada e indicador de cálculo; workspace permanece legível | Cancelar | Ready/remote-error |
| empty | N/A — ocorrência aberta sempre possui um alvo de preview | N/A — motivo | Retorna a initial |
| ready | Preview antes/depois e impacto classificados | Confirmar ou cancelar | Processing/initial |
| processing | Confirmação desabilita repetição e mostra progresso | Aguardar | Success/remote-error |
| success | Toast mostra data efetiva e linha/painel atualizados | Continuar | Initial |
| validation-error | Alvo ou opção inválida explicada sem escrita | Corrigir data ou escolher outra opção | Loading |
| remote-error | Erro seguro preserva preview quando ainda válido | Tentar novamente ou cancelar | Processing/initial |
| offline | Ações de soneca indisponíveis com explicação | Fechar diálogo | Reconexão restaura initial |
| access-denied | Diálogo fecha e informa que não há permissão | Voltar ao workspace | Estado global trata saída |
| partial-stale | Preview é marcado obsoleto e confirmação fica indisponível | Atualizar e recalcular ou cancelar | Loading/initial |

### INT-WEB-003 — Conclusão e histórico de ocorrência

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: Concluir a ocorrência correta uma vez e permitir entender execuções anteriores sem tratar a série ativa como tarefa concluída.
**Actors and Permissions**: Proprietário e editor concluem e encerram; leitor consulta histórico paginado e estado da ocorrência.
**Entry and Navigation**: O controle de conclusão existente passa a dizer `Concluir ocorrência` em recorrentes. O painel oferece `Histórico de ocorrências`; o menu contextual oferece `Concluir definitivamente`. Histórico abre em seção expansível/painel secundário e retorna ao painel da tarefa ao fechar.
**Content and Data**: Confirmação identifica data lógica e agendada; campo opcional de data efetiva; resultado mostra próxima ocorrência ou encerramento. Histórico lista data lógica, agendada, concluída, regra daquele momento e autor quando disponível; paginação busca itens anteriores sob demanda.
**Actions and Behavior**: Concluir envia o cursor exibido. Repetição do mesmo comando é absorvida e mostra o resultado já aplicado; conflito de cursor atualiza a tarefa antes de permitir outra conclusão. Concluir definitivamente requer confirmação separada. Histórico não possui ações de alteração.
**Validation and Feedback**: Data efetiva inválida, ocorrência substituída ou permissão removida impedem conclusão com mensagem clara. A tarefa não mostra estado concluído entre uma conclusão normal e a chegada da próxima ocorrência; feedback anuncia a nova data. Falha de página do histórico mantém itens já carregados e opção de tentar novamente.
**Responsive/Adaptive Behavior**: Desktop mostra histórico como aba/seção lateral sem ocultar contexto. Tablet empilha histórico abaixo da ocorrência. Telefone abre painel de histórico com cabeçalho fixo, cartões compactos e paginação por botão explícito; controles de conclusão permanecem acessíveis no painel principal.
**Accessibility**: Botão de conclusão anuncia ocorrência e data. A confirmação foca data efetiva e possui descrição da próxima data prevista. Histórico é lista semântica, com cada item anunciado em ordem lógica/agendada/concluída; carregamento adicional não desloca foco. Conflito e sucesso usam região de status.
**Localization**: Usa `Concluir ocorrência`, `Concluir definitivamente`, `Histórico de ocorrências`, `Data lógica`, `Agendada` e `Concluída em`; datas seguem pt-BR e autor ausente é anunciado como não disponível.
**Components and Design System**: Reutiliza checkbox/ação de conclusão, diálogo, painel, lista, paginação e toast. Conclusão normal usa ação primária de confirmação; concluir definitivamente usa confirmação com distinção visual de ação irreversível, sem reutilizar a soneca como atalho.
**Integration and Contracts**: Usa conclusão e histórico em [recurrence-api.md](contracts/recurrence-api.md); após retorno idempotente, conflito ou sucesso recarrega a projeção da tarefa para não assumir cursor no cliente.
**Telemetry**: Registrar abertura de histórico, página adicional, iniciar/concluir/repetir/conflitar conclusão e iniciar/confirmar/cancelar conclusão definitiva, sem datas, autores, títulos ou IDs.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-003.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|-------|-----------------------|-------------------|-----------------|
| initial | Controle identifica tarefa/ocorrência selecionada | Concluir ou abrir histórico | Loading/ready |
| loading | Confirmação ou histórico mostra carregamento localizado | Cancelar carregamento seguro | Ready/remote-error |
| empty | Histórico explica que ainda não há conclusões | Fechar ou concluir ocorrência | Initial |
| ready | Ocorrência e histórico carregado são legíveis | Concluir, paginar, fechar | Processing/loading |
| processing | Ação de conclusão bloqueia repetição visual | Aguardar | Success/validation-error |
| success | Próxima ocorrência ou encerramento é anunciado | Continuar | Initial/ready |
| validation-error | Data/cursor inválido é explicado e dados atuais são preservados | Corrigir ou atualizar | Ready/loading |
| remote-error | Itens anteriores permanecem; ação pode ser repetida | Tentar novamente ou fechar | Loading/ready |
| offline | Concluir fica indisponível; histórico já carregado segue legível | Consultar ou fechar | Reconexão restaura ready |
| access-denied | Ações de escrita somem; painel informa acesso somente leitura | Fechar/voltar | Estado global trata saída |
| partial-stale | Cursor exibido tem aviso; concluir exige atualização | Atualizar ou fechar | Loading/initial |

### INT-WEB-004 — Estado recorrente em Gantt e Tarefas

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: Tornar a ocorrência atual compreensível no contexto de planejamento sem fazer uma rotina contínua parecer parte do término finito do projeto.
**Actors and Permissions**: Proprietário, editor e leitor consultam a mesma projeção; somente os dois primeiros veem comandos editáveis.
**Entry and Navigation**: A informação aparece na linha e barra existentes do Gantt e da view Tarefas. Selecionar a linha abre o painel já existente; filtros e busca continuam operando sobre o estado da ocorrência corrente.
**Content and Data**: Ícone e texto de recorrência; regra curta; data lógica; `Adiada para` quando planejamento difere; estado operacional, bloqueio e indicação de que a rotina não compõe indicadores finitos. Gantt posiciona a barra pela ocorrência corrente; Tarefas usa os mesmos valores em coluna/resumo.
**Actions and Behavior**: Seleção, abertura de contexto e navegação temporal preservam comportamento atual. Clique no indicador abre o painel da tarefa, não edita silenciosamente. Filtros por estado usam o estado da ocorrência; contagens finitas de projeto permanecem separadas de contagens operacionais.
**Validation and Feedback**: Dados ausentes ou snapshot desatualizado mostram placeholder e ação de atualizar, sem inferir recorrência no cliente. Incompatibilidade de dependência aparece no painel/ação que a causou; a linha permanece legível.
**Responsive/Adaptive Behavior**: Desktop mostra ícone, regra curta e tooltip. Tablet preserva ícone e texto resumido. Telefone mostra ícone, estado e uma linha `Recorrente · adiada para ...` no cartão/linha; detalhes completos ficam no painel, sem exigir hover.
**Accessibility**: Ícone possui nome acessível que inclui regra e soneca; texto visível não depende somente do ícone. Linha mantém árvore, foco e relacionamento com a barra; tooltip tem alternativa por foco/toque. Indicadores de projeto finito usam texto explicativo, contraste e não apenas cor.
**Localization**: Termos canônicos são `Recorrente`, `Ocorrência`, `Data lógica`, `Adiada para`, `Não compõe o término do projeto` e nomes localizados de dias. Formatação segue pt-BR.
**Components and Design System**: Reutiliza linha virtualizada, barra Gantt, badges, tooltip, estado e tokens existentes. Cria marcador semântico reutilizável de recorrência/soneca; não cria nova paleta de botão.
**Integration and Contracts**: Consome extensão de workspace de [recurrence-api.md](contracts/recurrence-api.md) através do contrato de workspace; store substitui a projeção inteira após mutações e mantém a última projeção válida em erro.
**Telemetry**: Registrar visualização de indicador, abertura de painel por indicador e filtro operacional por estado, sem regra, data, título, IDs ou lista de tarefas.
**Wireframe Requirement**: OPTIONAL
**Wireframe**: N/A — alteração incremental de linha e barra, detalhada nos wireframes do editor e preview.

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|-------|-----------------------|-------------------|-----------------|
| initial | Linha reserva espaço para indicador quando dados carregarem | Navegar no workspace | Loading/ready |
| loading | Linha/skeleton preserva geometria atual | Navegar ou voltar | Ready/remote-error |
| empty | N/A — cada linha representa tarefa existente | N/A — motivo | Workspace decide visibilidade |
| ready | Regra curta, ocorrência e soneca quando aplicável | Selecionar, abrir painel/contexto conforme papel | Painel ou processing |
| processing | Linha mantém dados confirmados e marca ação em curso | Ler outras tarefas | Success/remote-error |
| success | Projeção atualizada e anúncio curto | Continuar planejando | Ready |
| validation-error | N/A — validação ocorre na interação que editou a recorrência | N/A — motivo | Painel apresenta correção |
| remote-error | Última projeção válida permanece; toast orienta atualização | Tentar atualizar | Loading/ready |
| offline | Snapshot disponível é marcado como possivelmente antigo; escrita indisponível | Ler, navegar, atualizar | Reconexão restaura ready |
| access-denied | Workspace não expõe linha/dados do projeto inacessível | Voltar | Navegação global trata saída |
| partial-stale | Banner e marca discreta distinguem projeção antiga | Atualizar ou continuar leitura | Loading/ready |

## Cross-Surface Rules

### Navigation and Parity

Há uma única superfície web responsiva. Gantt e Tarefas mostram a mesma ocorrência e usam os mesmos comandos; desktop muda densidade, não significado. Soneca, edição e conclusão retornam ao item de origem quando possível e recarregam a projeção autorizada antes de aceitar outra ação dependente do cursor.

### Shared Content and Terminology

`Regra` descreve a série; `Ocorrência` é o trabalho aberto; `Data lógica` pertence à regra; `Agendada para` descreve soneca ou reagendamento; `Concluir definitivamente` encerra a série. `Amanhã` sempre significa próximo dia útil. `Próxima semana` sempre significa primeiro dia útil da semana seguinte.

### Shared Accessibility and Input

Todo fluxo crítico possui mouse, teclado e toque. Foco visível, retorno de foco, anúncio de estado dinâmico, contraste, zoom e redução de movimento seguem o baseline do workspace. Nenhuma ação de persistência é ativada por atalho fora do formulário focado; o editor usa a ação padrão definida no projeto.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
|----------------|--------------|-------------------------|------------------|-----------|
| INT-WEB-001 | US-001, US-005 | FR-001–008, FR-012–013, FR-021, FR-024–025, FR-027 | SC-001, SC-006 | [recurrence-api.md](contracts/recurrence-api.md) |
| INT-WEB-002 | US-002, US-004 | FR-014–019, FR-023–025 | SC-002, SC-005–007 | [recurrence-api.md](contracts/recurrence-api.md) |
| INT-WEB-003 | US-003, US-005 | FR-009–012, FR-024–025, FR-027 | SC-003–004, SC-006 | [recurrence-api.md](contracts/recurrence-api.md) |
| INT-WEB-004 | US-004, US-005 | FR-020–025 | SC-005–007 | [recurrence-api.md](contracts/recurrence-api.md) |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
|----------------|-------------|----------|-------|
| INT-WEB-001 | REQUIRED | wireframes/int-web-001.md | Editor, regra e adaptação móvel |
| INT-WEB-002 | REQUIRED | wireframes/int-web-002.md | Atalhos e preview antes/depois |
| INT-WEB-003 | REQUIRED | wireframes/int-web-003.md | Conclusão e histórico |
| INT-WEB-004 | OPTIONAL | N/A | Mudança incremental de representação |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: yes
- Placeholders or open decisions remaining: 0
