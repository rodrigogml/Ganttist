# Interface Specification: Workspace de Projeto Gantt

**Feature**: `gantt-workspace`
**Created**: 2026-08-17
**Status**: Draft
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
|---|---|---|---|---|---|
| SURF-WEB-OPERATIONS | WEB | UsuÃ¡rio autenticado | FULL | seleÃ§Ã£o de projeto, Ã¡rvore, timeline e estados do workspace | mÃºltiplos projetos em uma sÃ³ timeline |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
|---|---|---|---|
| SURF-WEB-OPERATIONS | `resources/js/App.vue`, `WorkspaceController` | auditoria | Ã¡rvore/timeline bÃ¡sica com fixture e projeÃ§Ã£o simplificada. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
|---|---|---|---|---|---|
| INT-WORKSPACE-001 | SURF-WEB-OPERATIONS | SCREEN | MODIFIED | Workspace de projeto | projeto selecionado |
| INT-WORKSPACE-002 | SURF-WEB-OPERATIONS | DIRECT MANIPULATION | NEW | Resize temporal do timeblock | hover, foco ou toque no timeblock |
| INT-WORKSPACE-003 | SURF-WEB-OPERATIONS | DIRECT MANIPULATION | NEW | Criação gráfica de dependência | endpoint externo do timeblock |
| INT-WORKSPACE-004 | SURF-WEB-OPERATIONS | PANEL | MODIFIED | Relações no editor de tarefa | duplo clique ou comando Editar |
| INT-WORKSPACE-005 | SURF-WEB-OPERATIONS | DIALOG | MODIFIED | Configurações do projeto | engrenagem da barra superior |
| INT-WORKSPACE-006 | SURF-WEB-OPERATIONS | CONTEXT MENU | NEW | Menu contextual de tarefa | clique secundário ou long press na linha |

## Interaction Details

### INT-WORKSPACE-001 — Workspace de projeto

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: apresentar uma Ãºnica projeÃ§Ã£o confiÃ¡vel de Ã¡rvore e cronograma de um projeto selecionado.
**Actors and Permissions**: usuÃ¡rio autenticado, dono do Gantt.
**Entry and Navigation**: seleÃ§Ã£o de projeto abre o workspace; busca, histÃ³rico e configuraÃ§Ãµes retornam preservando foco/contexto.
**Content and Data**: barra do sistema; resumo; Ã¡rvore por seÃ§Ãµes/tarefas/subtarefas ativas e concluídas; timeline; grupos derivados; campos explícitos do Todoist; data, deadline e desbloqueio considerados; status calculado; estado de sincronizaÃ§Ã£o. Agrupadores usam uma linha apenas. Toda tarefa folha reserva, entre a árvore e o conteúdo textual, um slot terminal de largura idêntica ao controle de nó. P1/P2/P3 usam esse slot para uma bandeira que aproveita a altura do conjunto título/descrição; P4 mantém o slot sem marcador. Título e descrição nativa truncada começam sempre no mesmo eixo. Etiqueta e timeblock `OPENED` são verdes; etiqueta, timeblock e legenda `COMPLETED` são cinza-escuro e o título da tarefa concluída aparece riscado. O filtro organiza checkboxes “Abertas”, “Agendadas” e “Atrasadas” sob o agrupador virtual marcável “Desbloqueadas”, seguido de “Bloqueadas” e “Concluídas”. Acima dos títulos fixos, o comando Colunas abre as opções Tarefa, Responsável, Status, Início, Deadline e Comentários; Tarefa é obrigatória.
**Actions and Behavior**: expandir/recolher, selecionar item, abrir painel, navegar no tempo, combinar filtros de estados, escolher colunas, redimensionar Tarefa e solicitar recarga. Checkboxes do filtro aplicam imediatamente a união dos estados marcados; “Todas” marca/desmarca o conjunto completo e “Desbloqueadas” marca/desmarca suas três filhas, ficando parcial quando aplicável. O menu permanece aberto durante a composição e fecha por Escape ou clique externo. O seletor de colunas aplica cada opção imediatamente e persiste a configuração. O separador na borda direita de Tarefa aceita drag horizontal; setas ajustam em passos, Home restaura o mínimo e End aplica o máximo. A largura mínima é a largura atual da coluna e a máxima é 25% da viewport. Grupos nÃ£o sÃ£o editÃ¡veis diretamente; status calculado e o agrupador virtual não são editáveis nem persistidos. Prioridade bruta Todoist é apresentada como `4→P1` vermelha, `3→P2` amarela, `2→P3` azul e `1→P4` sem marcador.
**Validation and Feedback**: respostas exibem somente projeÃ§Ã£o autorizada; projeto vazio, integraÃ§Ã£o ausente e falha de sincronizaÃ§Ã£o tÃªm explicaÃ§Ã£o e aÃ§Ã£o de recuperaÃ§Ã£o.
**Responsive/Adaptive Behavior**: desktop mostra Ã¡rvore e timeline lado a lado; a largura máxima de Tarefa é recalculada ao redimensionar a viewport. Em tablet/telefone, 25% pode ficar abaixo da largura base e, nesse caso, a largura base prevalece com navegação horizontal; colunas opcionais podem ser ocultadas pelo usuário sem perder a configuração.
**Accessibility**: estrutura de Ã¡rvore navegÃ¡vel por teclado, foco visÃ­vel, relaÃ§Ã£o entre linha e barra, sem depender apenas de cor; alvos touch adequados. Os botões Filtros e Colunas expõem `aria-expanded`; opções usam checkboxes, o agrupador de status anuncia seleção parcial pelo estado indeterminado e Escape fecha o filtro devolvendo foco ao acionador. Tarefa é anunciada como obrigatória. O resize é um separador focável com orientação, valor atual/mínimo/máximo e operação por setas/Home/End. Bandeiras possuem nome acessível P1/P2/P3 e descrição completa fica disponível no atributo `title` quando truncada.
**Localization**: `pt-BR`; datas respeitam locale e timezone de planejamento.
**Components and Design System**: barra do sistema, Ã¡rvore virtualizada, timeline, badge de estado, painel e mensagens compartilhadas.
**Integration and Contracts**: projeÃ§Ã£o de workspace e eventos de atualizaÃ§Ã£o; cliente nÃ£o calcula grupos, datas consideradas, desbloqueio, status ou criticidade. O contrato distingue `start`/`finish` explícitos de `considered_start`/`considered_deadline` calculados.
**Telemetry**: abertura, tempo atÃ© pronto, projeto vazio, erro, recarga e alternÃ¢ncia de viewport; sem tÃ­tulos de tarefa.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-workspace-001.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | estrutura reservada e contexto de projeto | cancelar/navegar | loading |
| loading | skeleton de Ã¡rvore/timeline | cancelar navegaÃ§Ã£o | ready/error |
| empty | projeto sem tarefas, sem estrutura artificial | voltar/atualizar | loading |
| ready | Ã¡rvore e timeline alinhadas | navegar/selecionar/editar permitido | processing/loading |
| processing | operaÃ§Ã£o especÃ­fica marcada sem bloquear leitura | acompanhar/cancelar quando permitido | success/error |
| success | confirmaÃ§Ã£o breve e projeÃ§Ã£o atualizada | continuar | ready |
| validation-error | aÃ§Ã£o invÃ¡lida explicada no item | corrigir | ready |
| remote-error | integraÃ§Ã£o indisponÃ­vel e retry | recarregar/reconectar | loading |
| offline | Ãºltimo estado sinalizado | retentar apÃ³s rede | loading |
| access-denied | sessÃ£o/projeto indisponÃ­vel | entrar/selecionar projeto | initial |
| partial-stale | Ãºltimo snapshot e horÃ¡rio marcados | reconciliar | loading |

### INT-WORKSPACE-002 — Resize temporal do timeblock

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: permitir alterar início ou deadline diretamente na escala diária sem confundir resize com movimento.
**Actors and Permissions**: usuário autenticado, dono do Gantt; somente tarefas comuns não concluídas.
**Entry and Navigation**: hover/foco revela grips internos; em touch, primeiro toque seleciona/revela controles. O foco retorna ao grip após sucesso ou cancelamento.
**Content and Data**: timeblock original; grips inicial/final; ghost com início/deadline previstos; limite temporal efetivo; datas civis no fuso do projeto.
**Actions and Behavior**: `ew-resize` nas bordas; centro mantém movimento. Grip direito altera deadline com mínimo no início. Grip esquerdo altera início entre limite temporal e deadline; sem deadline, fixa a antiga borda direita como novo prazo. Snap ocorre em cada coluna diária. Escape cancela. Próximo às bordas do viewport, autoscroll preserva o gesto.
**Validation and Feedback**: limite atingido produz resistência visual e anúncio da data mínima/máxima; tarefa concluída, seção e resumo não exibem grips; falha Todoist restaura o original e oferece retry; sucesso reconcilia toda a projeção.
**Responsive/Adaptive Behavior**: desktop usa hover/foco e hit area interna de 10–12 px; touch usa controle visível após seleção com alvo mínimo ampliado sem alterar a geometria da barra.
**Accessibility**: grips são botões/separadores focáveis com nome “Ajustar início/fim de …”, `aria-valuetext` da data e setas para passos de um dia; Enter/Espaço inicia/confirma e Escape cancela; ghost é anunciado por região `aria-live` sem depender de cor.
**Localization**: `pt-BR`, datas civis no fuso do projeto.
**Components and Design System**: timeblock existente, grip interno, ghost semitransparente e toast compartilhado.
**Integration and Contracts**: `PUT /api/v1/tasks/{id}/dates` com intent `MOVE`, `RESIZE_START` ou `RESIZE_END`; resposta contém campos nativos persistidos e é seguida por reload/evento de workspace.
**Telemetry**: modo, duração em dias, cancelamento, limite atingido e resultado; sem título da tarefa.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-workspace-timeblock-gestures.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | grips ocultos até hover, foco ou seleção touch | focar/selecionar tarefa | ready |
| loading | controles indisponíveis enquanto o workspace carrega | aguardar/cancelar navegação | ready/remote-error |
| empty | nenhum timeblock ou grip | voltar/atualizar | loading |
| ready | grips internos no timeblock elegível | mover, focar ou iniciar resize | processing |
| processing | somente ghost quantizado e datas previstas | mover, confirmar ou cancelar | success/validation-error/remote-error |
| success | projeção reconciliada e confirmação breve | continuar | ready |
| validation-error | ghost removido, original preservado e limite explicado | corrigir/repetir | ready |
| remote-error | original restaurado e falha Todoist explicada | tentar novamente | processing/ready |
| offline | escrita indisponível e último estado preservado | retentar após rede | loading |
| access-denied | sessão/projeto indisponível | entrar/selecionar projeto | initial |
| partial-stale | último snapshot identificado; commit bloqueado | reconciliar | loading |

### INT-WORKSPACE-003 — Criação gráfica de dependência

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: criar relações FS/SS/FF/SF conectando eventos de início e fim dos timeblocks.
**Actors and Permissions**: usuário autenticado, dono do Gantt.
**Entry and Navigation**: endpoints externos aparecem no timeblock em hover/foco; iniciado o drag, endpoints elegíveis aparecem nas demais tarefas.
**Content and Data**: porta de origem, portas de destino, linha SVG até o ponteiro/target, badge do tipo inferido e motivo para target inválido.
**Actions and Behavior**: cursor `crosshair`; origem é predecessora e destino é sucessora. início→início=SS, início→fim=SF, fim→início=FS e fim→fim=FF. Drop válido persiste imediatamente no Ganttist usando o snapshot já carregado, desenha a relação no grafo local e reconcilia a projeção em segundo plano; Escape ou drop fora cancela. O gesto nunca aguarda consulta ao Todoist.
**Validation and Feedback**: autorrelação, duplicidade, ciclo, item externo, seção e grupo como sucessor ficam desabilitados e são revalidados no servidor. Erro mantém o grafo anterior. Durante commit, novo gesto é bloqueado.
**Responsive/Adaptive Behavior**: desktop usa drag preciso; touch amplia portas após seleção e mantém autoscroll. Em viewport estreito, relação também pode ser criada pelo formulário acessível existente no editor.
**Accessibility**: portas focáveis nomeadas “Conectar início/fim de …”; Enter/Espaço escolhe origem e destino; targets inválidos expõem `aria-disabled` e motivo; tipo e resultado são anunciados.
**Localization**: siglas FS/SS/FF/SF permanecem canônicas e possuem descrição localizada em tooltip/aria-label.
**Components and Design System**: portas externas, overlay SVG compartilhado, badge de tipo e toast com ação Desfazer.
**Integration and Contracts**: reutiliza `POST /api/v1/dependencies` e `DELETE /api/v1/dependencies/{id}`; servidor é autoridade para escopo, grupos, duplicidade e ciclo, validados contra o snapshot local. Snapshot ausente retorna orientação para atualizar o workspace, sem acionar o Todoist.
**Telemetry**: tipo, origem por endpoint, cancelamento, validação e resultado; IDs/títulos não entram em telemetria.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-workspace-timeblock-gestures.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | portas ocultas até hover, foco ou seleção touch | focar/selecionar tarefa | ready |
| loading | nenhuma porta interativa | aguardar/cancelar navegação | ready/remote-error |
| empty | nenhum endpoint | voltar/atualizar | loading |
| ready | portas externas somente no timeblock ativo | escolher origem | processing |
| processing | overlay, tipo inferido e destinos válidos/invalidos | escolher destino ou cancelar | success/validation-error/remote-error |
| success | relação desenhada, projeção reconciliada e ação Desfazer | continuar/desfazer | ready/processing |
| validation-error | target inválido e motivo anunciado; grafo preservado | escolher outro destino/cancelar | processing/ready |
| remote-error | preview removido e grafo anterior preservado | tentar novamente | processing/ready |
| offline | commit bloqueado e snapshot preservado | retentar após rede | loading |
| access-denied | sessão/projeto indisponível | entrar/selecionar projeto | initial |
| partial-stale | portas desabilitadas até reconciliar | reconciliar | loading |

### INT-WORKSPACE-006 — Menu contextual de tarefa

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: disponibilizar ações rápidas contextualizadas para uma linha de tarefa.
**Actors and Permissions**: usuário autenticado, dono do Gantt.
**Entry and Navigation**: clique secundário no corpo da linha no desktop; long press de 550 ms sem movimento em toque. Timeblocks, portas, linhas de conexão, checkboxes, botões e controles com contexto próprio não abrem este menu. Clique externo fecha o menu.
**Content and Data**: menu posicionado junto ao ponto de entrada, limitado à viewport. A primeira ação alterna entre “Concluir tarefa”, com círculo e check, e “Desfazer conclusão”, com círculo vazio.
**Actions and Behavior**: a ação fecha o menu imediatamente e confirma a alteração diretamente no Todoist por operação específica de conclusão/reabertura, validada contra o snapshot carregado. Após confirmação, o workspace é reconciliado e um toast informa o resultado. Não há atualização otimista quando o Todoist falha.
**Validation and Feedback**: snapshot ausente orienta atualizar o workspace; falha do Todoist preserva o estado atual, mantém o menu fechado e exibe toast de erro. Durante o envio a opção permanece desabilitada.
**Responsive/Adaptive Behavior**: o menu usa coordenadas fixas e não sai da viewport; long press cancela ao mover mais de 10 px, soltar ou cancelar o toque, evitando conflito com rolagem.
**Accessibility**: `role=menu` e `role=menuitem`; ícones são decorativos e o rótulo descreve a ação atual.
**Localization**: rótulos e feedback em `pt-BR`.
**Components and Design System**: popover elevado, botão de menu e ícones SVG alinhados aos tokens do workspace.
**Integration and Contracts**: `PATCH /api/v1/tasks/{taskId}/completion`.
**Telemetry**: resultado da ação e duração; nenhum título, token ou ID externo em claro.
**Wireframe Requirement**: OPTIONAL
**Wireframe**: N/A — popover isolado, sem alteração estrutural do workspace.

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | menu fechado | clique secundário/long press | loading/ready |
| loading | snapshot ou confirmação em curso | aguardar/cancelar | ready/remote-error |
| empty | N/A — tarefas vazias não oferecem o menu | atualizar | loading |
| ready | menu fechado | clique secundário/long press | open |
| open | ação contextual habilitada | concluir/reabrir, clique externo | processing/ready |
| processing | ação desabilitada | aguardar | success/remote-error |
| success | menu fecha e toast confirma | continuar | ready |
| validation-error | N/A — a única ação é booleana e não possui entrada inválida | N/A | ready |
| remote-error | menu permanece fechado; toast explica | retentar | ready |
| offline | ação indisponível após falha de rede | retentar/fechar | ready/open |
| access-denied | menu fecha e o fluxo de login é exibido | entrar novamente | initial |
| partial-stale | menu permanece disponível somente após atualização do workspace | atualizar | loading |

### INT-WORKSPACE-004 — Relações no editor de tarefa

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: editar os campos nativos e comentários da tarefa, distinguir predecessoras e sucessoras e criar relações com busca e prévia inequívoca.
**Actors and Permissions**: usuário autenticado, dono do Gantt.
**Entry and Navigation**: abrir o editor por duplo clique ou comando Editar; a região de relações aparece após os campos e a projeção.
**Content and Data**: campos de título, descrição, prioridade P1–P4 e responsável; comentários com autor, data e conteúdo; quadro “Depende de” lista relações cuja tarefa atual é sucessora; quadro “Dependentes” lista relações cuja tarefa atual é predecessora. Cada relação contém badge de tipo, título da outra tarefa e lixeira. Cada cabeçalho possui contador e botão `+`.
**Actions and Behavior**: salvar campos nativos; criar ou editar comentário; remover relação; `+` abre o diálogo central na direção do quadro. O formulário permanente é removido. No diálogo, digitar qualquer trecho do título filtra a lista, escolher uma tarefa libera os tipos, e escolher FS/SS/FF/SF exibe a prévia acima da busca antes da confirmação.
**Validation and Feedback**: responsável deve pertencer aos colaboradores atuais; comentário vazio não é enviado; quadros vazios preservam o `+`. Busca vazia orienta a digitação, nenhum resultado é explicitado e resultados visuais são limitados. Autorrelação, duplicidade, ciclo e grupo sucessor continuam validados no cliente e no servidor. Falha preserva rascunhos, comentários e relações anteriores.
**Responsive/Adaptive Behavior**: os dois quadros ficam empilhados em qualquer largura do drawer; título encolhe antes do badge e da lixeira, que permanecem integralmente visíveis. Escalas de texto/espaçamento seguem o tema do workspace.
**Accessibility**: campos possuem rótulos; comentários anunciam autoria/data; cada quadro é região rotulada; `+` nomeia a direção. O diálogo move o foco inicial para a busca e fecha por Cancelar/Escape; busca usa combobox/listbox com resultado e seleção anunciados. A prévia possui descrição textual equivalente do tipo e não depende somente do desenho.
**Localization**: rótulos em `pt-BR`; siglas FS/SS/FF/SF permanecem canônicas.
**Components and Design System**: cartões, badges, ellipsis e botão iconográfico reutilizam tokens do drawer e foco visível da aplicação.
**Integration and Contracts**: usa workspace/dependencies, atualização de tarefa, colaboradores do projeto e comentários Todoist; cria relações pelo contrato existente de dependências.
**Telemetry**: remoção por direção e tipo; títulos e IDs não entram em telemetria.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-workspace-editor-relations.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | editor fechado | abrir tarefa | loading/ready |
| loading | relações indisponíveis durante carga do workspace | aguardar/fechar | ready/remote-error |
| empty | os dois quadros mostram mensagens vazias independentes | adicionar relação | processing |
| ready | listas separadas, títulos truncáveis e ações acessíveis | adicionar/remover/editar tarefa | processing |
| processing | confirmação de remoção ou criação preserva as listas | confirmar/cancelar | success/validation-error/remote-error |
| success | listas reconciliadas e toast breve | continuar | ready |
| validation-error | motivo exibido sem remover a linha | corrigir/cancelar | ready |
| remote-error | relação anterior preservada e erro explicado | tentar novamente | processing/ready |
| offline | listas permanecem legíveis e mutação indisponível | retentar após rede | loading |
| access-denied | editor fecha e fluxo de login é exibido | entrar novamente | initial |
| partial-stale | listas do último snapshot marcadas pelo estado global | reconciliar | loading |

### INT-WORKSPACE-005 — Configurações do projeto

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: organizar configurações por domínio sem permitir perda silenciosa de alterações locais.
**Actors and Permissions**: usuário autenticado, dono do Gantt.
**Entry and Navigation**: a engrenagem da barra superior abre um diálogo modal centralizado na aba Calendário. A navegação possui Calendário como primeira aba e Automação como segunda aba. Fechar retorna o foco à engrenagem.
**Content and Data**: cabeçalho “Configurações do projeto”; abas; formulário completo de calendário, impactos simulados e ações dentro do painel Calendário. Automação exibe somente linhas no formato checkbox, título e `?`: definir no Todoist o início de tarefas bloqueadas; e manter sem data/deadline no Todoist as tarefas com subtarefas. Não há cabeçalho de grupo nem descrições secundárias. Cada ajuda explica a regra e apresenta exemplo; o intervalo do pai continua derivado e desenhado no Gantt. Cada aba possui versão, baseline e rascunho próprios.
**Actions and Behavior**: Calendário mantém Adicionar/Remover exceção, Simular impacto e Confirmar calendário. Marcar automações altera somente o rascunho; a API é chamada exclusivamente por “Salvar automação”. A ativação confirmada enfileira reconciliação imediata. A segunda autorização somente limpa data e deadline de tarefas-pai e nunca grava nelas valores derivados. Salvar reconcilia o workspace sem fechar o diálogo. Clique externo é inerte para o diálogo principal, mas fecha qualquer popup de ajuda aberto; clicar dentro da ajuda ou em seu acionador não a fecha prematuramente. Fechar por X, Cancelar ou Escape e escolher outra aba são intenções protegidas: se a aba estiver suja, um `alertdialog` pergunta entre “Descartar alterações” e “Voltar”. Descartar restaura o último baseline carregado/salvo e só então executa a intenção; o fechamento efetivo também elimina qualquer rascunho não persistido. Voltar ou Escape fecha apenas a confirmação e preserva aba e valores. Sem alterações, a intenção acontece imediatamente.
**Validation and Feedback**: carregamento, validação, conflito de versão, falha remota e impacto simulado permanecem contextualizados na respectiva aba. Salvar automação confirmado produz toast verde de sucesso; falha produz toast vermelho e preserva a mensagem contextual. O sistema posiciona toasts centralizados no topo, abaixo da barra superior no desktop e junto à margem superior no telefone, com ícone, cor, `role` e `aria-live` coerentes para sucesso, erro ou informação. Durante requisição, a ação principal fica indisponível e seu rótulo informa o progresso.
**Responsive/Adaptive Behavior**: desktop e tablet usam diálogo central com largura máxima e conteúdo rolável; em telefone, o diálogo ocupa a viewport, mantendo cabeçalho, abas e ações acessíveis. A confirmação permanece central e transforma ações em pilha na largura estreita.
**Accessibility**: acionador expõe `aria-haspopup="dialog"` e `aria-expanded`; diálogo usa `aria-modal`, título associado, contenção de foco e retorno ao acionador. Abas usam `tablist`/`tab`/`tabpanel`, `aria-selected`, `aria-controls` e setas horizontais. Cada opção usa checkbox e rótulo completo. O `?` possui nome acessível, estado expandido e associação ao popup; Escape ou o X fecha a ajuda e devolve foco ao acionador correspondente. A confirmação usa `alertdialog`; Escape equivale a Voltar quando ela está aberta. Nenhuma ação depende apenas do backdrop, cor ou ícone sem nome acessível.
**Localization**: rótulos e mensagens em `pt-BR`; datas seguem o comportamento do calendário existente.
**Components and Design System**: engrenagem SVG, modal, abas, formulário de calendário e confirmação compartilhando tokens de texto/espaçamento do workspace.
**Integration and Contracts**: `GET/PUT /api/v1/calendar`, `POST /api/v1/calendar/simulate` e `GET/PUT /api/v1/settings/automation`; a borda Todoist recebe datas produzidas pelo core somente para folhas elegíveis e, para pais, no máximo comandos explícitos de limpeza.
**Telemetry**: abertura/fechamento, troca de aba, descarte e resultado de salvamento; valores do calendário não entram em telemetria.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-workspace-settings.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | diálogo fechado e engrenagem disponível | abrir | loading |
| loading | diálogo central, Calendário ativo e conteúdo carregando | fechar sem rascunho | ready/remote-error |
| empty | N/A — as duas abas sempre possuem ao menos uma configuração | N/A | N/A |
| ready | aba ativa com conteúdo e ações próprias | editar/trocar/fechar | processing/confirmation/initial |
| processing | ação da aba em progresso e comando principal indisponível | aguardar | success/validation-error/remote-error |
| confirmation | alerta sobre rascunho, foco nas decisões | descartar/voltar | ready/empty/initial |
| success | baseline atualizado, diálogo preservado e workspace reconciliado | continuar/trocar/fechar | ready |
| validation-error | erro local à aba e rascunho preservado | corrigir/simular novamente | ready |
| remote-error | falha explicada sem descarte do rascunho | tentar novamente/voltar | processing/ready |
| offline | rascunho preservado e persistência indisponível | retentar após rede | processing |
| access-denied | sessão expirada e fluxo global de login acionado | entrar novamente | initial |
| partial-stale | conflito de versão explicado sem sobrescrita | recarregar calendário/voltar | loading/ready |

## Cross-Surface Rules

Desktop, tablet e telefone compartilham o mesmo estado de negÃ³cio; apenas a disposiÃ§Ã£o e o mÃ©todo de entrada variam. Toda alteraÃ§Ã£o chega por projeÃ§Ã£o atualizada, nÃ£o por mutaÃ§Ã£o local persistente.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
|---|---|---|---|---|
| INT-WORKSPACE-001 | US-1â€“3 | FR-001â€“017, FR-029â€“030, FR-034 | SC-001â€“005, SC-010â€“011, SC-014 | workspace/eventos |
| INT-WORKSPACE-002 | US-4 | FR-018â€“023, FR-026â€“027 | SC-006, SC-008 | task dates/workspace/eventos |
| INT-WORKSPACE-003 | US-4 | FR-023â€“027 | SC-007â€“008 | dependencies/workspace/eventos |
| INT-WORKSPACE-004 | US-4 | FR-025, FR-028, FR-031â€“033 | SC-009, SC-012â€“013 | tasks/comments/collaborators/dependencies/workspace |
| INT-WORKSPACE-005 | US-5 | FR-037–038 | SC-015–016 | calendar/automation/workspace/Todoist |
| INT-WORKSPACE-006 | US-1 | FR-042 | SC-003 | task completion/workspace/Todoist |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
|---|---|---|---|
| INT-WORKSPACE-001 | REQUIRED | wireframes/int-workspace-001.md | Hierarquia e timeline responsiva |
| INT-WORKSPACE-002 | REQUIRED | wireframes/int-workspace-timeblock-gestures.md | Grips internos, ghost e limites |
| INT-WORKSPACE-003 | REQUIRED | wireframes/int-workspace-timeblock-gestures.md | Portas externas, linha e targets |
| INT-WORKSPACE-004 | REQUIRED | wireframes/int-workspace-editor-relations.md | Separação de predecessoras e sucessoras |
| INT-WORKSPACE-005 | REQUIRED | wireframes/int-workspace-settings.md | Abas, ações locais e confirmação de descarte |
| INT-WORKSPACE-006 | OPTIONAL | N/A | Popover isolado, sem alteração estrutural |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: pending contracts stage
- Placeholders or open decisions remaining: 0
