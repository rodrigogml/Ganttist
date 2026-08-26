# Interface Specification: Gerenciamento Local de Projetos

**Feature**: `local-project-management`
**Created**: 2026-08-25
**Status**: Draft
**Spec**: [spec.md](spec.md)
**Plan**: [plan.md](plan.md)
**Surface Catalog**: [../../architecture/interaction-surfaces.md](../../architecture/interaction-surfaces.md)

## Interface Coverage

| Surface ID | Type | Users | Coverage | Included Scope | Deferred or Excluded Scope |
|---|---|---|---|---|---|
| SURF-WEB-OPERATIONS | WEB | Proprietário, editor e leitor | FULL | Lista, criação e abertura de projetos; workspace local; pessoas e acesso | Integração obrigatória ou setup Todoist |
| SURF-WEB-ACCESS | WEB | Visitante e usuário autenticado | FULL | Após autenticação, encaminha ao dashboard local | Mudança no login passwordless |

## Current-State Evidence

| Surface ID | Existing Route, Command, or Component | Evidence | Current Behavior |
|---|---|---|---|
| SURF-WEB-OPERATIONS | `resources/js/App.vue` | Inicialização consulta `/todoist/status`, exibe `TodoistSetup.vue` e carrega um único workspace | A conexão e a seleção Todoist bloqueiam a operação. |
| SURF-WEB-ACCESS | `resources/js/TodoistSetup.vue` | Tela de configuração inicial existente | Solicita OAuth e seleção de projeto. |

## Interaction Inventory

| Interaction ID | Surface ID | Kind | Change Type | Name | Entry Point |
|---|---|---|---|---|---|
| INT-WEB-001 | SURF-WEB-OPERATIONS | SCREEN | NEW | Dashboard de projetos | Pós-login, marca e navegação principal |
| INT-WEB-002 | SURF-WEB-OPERATIONS | DIALOG | NEW | Criar projeto | Botão `Novo projeto` no dashboard |
| INT-WEB-003 | SURF-WEB-OPERATIONS | SCREEN | MODIFIED | Workspace do projeto | Card de projeto, URL direta ou troca de projeto |
| INT-WEB-004 | SURF-WEB-OPERATIONS | PANEL | MODIFIED | Pessoas e acesso | Configurações do projeto; proprietário |
| INT-WEB-005 | SURF-WEB-ACCESS | SCREEN | REMOVED | Configuração Todoist obrigatória | Pós-login |

## Interaction Details

### INT-WEB-001 — Dashboard de projetos

**Surface**: SURF-WEB-OPERATIONS  
**Surface Type**: WEB  
**Change Type**: NEW  
**Purpose**: Permitir que o usuário encontre, avalie e abra seus projetos locais ou inicie um novo.  
**Actors and Permissions**: Proprietário, editor e leitor veem somente projetos acessíveis; todos podem criar projeto e se tornam proprietários.  
**Entry and Navigation**: Destino pós-login; logo retorna ao dashboard; cada card abre o workspace daquele projeto e o voltar retorna à lista preservando busca/ordem.  
**Content and Data**: Topbar existente com marca e conta; título `Projetos`; ação primária `Novo projeto`; cards com nome, papel, total de tarefas, progresso ponderado, atrasadas e atualização.  
**Actions and Behavior**: Abrir projeto, criar projeto, pesquisar por nome e abrir conta. Card inteiro é acionável; botão de criação não fica escondido em menu.  
**Validation and Feedback**: Skeleton durante carga; estado vazio orienta a criar primeiro projeto; erro preserva a página e oferece `Tentar novamente`; sucesso de criação abre o novo workspace.  
**Responsive/Adaptive Behavior**: Desktop usa grade de cards; tablet reduz colunas; telefone usa uma coluna, ação de criação visível no topo e cards com métricas em grade 2×2. Teclado, mouse e toque suportados.  
**Accessibility**: `main` com heading único; card é link/botão com nome e indicadores acessíveis; foco visível, ordem topbar→criação→busca→cards; métricas não dependem apenas de cor.  
**Localization**: pt-BR inicial; pluralização de tarefa(s), datas em locale do usuário e textos preparados para expansão.  
**Components and Design System**: Reutiliza topbar, marca, botões primário/soft, cards, toast, escala de texto e tokens violeta, ink e line existentes; cria somente `ProjectCard` e `ProjectDashboard`.  
**Integration and Contracts**: Consome lista e criação de `contracts/projects-api.md`; usa resposta confirmada, sem cache de outra conta.  
**Telemetry**: `project_dashboard_viewed`, `project_opened`, `project_creation_started`; sem nome, e-mail ou conteúdo de tarefa.  
**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/int-web-001.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | Skeleton de cabeçalho e cards | Conta | Inicia carregamento |
| loading | Skeleton de cabeçalho e cards | Conta | Lista carregada ou erro |
| empty | Ilustração discreta, explicação e `Criar projeto` | Criar, conta | Abre INT-WEB-002 |
| ready | Cards e busca | Abrir, buscar, criar | Workspace ou diálogo |
| processing | Criação desabilitada no diálogo; lista permanece legível | Cancelar quando seguro | Sucesso ou erro |
| success | Toast curto; navegação ao projeto | Abrir workspace | INT-WEB-003 |
| validation-error | Mensagem junto à ação; rascunho preservado | Corrigir ou tentar novamente | Nova submissão |
| remote-error | Mensagem segura e ação de nova tentativa | Tentar novamente | Recarrega lista |
| offline | Sem escrita; aviso de conexão | Tentar novamente | Recarrega lista |
| access-denied | Não exibe card/dados | Voltar ao dashboard | Estado pronto |
| partial-stale | Dados identificados como desatualizados | Atualizar | Recarrega lista |

### INT-WEB-002 — Criar projeto

**Surface**: SURF-WEB-OPERATIONS  
**Surface Type**: WEB  
**Change Type**: NEW  
**Purpose**: Criar um projeto local vazio com o menor atrito possível.  
**Actors and Permissions**: Usuário autenticado.  
**Entry and Navigation**: Botão `Novo projeto`; fecha por cancelar, Escape ou confirmação.  
**Content and Data**: Diálogo modal com título `Novo projeto`, campo `Nome do projeto`, ajuda curta e ações `Cancelar` e `Criar projeto`.  
**Actions and Behavior**: Enter submete quando o nome é válido; Escape cancela; não há campos Todoist.  
**Validation and Feedback**: Nome vazio recebe mensagem associada ao campo; falha remota mantém o texto; confirmação abre o workspace.  
**Responsive/Adaptive Behavior**: Modal central no desktop; folha inferior/tela cheia em telefone, com ações fixas e teclado virtual sem ocultar o campo.  
**Accessibility**: Foco inicial no campo; foco fica contido no diálogo e retorna ao botão de origem; `aria-describedby` associa ajuda e erro.  
**Localization**: Nome não é traduzido; rótulos e erros em pt-BR.  
**Components and Design System**: Reutiliza padrão de settings modal, inputs, botões e scrim existentes.  
**Integration and Contracts**: `POST /projects` conforme contrato.  
**Telemetry**: `project_creation_submitted`, `project_creation_succeeded`, `project_creation_failed`, sem nome.  
**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/int-web-002.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | Campo vazio e criar desabilitado | Digitar, cancelar | Campo válido |
| loading | N/A — diálogo não carrega dados remotos | N/A | N/A |
| empty | N/A — diálogo contém somente formulário | N/A | N/A |
| ready | Campo vazio e criar desabilitado | Digitar, cancelar | Campo válido |
| processing | Spinner no botão; campos bloqueados | Nenhuma escrita repetida | Sucesso ou erro |
| success | Fecha e anuncia criação | Nenhuma | INT-WEB-003 |
| validation-error | Erro no campo, rascunho preservado | Corrigir, tentar, cancelar | Nova submissão/fechar |
| remote-error | Erro no rodapé, rascunho preservado | Corrigir, tentar, cancelar | Nova submissão/fechar |
| offline | Erro de submissão por conexão indisponível | Tentar novamente ou cancelar | Nova submissão/fechar |
| access-denied | N/A — usuário autenticado pode criar projeto | N/A | N/A |
| partial-stale | N/A — criação não usa dados em cache | N/A | N/A |

### INT-WEB-003 — Workspace local do projeto

**Surface**: SURF-WEB-OPERATIONS  
**Surface Type**: WEB  
**Change Type**: MODIFIED  
**Purpose**: Manter o Gantt e o planejamento existentes, agora sobre dados locais e no projeto escolhido.  
**Actors and Permissions**: Proprietário/editor editam; leitor consulta.  
**Entry and Navigation**: Card do dashboard, deep link do projeto e troca de projeto; breadcrumb/nome retorna ao dashboard.  
**Content and Data**: Preserva topbar, commandbar, filtros, timeline, editor, calendário, histórico, dependências e feedback atuais. Altera o seletor de projeto para lista local e remove pílula/diagnóstico Todoist.  
**Actions and Behavior**: Criação inicial oferece `Nova seção` e `Nova tarefa`; tarefa pode estar na raiz ou seção. O Gantt, drag, resize, simulação e relações mantêm interação existente. Mudanças de dados devem ser incrementais, sem redesenho bruto de layout ou componentes estáveis.  
**Validation and Feedback**: Mantém toasts, confirmações destrutivas e proteção contra rascunho; erros locais não redirecionam ao Todoist.  
**Responsive/Adaptive Behavior**: Preserva breakpoints, virtualização, gestos e alternativas por toque existentes.  
**Accessibility**: Preserva foco de linha, atalhos, navegação de árvore, menus contextuais e nomes acessíveis; novos controles seguem o mesmo baseline.  
**Localization**: Troca termos `deadline` por `Data final planejada` quando exibidos em novos fluxos locais; mantém i18n viável.  
**Components and Design System**: Reutiliza `App.vue`, painéis, componentes de Gantt, tokens e CSS atuais; novos controles devem compor esses padrões.  
**Integration and Contracts**: Consome workspace por projeto e eventos do contrato; versão desatualizada solicita atualização sem perder seleção local quando possível.  
**Telemetry**: `workspace_opened`, `section_created`, `task_created`, `workspace_access_denied`; sem títulos, descrições ou e-mails.  
**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/int-web-003.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| loading | Shell atual e skeleton do workspace | Voltar | Ready ou erro |
| empty | Gantt vazio com ações para seção e tarefa | Criar seção/tarefa | Workspace pronto |
| ready | Layout Gantt existente e dados locais | Conforme papel | Mutação, painel ou dashboard |
| processing | Feedback existente de operação | Cancelar quando disponível | Workspace atualizado |
| success | Toast de confirmação e dados atualizados | Continuar | Estado pronto |
| validation-error | Toast/painel existente, rascunho preservado | Corrigir/tentar | Nova ação |
| remote-error | Toast seguro e ação de nova tentativa | Tentar novamente | Nova ação |
| offline | Aviso de conexão e escrita indisponível | Atualizar | Estado atual |
| access-denied | Mensagem sem dados e retorno ao dashboard | Voltar | Dashboard |
| partial-stale | Aviso de dados desatualizados | Atualizar | Estado atual |
| initial | N/A — entrada sempre carrega projeto | N/A | N/A |

### INT-WEB-004 — Pessoas e acesso

**Surface**: SURF-WEB-OPERATIONS  
**Surface Type**: WEB  
**Change Type**: MODIFIED  
**Purpose**: Permitir ao proprietário cadastrar responsáveis e convidar membros sem perturbar o Gantt.  
**Actors and Permissions**: Somente proprietário; editor/leitor não vê ações de alteração.  
**Entry and Navigation**: Nova aba `Pessoas e acesso` no diálogo de configurações já existente.  
**Content and Data**: Lista de pessoas por nome e e-mail opcional; membros ativos por papel; convites pendentes; ações para cadastrar pessoa, convidar por e-mail e alterar/remover acesso.  
**Actions and Behavior**: Responsável pode ser pesquisado por nome no editor da tarefa; convite exige e-mail e papel; não concede acesso até aceite.  
**Validation and Feedback**: Nome/e-mail/papel validados junto ao campo; confirma exclusão de pessoa e explica que tarefas ficam sem responsável.  
**Responsive/Adaptive Behavior**: Mantém modal atual; listas empilham e ações por item vão a menu em telefone.  
**Accessibility**: Tabs, listas e menus seguem padrões atuais; confirmação anuncia consequência da exclusão.  
**Localization**: Termos canônicos: Pessoa, Responsável, Membro, Convite, Proprietário, Editor e Leitor.  
**Components and Design System**: Reutiliza settings modal, tabs, inputs, soft/primary/danger buttons e toasts.  
**Integration and Contracts**: Recursos aninhados de pessoas, membros e convites sob o projeto.  
**Telemetry**: eventos de abertura, convite e aceite/recusa sem e-mail.  
**Wireframe Requirement**: REQUIRED  
**Wireframe**: wireframes/int-web-004.md

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | Aba abre sem dados renderizados | Nenhuma | Carregamento |
| loading | Aba carrega e lista dados | Nenhuma escrita | Pronto ou erro |
| empty | Orientação para cadastrar/convidar | Cadastrar/convidar | Lista pronta |
| ready | Lista de pessoas, membros e convites | Cadastrar, convidar, editar | Processamento |
| processing | Controle afetado bloqueado | Cancelar seguro | Lista atualizada |
| success | Toast de confirmação | Continuar | Lista atualizada |
| validation-error | Erro associado ao formulário | Corrigir/tentar | Nova submissão |
| remote-error | Erro seguro junto ao formulário | Tentar novamente | Nova submissão |
| offline | Escrita indisponível | Atualizar | Novo carregamento |
| access-denied | Aba não é exibida | N/A | N/A |
| partial-stale | Dados identificados como desatualizados | Atualizar | Novo carregamento |

### INT-WEB-005 — Configuração Todoist obrigatória

**Surface**: SURF-WEB-ACCESS  
**Surface Type**: WEB  
**Change Type**: REMOVED  
**Purpose**: Remover o bloqueio de entrada baseado em conexão externa.  
**Actors and Permissions**: Usuário autenticado.  
**Entry and Navigation**: Pós-login segue diretamente para INT-WEB-001.  
**Content and Data**: `TodoistSetup.vue`, ações OAuth e seleção remota não são apresentados.  
**Actions and Behavior**: Após login, o sistema encaminha diretamente ao dashboard local.  
**Validation and Feedback**: Falhas de sessão usam o fluxo de autenticação existente.  
**Responsive/Adaptive Behavior**: N/A — fluxo removido em todos os tamanhos de tela.  
**Accessibility**: N/A — não há interface remanescente; o dashboard recebe foco e heading principal.  
**Localization**: N/A — textos removidos.  
**Components and Design System**: `TodoistSetup.vue` sai do fluxo; dashboard reutiliza componentes existentes.  
**Integration and Contracts**: Não há chamada Todoist; pós-login usa a lista local de projetos.  
**Telemetry**: `todoist_setup_bypassed` sem dados pessoais.  
**Wireframe Requirement**: N/A  
**Wireframe**: N/A — remoção de fluxo.

**States**:

| State | Expected Presentation | Available Actions | Transition/Exit |
|---|---|---|---|
| initial | N/A — fluxo removido | N/A | Dashboard |
| loading | N/A — fluxo removido | N/A | Dashboard |
| empty | N/A — fluxo removido | N/A | Dashboard |
| ready | N/A — fluxo removido | N/A | Dashboard |
| processing | N/A — fluxo removido | N/A | Dashboard |
| success | N/A — fluxo removido | N/A | Dashboard |
| validation-error | N/A — fluxo removido | N/A | Dashboard |
| remote-error | N/A — fluxo removido | N/A | Dashboard |
| offline | N/A — fluxo removido | N/A | Dashboard |
| access-denied | N/A — fluxo removido | N/A | Dashboard |
| partial-stale | N/A — fluxo removido | N/A | Dashboard |

## Cross-Surface Rules

### Navigation and Parity

Há apenas a SPA responsiva. Dashboard é a raiz autenticada; workspace preserva seu layout e interações, com retorno ao dashboard. Não existe gate Todoist.

### Shared Content and Terminology

Usar `Projeto`, `Seção`, `Tarefa`, `Pessoa`, `Responsável`, `Membro`, `Convite`, `Proprietário`, `Editor`, `Leitor`, `Data inicial planejada` e `Data final planejada`.

### Shared Accessibility and Input

Toda ação por pointer possui equivalente de teclado; foco é visível; modais contêm e retornam foco; alvo touch tem tamanho adequado; estados e status usam texto e ícone além de cor.

## Traceability

| Interaction ID | User Stories | Functional Requirements | Success Criteria | Contracts |
|---|---|---|---|---|
| INT-WEB-001 | US-001 | FR-001–003 | SC-001, SC-005 | projects-api.md |
| INT-WEB-002 | US-001 | FR-001–002 | SC-001 | projects-api.md |
| INT-WEB-003 | US-002, US-003, US-005 | FR-004–014, FR-018–020 | SC-002, SC-003, SC-005 | projects-api.md |
| INT-WEB-004 | US-004 | FR-007, FR-015–017, FR-020–021 | SC-004 | projects-api.md |
| INT-WEB-005 | US-001 | FR-002, FR-018 | SC-001 | projects-api.md |

## Wireframes

| Interaction ID | Requirement | Artifact | Notes |
|---|---|---|---|
| INT-WEB-001 | REQUIRED | wireframes/int-web-001.md | Dashboard desktop/mobile |
| INT-WEB-002 | REQUIRED | wireframes/int-web-002.md | Diálogo de criação |
| INT-WEB-003 | REQUIRED | wireframes/int-web-003.md | Mudança incremental do workspace |
| INT-WEB-004 | REQUIRED | wireframes/int-web-004.md | Aba de pessoas e acesso |

## Validation Summary

- Coverage matrix reviewed: yes
- All inventory items detailed: yes
- Canonical states resolved: yes
- Required wireframes present: yes
- Accessibility requirements resolved: yes
- Contract mappings verified: yes
- Placeholders or open decisions remaining: 0
