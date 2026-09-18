# Interface Specification: Tabelas na conversa da tarefa

**Feature**: `task-conversation-tables`
**Created**: 2026-09-10
**Status**: Draft
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
|---|---|---|---|---|---|
| SURF-WEB-OPERATIONS | WEB | Proprietário, editor e leitor | FULL | Conversa, blocos de tabela, criação, edição exclusiva, recuperação de conflito e leitura responsiva. | Coedição, atualização automática, histórico, múltiplas abas, importação/exportação, gráficos e merge automatizado. |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
|---|---|---|---|
| SURF-WEB-OPERATIONS | `resources/js/TaskCommentsWindow.vue` | Footer tem “Novo comentário” com `RichMarkdownEditor`; corpo lista somente `TaskComment`. | Janela móvel/redimensionável carrega comentários, permite editar/excluir o próprio texto e mostra autor/data. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
|---|---|---|---|---|---|
| INT-WEB-001 | SURF-WEB-OPERATIONS | PANEL | MODIFIED | Conversa da tarefa e compositor por abas | Ação de comentários da tarefa no workspace. |
| INT-WEB-002 | SURF-WEB-OPERATIONS | PANEL | NEW | Editor de nova tabela | Aba “Nova Tabela”. |
| INT-WEB-003 | SURF-WEB-OPERATIONS | PANEL | NEW | Edição exclusiva e recuperação de tabela | Ação “Editar tabela” em bloco publicado. |

## Interaction Details

### INT-WEB-001 — Conversa da tarefa e compositor por abas

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: MODIFIED
**Purpose**: Apresentar comentários textuais e tabelas como uma única cronologia e permitir iniciar o tipo de publicação adequado.
**Actors and Permissions**: Proprietário/editor veem abas e ações de escrita; leitor somente lê os blocos.
**Entry and Navigation**: Mantém o acionamento atual de comentários da tarefa e a mesma janela. Fechar retorna foco ao acionador; trocar aba não abre nova janela.
**Content and Data**: Cabeçalho preserva tarefa e contagem de blocos. Corpo ordena comentário e tabela por data. Cada bloco tem autor/data; tabela inclui rótulo visual “Tabela”. Footer contém tabs `Novo Comentário` e `Nova Tabela`.
**Actions and Behavior**: A aba inicial é “Novo Comentário”. Trocar para a outra aba preserva o rascunho de ambas. Publicar comentário mantém seu fluxo. Publicar tabela acrescenta o bloco à cronologia, seleciona “Novo Comentário” e limpa somente o rascunho publicado. Fechar com qualquer rascunho não publicado pede confirmação.
**Validation and Feedback**: Falha de leitura mostra ação “Tentar novamente”; publicação mantém o rascunho e mostra mensagem próxima à ação. Limites de tabela são informados no editor.
**Responsive/Adaptive Behavior**: Desktop mantém janela redimensionável. Em tablet/telefone, a janela ocupa a área segura, remove arraste/redimensionamento e o footer permanece acessível acima do teclado virtual; as tabs têm largura equivalente e podem rolar horizontalmente se necessário.
**Accessibility**: Tabs seguem padrão `tablist`/`tab`/`tabpanel`, com setas para trocar, foco no painel ativo e `aria-selected`. Cronologia usa artigos com heading de autor e data legível. Contagem e mensagens dinâmicas usam região de status.
**Localization**: Rótulos em pt-BR; data usa formato local já aplicado pela conversa; mensagens não expõem token, documento integral ou identidade de outro editor.
**Components and Design System**: Reutiliza janela, botões `primary`/`soft-btn`, diálogos e tokens de `gantt-workspace.css`; adiciona tabs e cartão de tabela, sem segunda navegação.
**Integration and Contracts**: Consome o contexto estendido e publicação de tabela em [tables-api.md](contracts/tables-api.md). Não faz polling; conteúdo salvo é atualizado ao publicar localmente ou reabrir/recarregar.
**Telemetry**: Registrar abertura da aba, publicação bem-sucedida/falha e motivo de validação; não registrar conteúdo, fórmulas ou token.
**Wireframe Requirement**: REQUIRED
**Wireframe**: `wireframes/int-web-001.md`

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | Aba de comentário ativa e rascunhos vazios. | Trocar aba, fechar. | Carregar conversa. |
| loading | Corpo informa “Carregando conversa…”. Footer de escrita fica indisponível até conhecer permissão. | Fechar. | ready, empty, remote-error ou access-denied. |
| empty | “Ainda não há publicações nesta tarefa.” | Criar comentário/tabela conforme permissão. | Publicar ou trocar aba. |
| ready | Cronologia e compositor ativo conforme papel. | Todas as ações permitidas. | Publicação, edição ou fechamento. |
| processing | Botão da ação atual mostra progresso e evita envio duplicado. | Cancelar somente se não enviou. | success, validation-error ou remote-error. |
| success | Mensagem breve e bloco novo/atualizado visível. | Continuar, criar outra publicação. | Retorna a ready. |
| validation-error | Mensagem junto ao editor; conteúdo preservado. | Corrigir, copiar ou publicar quando aplicável. | Novo envio. |
| remote-error | Mensagem acionável e “Tentar novamente”; rascunho preservado. | Tentar novamente, fechar. | loading/ready. |
| offline | Aviso de que publicação e edição exigem conexão; rascunho permanece local. | Copiar conteúdo, aguardar conexão, fechar. | ready ao reconectar e recarregar. |
| access-denied | Corpo explica que a leitura/escrita não é permitida; não mostra dados não autorizados. | Fechar. | N/A. |
| partial-stale | N/A — não há atualização automática nesta entrega. | Recarregar/reabrir conversa. | loading. |

### INT-WEB-002 — Editor de nova tabela

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: Permitir criar um bloco de tabela formatado sem misturá-lo com comentário textual.
**Actors and Permissions**: Apenas proprietário e editor; leitor não acessa a aba nem o editor.
**Entry and Navigation**: Aba “Nova Tabela” no footer. Retorno para “Novo Comentário” mantém a tabela não publicada até descarte ou publicação.
**Content and Data**: Área de planilha inicial de 5 × 5 com cabeçalhos de linhas/colunas, barra de formatação, seleção de célula e rodapé de limites. Uma aba de planilha é visível; não existe seletor de abas.
**Actions and Behavior**: Inserir/remover linha ou coluna, mesclar/desmesclar, aplicar negrito/cor/alinhamento, ajustar dimensões, copiar/colar, desfazer/refazer e inserir fórmulas aritméticas/soma. “Publicar tabela” só fica ativo quando houver conteúdo material.
**Validation and Feedback**: Antes de publicar, informar linhas, colunas e tamanho usados; ao exceder limite, impedir a operação/publicação afetada e preservar o conteúdo válido. Fórmula inválida é indicada na própria célula.
**Responsive/Adaptive Behavior**: Desktop usa a largura integral do footer expandido e sugere maximizar a janela. Em telefone, a planilha rola nos dois eixos, toolbar é horizontalmente rolável, ações de linha/coluna ficam em menu e alvos touch têm no mínimo 44 px. Teclado físico preserva atalhos usuais; touch usa toque/arrasto.
**Accessibility**: O contêiner recebe nome “Editor de nova tabela”; toolbar tem grupos e nomes acessíveis. Expor instrução curta de teclado e limites. O produto deve fornecer alternativa textual para copiar a seleção; navegação celular por teclado e anúncio de seleção/erro são verificados contra a capacidade do componente integrado.
**Localization**: Fórmulas aceitam a sintaxe definida pelo editor; labels e erros são pt-BR. Números exibidos usam locale pt-BR sem alterar as expressões salvas.
**Components and Design System**: Novo `TaskTableEditor`, runtime de planilha lazy, botões existentes do compositor e aviso de limite.
**Integration and Contracts**: Usa publicação em [tables-api.md](contracts/tables-api.md); não solicita reserva para uma tabela ainda não publicada.
**Telemetry**: Registrar início, publicação, descarte, limite e fórmula inválida por categoria; excluir conteúdo.
**Wireframe Requirement**: REQUIRED
**Wireframe**: `wireframes/int-web-001.md`

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | Placeholder de criação enquanto o editor carrega. | Voltar à aba de comentário. | loading. |
| loading | Indicador “Preparando tabela…”, sem toolbar interativa. | Voltar/fechar. | ready ou remote-error. |
| empty | Grade inicial sem conteúdo material. | Editar. | ready após alteração. |
| ready | Grade e toolbar interativas. | Formatar, editar, publicar, trocar aba. | processing ou confirmação de descarte. |
| processing | Grade bloqueada durante publicação. | Nenhuma ação concorrente. | success, validation-error ou remote-error. |
| success | Aba retorna a comentário e confirma publicação. | Criar outra publicação. | ready de INT-WEB-001. |
| validation-error | Limite/fórmula destacados, conteúdo preservado. | Corrigir. | ready. |
| remote-error | Falha de publicação com retry, conteúdo preservado. | Tentar novamente, copiar, trocar aba. | processing/ready. |
| offline | Sem publicação; rascunho preservado. | Copiar, aguardar conexão. | ready após recarga. |
| access-denied | N/A — aba não é disponibilizada ao leitor. | N/A. | N/A. |
| partial-stale | N/A — é rascunho local ainda não publicado. | N/A. | N/A. |

### INT-WEB-003 — Edição exclusiva e recuperação de tabela

**Surface**: SURF-WEB-OPERATIONS
**Surface Type**: WEB
**Change Type**: NEW
**Purpose**: Editar uma tabela publicada sem sobrescrever o trabalho de outro participante e recuperar o rascunho em caso de perda da reserva.
**Actors and Permissions**: Proprietário e editor podem iniciar edição de qualquer tabela. Leitor vê somente a tabela.
**Entry and Navigation**: Botão “Editar tabela” no bloco. Se a tabela está reservada por outra pessoa, o botão abre um estado explicativo, sem editor editável. Salvar/cancelar fecha o editor e retorna ao bloco atualizado.
**Content and Data**: Editor recebe documento salvo, estado da reserva e contador não exposto visualmente como prazo rígido. O estado de perda mostra aviso, editor congelado e ações “Copiar conteúdo” e “Publicar como nova tabela”.
**Actions and Behavior**: Ao abrir, solicitar reserva; renovar silenciosamente durante edição. Salvar exige reserva válida e atualiza o bloco. Cancelar ou excluir confirma a ação e libera a reserva. Ocultar página não descarta nem libera rascunho. Se a renovação tardia for aceita, edição continua sem interrupção. “Copiar conteúdo” copia valores e fórmulas como texto tabulado; “Publicar como nova tabela” preserva o documento integral.
**Validation and Feedback**: Reserva ocupada informa que a tabela está sendo editada e recomenda tentar mais tarde. Perda usa mensagem: “Esta tabela passou a ser editada por outro participante enquanto você esteve ausente. Seu rascunho foi preservado, mas não pode substituir a tabela original.” Não mostra nome de terceiro.
**Responsive/Adaptive Behavior**: Usa o mesmo comportamento responsivo de INT-WEB-002. O painel de conflito permanece no topo e suas ações ficam sempre alcançáveis; no telefone empilha botões e não depende de hover.
**Accessibility**: Foco entra no título do editor; alertas de reserva e perda são `role=status`/`role=alert` conforme urgência. Após congelar, foco vai ao alerta e depois a “Copiar conteúdo”. Diálogos de cancelar/excluir prendem foco e devolvem ao acionador.
**Localization**: Mensagens em pt-BR, com linguagem neutra (“outro participante”); nenhuma mensagem expõe token ou informação privada.
**Components and Design System**: Reutiliza editor, diálogos e botões; adiciona banner de reserva/conflito e cartão de tabela com menu de ações.
**Integration and Contracts**: Usa aquisição, renovação, liberação, salvamento e exclusão definidos em [tables-api.md](contracts/tables-api.md). Não atualiza o bloco automaticamente depois de perda: “Publicar como nova tabela” usa a operação de criação.
**Telemetry**: Registrar aquisição, reserva ocupada, renovação tardia aceita, perda, salvamento, cancelamento e publicação como cópia; excluir tokens, células e fórmula.
**Wireframe Requirement**: REQUIRED
**Wireframe**: `wireframes/int-web-003.md`

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | Botão editar disponível para escritor. | Editar. | loading. |
| loading | “Abrindo editor…” enquanto obtém reserva. | Cancelar abertura. | ready, remote-error ou access-denied. |
| empty | N/A — tabela publicada tem documento. | N/A. | N/A. |
| ready | Editor editável e renovação ativa. | Editar, salvar, cancelar, excluir. | processing, partial-stale ou close. |
| processing | Salvar/excluir bloqueia alterações e mostra progresso. | Nenhuma ação concorrente. | success, validation-error, remote-error ou partial-stale. |
| success | Confirmação e bloco recarregado/localmente atualizado. | Voltar à conversa. | ready de INT-WEB-001. |
| validation-error | Erro de documento/limite com conteúdo preservado. | Corrigir e salvar. | ready. |
| remote-error | Falha recuperável preserva editor; falha na renovação tenta novamente até perda confirmada. | Tentar novamente, copiar, cancelar. | ready ou partial-stale. |
| offline | Banner explica que não é possível renovar/salvar; editor permanece local até resultado posterior. | Copiar, aguardar conexão, cancelar. | ready ou partial-stale. |
| access-denied | Explica ausência de escrita e fecha editor. | Voltar à conversa. | ready de INT-WEB-001. |
| partial-stale | Banner de perda, editor congelado e rascunho preservado. | Copiar conteúdo, publicar como nova tabela, descartar. | INT-WEB-002, confirmação de descarte ou conversa. |

## Cross-Surface Rules

### Navigation and Parity

Há apenas a SPA web responsiva. Desktop, tablet e telefone preservam as mesmas ações de negócio; layouts e mecanismos de seleção adaptam-se a mouse, teclado e toque. A conversa nunca atualiza blocos de terceiros automaticamente: reabrir/recarregar é a ação explícita de atualização.

### Shared Content and Terminology

Usar “Novo Comentário”, “Nova Tabela”, “Publicar tabela”, “Editar tabela”, “Publicar como nova tabela” e “outro participante”. “Reserva de edição” é termo técnico interno e não substitui a mensagem explicativa para o usuário.

### Shared Accessibility and Input

Foco, contraste e estado não dependem apenas de cor. Toda ação de mouse tem equivalente por teclado e todo controle touch tem alvo adequado. A capacidade de leitura por leitor de tela da grade integrada deve ser testada antes da entrega; se a biblioteca não a fornecer para edição, disponibilizar cópia textual da seleção e documentar a limitação de forma acessível.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
|---|---|---|---|---|
| INT-WEB-001 | US-1, US-4 | FR-001–006, FR-018–019 | SC-001, SC-002, SC-004 | `contracts/tables-api.md` contexto e publicação |
| INT-WEB-002 | US-1, US-2 | FR-001, FR-004, FR-007–011 | SC-001, SC-005 | `contracts/tables-api.md` publicação |
| INT-WEB-003 | US-3, US-4 | FR-005, FR-012–022 | SC-002–004 | `contracts/tables-api.md` reserva, salvar e excluir |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
|---|---|---|---|
| INT-WEB-001 | REQUIRED | [int-web-001.md](wireframes/int-web-001.md) | Cronologia e abas do compositor. |
| INT-WEB-002 | REQUIRED | [int-web-001.md](wireframes/int-web-001.md) | Layout do editor de nova tabela. |
| INT-WEB-003 | REQUIRED | [int-web-003.md](wireframes/int-web-003.md) | Reserva ocupada e recuperação após perda. |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: yes
- Placeholders or open decisions remaining: 0
