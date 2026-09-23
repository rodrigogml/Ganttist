# Interface Specification: Busca Unificada e Views de Tarefas

**Feature**: `unified-task-views`
**Created**: 2026-09-22
**Status**: Draft
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
|---|---|---|---|---|---|
| SURF-WEB-OPERATIONS | WEB | Proprietário, editor e leitor | FULL | Query única, funil, autocomplete, ajuda, histórico, views, importação, exportação e confirmações em Tarefas e Gantt | Compartilhamento colaborativo, dependências, caminho crítico e assistente por linguagem natural |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
|---|---|---|---|
| SURF-WEB-OPERATIONS | Workspace do projeto, `ProjectPlanningPage.vue` | Barra de comandos, busca com `/` e popover de filtros em `resources/js/ProjectPlanningPage.vue`; análise textual em `resources/js/utils/task-query.ts` | A busca textual e os filtros de status, responsável e período mantêm estados separados; Tarefas e Gantt já compartilham o workspace. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
|---|---|---|---|---|---|
| INT-WEB-001 | SURF-WEB-OPERATIONS | COMMAND BAR | MODIFIED | Consulta unificada e construtor de filtros | Campo de busca existente, atalho `/` e botão de funil |
| INT-WEB-002 | SURF-WEB-OPERATIONS | MENU AND DIALOG | NEW | Seletor e gestão de views | Novo controle de views na barra de comandos |
| INT-WEB-003 | SURF-WEB-OPERATIONS | DIALOG | NEW | Importação e exportação de view | Ações de gestão da view selecionada |

## Interaction Details

### INT-WEB-001 — Consulta unificada e construtor de filtros

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: Permitir que o usuário pesquise e filtre o mesmo conjunto de tarefas por uma expressão única e descoberta assistida.
**Actors and Permissions**: Proprietário, editor e leitor com acesso de consulta; nenhuma ação desta interação altera tarefa, estrutura ou acesso.
**Entry and Navigation**: O campo atual continua na barra de comandos de Tarefas e Gantt. `/` foca o campo quando nenhum controle textual está em edição; Esc fecha menus e devolve foco ao acionador; alternar Tarefas/Gantt não limpa a query.
**Content and Data**: Campo de expressão, contagem de tarefas, descrição legível dos critérios, mensagem de sintaxe, advertência de valor desconhecido, histórico, ajuda e sugestões de campos/operadores/valores do projeto. O funil mostra o critério atual derivado da expressão, nunca um estado independente.
**Actions and Behavior**: Digitar atualiza a análise; Tab/Enter aceita sugestão; setas navegam sugestões; o funil insere ou substitui critério sem remover texto não relacionado; exemplos da ajuda preenchem o campo; o usuário pode limpar a query e reaplicar item do histórico. `Ctrl/Cmd+Enter` não confirma ações nesta área.
**Validation and Feedback**: Erro estrutural mostra trecho/posição e mantém a última expressão válida aplicada. Valor não reconhecido é advertência não bloqueante e a query segue exportável. Query válida atualiza contagem, descrição e estado vazio. Fechar popover não descarta texto já inserido.
**Responsive/Adaptive Behavior**: Desktop mantém campo, contagem e controles na mesma barra quando houver espaço. Tablet quebra controles em segunda linha sem alterar ordem de leitura. Telefone dá largura integral ao campo; funil, ajuda e histórico abrem painel ancorado de largura disponível, com lista rolável, alvos de toque adequados e sem exigir hover.
**Accessibility**: O campo possui rótulo acessível, `aria-describedby` para diagnóstico e resultado, e atalho declarado. Sugestões usam padrão de combobox com item ativo anunciado. Menus prendem foco somente quando modais; Esc e clique externo os fecham. Mensagens de erro, aviso e contagem usam região de status sem repetir cada tecla digitada. Contraste, foco visível, zoom e redução de movimento seguem os tokens existentes.
**Localization**: Conteúdo inicial em pt-BR; datas relativas e pluralização da contagem usam locale do produto. Sintaxe canônica é documentada em pt-BR sem acento obrigatório para maximizar portabilidade; exemplos não expõem nomes de pessoas reais.
**Components and Design System**: Reutiliza barra de comandos, botão secundário, popover, `kbd`, tooltip, feedback de busca e tokens `--button-secondary-*` e `--button-accent-*`. Cria apenas primitivas reutilizáveis de sugestão, ajuda e resumo de query; nenhuma nova ação de persistência usa `.primary`.
**Integration and Contracts**: Consome a projeção de workspace existente e a representação de view quando aplicada. Referencia [views-api.md](contracts/views-api.md) para operações explícitas de views; a avaliação de query não chama o servidor a cada tecla nem grava histórico.
**Telemetry**: Registrar abertura de ajuda, aceitação de sugestão, uso do funil, query estruturalmente inválida e resultado vazio, sem registrar texto integral da query, nomes, IDs de pessoas ou títulos de tarefas.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-001.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | Campo vazio com dica de `/`, exemplos curtos e nenhuma advertência | Focar, abrir ajuda, funil ou histórico | Digitação ou seleção abre ready |
| loading | N/A — a query local não bloqueia o workspace | N/A — motivo | Mantém resultado anterior durante análise curta |
| empty | Query válida, contagem zero e orientação para ajustar ou limpar | Editar, abrir ajuda, limpar, histórico | Query válida com resultado entra em ready |
| ready | Query, resumo, contagem e resultado aplicados | Digitar, aceitar sugestão, abrir funil/ajuda/histórico | Erro entra em validation-error |
| processing | Indicador discreto de atualização após pausa de digitação, sem ocultar resultado anterior | Continuar editando ou limpar | Análise termina em ready, empty ou validation-error |
| success | Atualização válida é anunciada de forma não intrusiva | Continuar usando controles | Retorna a ready |
| validation-error | Borda e mensagem identificam sintaxe e posição; último resultado válido permanece | Corrigir, desfazer, ajuda | Query corrigida entra em processing |
| remote-error | Histórico indisponível é comunicado sem afetar a query local | Tentar novamente, seguir sem histórico | Resposta recuperada restaura histórico |
| offline | Query e resultado atual seguem disponíveis; nenhuma busca é gravada | Buscar, filtrar e usar dados atuais | Reconexão permite uma ação explícita futura sobre views |
| access-denied | N/A — workspace inacessível usa estado global existente | N/A — motivo | Navegação global trata saída |
| partial-stale | Banner existente informa projeção desatualizada; query continua distinguível | Ajustar consulta, tentar recarregar workspace | Workspace atualizado recalcula resultado |

### INT-WEB-002 — Seletor e gestão de views

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: Aplicar e administrar snapshots privados que restauram consulta e preferências visuais de Tarefas e Gantt.
**Actors and Permissions**: Proprietário, editor e leitor com acesso de consulta criam e administram somente as próprias views no projeto; nenhuma pessoa vê ou altera view de outra.
**Entry and Navigation**: Controle de views fica na barra de comandos antes dos controles de apresentação. Clique, Enter ou Espaço abre o seletor; escolha aplica a view e mantém o usuário no modo atual; criar, renomear, duplicar e excluir abrem diálogo contextual; Esc retorna foco ao controle.
**Content and Data**: Nome da view ativa ou “Sem view salva”, lista das views privadas do usuário, query resumida, data de atualização, ações criar, salvar como nova, sobrescrever, renomear, duplicar, excluir, exportar e importar. A lista inclui Minhas Tarefas e Tarefas Equipe como itens comuns editáveis.
**Actions and Behavior**: Aplicar restaura query e todo estado visual salvo; mudanças posteriores não marcam estado sujo nem salvam. Salvar como nova pede nome. Sobrescrever exibe confirmação com nome e resumo das mudanças. Excluir pede confirmação e, se era a ativa, mantém a configuração atual como temporária sem view selecionada.
**Validation and Feedback**: Nome vazio, longo ou duplicado recebe mensagem no diálogo e preserva entrada. Ações concorrentes desabilitam apenas seus controles. Sucesso atualiza lista e anuncia ação; erro remoto preserva configuração temporária e oferece nova tentativa.
**Responsive/Adaptive Behavior**: Desktop usa menu ancorado com busca quando a lista exceder altura útil. Tablet e telefone usam painel modal inferior ou lateral com título, ações em linhas separadas e rodapé fixo; a lista é rolável sem ocultar fechar. Ações críticas não dependem de menu de contexto ou hover.
**Accessibility**: Seletor é botão com estado expandido e nome da view ativa. Menu usa papéis e navegação por setas; diálogos são rotulados, movem foco ao título ou primeiro campo e o devolvem ao acionador. Confirmações distinguem ação destrutiva de persistência; anúncios não revelam query em regiões públicas.
**Localization**: Nomes criados são texto do usuário e preservam Unicode. Rótulos, datas de atualização, pluralização e mensagens usam pt-BR e suportam expansão futura de texto.
**Components and Design System**: Reutiliza botão secundário, menu, diálogo, campo de texto e `DefaultSubmitButton` em formulários de criar/renomear/salvar. Ação de exclusão usa token `--button-danger-*`; salvar/confirmar usa `DefaultSubmitButton` e tokens primários existentes.
**Integration and Contracts**: Consome listagem, criação, atualização e remoção em [views-api.md](contracts/views-api.md). Ao aplicar, usa o snapshot sem nova busca de tarefas; após escrita, atualiza apenas a coleção de views do usuário.
**Telemetry**: Registrar criação, aplicação, duplicação, tentativa/aceite/cancelamento de sobrescrita e exclusão por tipo de ação, sem nome, query, IDs de projeto ou dados do estado visual.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-002.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | Botão mostra ausência de view ou última view aplicada | Abrir seletor | Carregamento de lista entra em loading |
| loading | Menu ou painel mostra esqueleto de itens | Fechar | Itens carregam em ready |
| empty | Lista sem views criadas além das iniciais quando aplicável; explica criar primeira | Criar view | Criação bem-sucedida entra em success |
| ready | Lista, ações e view ativa distinguíveis | Aplicar, criar, editar, duplicar, excluir, importar/exportar | Escrita entra em processing |
| processing | Ação em curso indica progresso no item ou diálogo | Cancelar apenas antes de envio | Sucesso ou erro conclui operação |
| success | Confirmação curta e lista atualizada | Aplicar ou fechar | Retorna a ready |
| validation-error | Campo do diálogo mostra motivo e preserva valor | Corrigir e reenviar | Submissão válida entra em processing |
| remote-error | Mensagem no diálogo/menu preserva configuração e ação de tentar novamente | Tentar novamente, cancelar | Nova tentativa entra em processing |
| offline | Listagem conhecida é legível; criar/alterar/remover ficam indisponíveis com motivo | Aplicar views já carregadas | Reconexão permite novas operações |
| access-denied | Operação recusada fecha diálogo e comunica acesso removido | Voltar ao dashboard | Navegação global trata saída |
| partial-stale | Views carregadas podem ser aplicadas; sinaliza que atualização pode estar atrasada | Aplicar, tentar atualizar | Atualização restaura ready |

### INT-WEB-003 — Importação e exportação de view

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: Permitir transportar uma view por arquivo sem criar compartilhamento implícito ou alterar tarefa e pessoa do projeto.
**Actors and Permissions**: Proprietário, editor e leitor com acesso de consulta podem exportar e importar apenas para sua coleção privada do projeto atual.
**Entry and Navigation**: Exportar e Importar aparecem na gestão de views. Exportar inicia download diretamente. Importar abre seletor de arquivo; se existir colisão, abre confirmação explícita com opções copiar, sobrescrever e cancelar.
**Content and Data**: Nome e resumo da view exportada; no importador, nome do arquivo, nome contido, versão, resumo da query, advertências de referência não encontrada e opção de resolução de colisão. O conteúdo do arquivo não exibe dados de origem além da própria configuração de view.
**Actions and Behavior**: Exportar um item gera um arquivo. Importar valida primeiro; arquivo válido sem colisão cria view. Com colisão, copiar gera novo nome, sobrescrever atualiza somente a view escolhida e cancelar não escreve nada. Referência ausente não bloqueia nenhuma opção.
**Validation and Feedback**: Arquivo inválido, versão incompatível, estrutura incompleta e query estruturalmente inválida exibem motivo e não criam view. Avisos de valores ausentes são informativos. Falha de leitura ou rede mantém arquivo selecionado quando possível para nova tentativa.
**Responsive/Adaptive Behavior**: Desktop apresenta confirmação compacta no centro. Tablet e telefone usam diálogo de tela suficiente para mostrar todas as opções sem rolagem horizontal; ações ficam empilhadas e o botão cancelar permanece acessível acima do teclado virtual.
**Accessibility**: Input de arquivo tem rótulo e instrução de formato. O resumo é texto real, não dependente de cor. Diálogo anuncia nome, conflito e consequência de cada opção; foco inicial vai para título, e `Esc` equivale a cancelar antes da escrita.
**Localization**: Erros descrevem formato e versão em pt-BR sem revelar caminho local completo. Nome de arquivo e query são tratados como dados do usuário; datas não são reformatadas dentro do arquivo.
**Components and Design System**: Reutiliza diálogos, botões secundários, `DefaultSubmitButton` para importar/sobrescrever e botão de perigo somente quando o texto deixar claro que substitui conteúdo. Usa tokens existentes, sem novo roxo de ação primária.
**Integration and Contracts**: Exporta representação definida em [views-api.md](contracts/views-api.md); importação consome a mesma validação e os resultados de conflito/advertência do contrato.
**Telemetry**: Registrar início, validação inválida, conflito, resolução escolhida, sucesso e falha de importação/exportação, sem arquivo, nome, query, conteúdo ou caminho local.
**Wireframe Requirement**: REQUIRED
**Wireframe**: wireframes/int-web-003.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | Ação de importar/exportar disponível na gestão da view | Exportar ou escolher arquivo | Escolha de arquivo entra em loading |
| loading | Leitura e validação mostram progresso discreto | Cancelar leitura | Arquivo analisado entra em ready ou validation-error |
| empty | N/A — requer uma view para exportar ou arquivo para importar | N/A — motivo | Entrada inicial permanece disponível |
| ready | Resumo de arquivo válido e aviso eventual; opções de conflito quando necessário | Criar, copiar, sobrescrever, cancelar | Envio entra em processing |
| processing | Botões de escrita ficam indisponíveis e ação atual é identificada | Aguardar | Resposta entra em success ou remote-error |
| success | Confirmação identifica criação ou atualização e oferece aplicar a view | Aplicar ou fechar | Retorna à gestão em ready |
| validation-error | Erro de arquivo/versão/query sem escrita; arquivo permanece identificado | Escolher outro arquivo, fechar | Nova seleção entra em loading |
| remote-error | Erro de importação preserva resumo e opção de tentar novamente | Tentar novamente ou cancelar | Nova tentativa entra em processing |
| offline | Exportação de view carregada pode continuar localmente; importação que grava fica indisponível com explicação | Exportar, cancelar | Reconexão habilita importação |
| access-denied | Operação é interrompida, sem conteúdo de arquivo persistido | Fechar e voltar ao dashboard | Navegação global trata saída |
| partial-stale | Exportação usa view carregada e informa possível desatualização; importação aguarda rede | Exportar, cancelar, tentar atualizar | Atualização retorna a ready |

## Cross-Surface Rules

### Navigation and Parity

Há somente a superfície web responsiva. Tarefas e Gantt usam a mesma query e a mesma view; controles não disponíveis visualmente no modo atual são restaurados no estado do outro modo e surtirão efeito quando ele for aberto. Não há nova rota, deep link ou compartilhamento automático.

### Shared Content and Terminology

Termos canônicos: “consulta” para a expressão, “critério” para parte reconhecida, “view” para snapshot privado, “sobrescrever” para substituir configuração salva, “copiar” para importação com novo nome e “referência não encontrada” para valor textual válido sem correspondência local.

### Shared Accessibility and Input

Todos os fluxos funcionam por teclado e toque. O foco não é perdido por atualização de contagem; atalhos não interferem com composição de texto; controles desabilitados explicam motivo; confirmação de sobrescrita e exclusão nunca depende só de cor ou ícone.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
|---|---|---|---|---|
| INT-WEB-001 | US-001, US-002, US-006 | FR-001 a FR-016, FR-027, FR-028 | SC-001 a SC-004, SC-007 | [views-api.md](contracts/views-api.md) |
| INT-WEB-002 | US-003, US-004 | FR-017 a FR-023, FR-027, FR-028 | SC-005, SC-007 | [views-api.md](contracts/views-api.md) |
| INT-WEB-003 | US-005 | FR-024 a FR-028 | SC-006, SC-007 | [views-api.md](contracts/views-api.md) |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
|---|---|---|---|
| INT-WEB-001 | REQUIRED | wireframes/int-web-001.md | Barra, painel assistido e adaptação mobile |
| INT-WEB-002 | REQUIRED | wireframes/int-web-002.md | Seletor e diálogo de gestão |
| INT-WEB-003 | REQUIRED | wireframes/int-web-003.md | Importação e resolução de colisão |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: yes
- Placeholders or open decisions remaining: 0
