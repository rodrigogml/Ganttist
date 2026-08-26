# Pesquisa Técnica: Gerenciamento Local de Projetos

## Decision 1: Autoridade e transição dos dados

**Decision**: Substituir o fluxo operacional baseado em Todoist por persistência local autoritativa. A limpeza aprovada deve remover os dados de teste e as tabelas de integração/sincronização que deixarem de servir ao novo domínio; não haverá migração de Gantts existentes.

**Rationale**: O produto precisa funcionar sem autorização externa, e os dados existentes são apenas de teste.

**Alternatives considered**:

- Manter o Todoist como fonte de verdade: rejeitado, viola a autonomia aprovada.
- Sincronizar durante a transição: rejeitado, não há dados de produção a preservar e adiciona complexidade sem valor.

## Decision 2: Estrutura local e tarefas

**Decision**: Representar seções e tarefas como entidades distintas. Seções se relacionam recursivamente; tarefas referenciam apenas projeto e seção opcional. Dependências referenciam tarefas locais.

**Rationale**: Impede tarefas-filhas no banco e preserva hierarquia ilimitada somente para seções.

**Alternatives considered**:

- Uma única tabela polimórfica para seções e tarefas: rejeitada, permitiria combinações inválidas e tornaria as regras de planejamento menos claras.
- Reutilizar identificadores externos como identidade de tarefas: rejeitado, não há fonte externa autoritativa no MVP.

## Decision 3: Autorização e pessoas

**Decision**: Separar pessoa responsável de usuário autenticado e de membro de projeto. Convites por e-mail criam acesso somente após aceite; responsável não concede acesso implícito.

**Rationale**: O gestor pode planejar para pessoas que não usam o produto, preservando a autorização explícita.

**Alternatives considered**:

- Exigir conta para todo responsável: rejeitado, contraria o uso por equipes não usuárias.
- Conceder acesso ao atribuir tarefa: rejeitado, viola o princípio de menor privilégio.

## Decision 4: Reuso do motor de planejamento

**Decision**: Manter o domínio de calendário, projeção, dependências, criticidade e reagendamento; substituir somente os adaptadores e snapshots Todoist por repositórios locais e payloads do workspace.

**Rationale**: Preserva o diferencial do Gantt sem carregar dependência de sincronização externa.

**Alternatives considered**:

- Reescrever o motor de scheduling: rejeitado, risco alto e sem requisito funcional que o justifique.

## Decision 5: Superfície e contratos

**Decision**: Evoluir a SPA web responsiva existente com uma área inicial de projetos e um workspace por projeto. A API autenticada expõe recursos locais versionados e emite atualizações de workspace por SSE após escrita confirmada.

**Rationale**: Mantém a superfície humana, os padrões de sessão e a atualização sem recarga já existentes.

**Alternatives considered**:

- Criar uma segunda aplicação: rejeitado, duplicaria autenticação e interface sem benefício.
- Manter a tela de conexão Todoist antes do workspace: rejeitado, bloqueia a jornada principal aprovada.
