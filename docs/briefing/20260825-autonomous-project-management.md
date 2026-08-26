# Briefing do Projeto: Ganttist autônomo

**Data**: 2026-08-25
**Status**: Validado
**Versão**: 1.0

---

## 1. Visão e Propósito

**O que é**: Uma evolução do Ganttist para um gerenciador de projetos autônomo, com projetos, estruturas, tarefas, pessoas, permissões, Gantt e planejamento mantidos localmente.

**Problema que resolve**: Permitir o planejamento e acompanhamento de projetos sem exigir uma conta ou conexão com o Todoist.

**Proposta de valor**: Oferecer a uma pessoa gestora uma visão de projetos, cronogramas e responsabilidades, inclusive de membros da equipe que não usam o sistema.

## 2. Usuários e Stakeholders

| Ator | Papel | Ações principais |
|---|---|---|
| Proprietário | Gestor do projeto | Cria projetos, gerencia membros, estrutura, tarefas e pode excluir o projeto. |
| Editor | Membro com alteração | Cria e altera a estrutura e as tarefas do projeto. |
| Leitor | Membro com consulta | Consulta o projeto sem alterar dados. |
| Responsável | Pessoa usuária ou interna | Recebe atribuição de tarefas; não precisa ser membro nem usar o sistema. |

**Stakeholders de decisão**: Usuário solicitante.

## 3. Interfaces e Canais

| Interface/Canal | Usuários | Dispositivos/Plataformas | Cobertura | Conectividade | Paridade esperada |
|---|---|---|---|---|---|
| SPA web autenticada | Proprietário, editor e leitor | Desktop, tablet e celular; navegadores modernos | MVP | Online | Mesmo domínio funcional com interação adaptada ao dispositivo. |

**Restrições tecnológicas já obrigatórias**: PHP/Laravel, Vue 3, TypeScript, Pinia, MySQL, API JSON versionada, SSE e Gantt próprio existentes.

## 4. Escopo

### MVP (Essencial)

1. Após o login, exibir os projetos locais do usuário em cards ou lista, com nome, total de tarefas, progresso e quantidade de tarefas atrasadas, além da ação de criar projeto.
2. Criar projeto local vazio com nome obrigatório, sem integração com Todoist.
3. Criar seções em árvore de profundidade ilimitada e tarefas na raiz do projeto ou em qualquer seção; tarefas não contêm outras tarefas.
4. Manter em cada tarefa título, descrição, responsável, datas planejadas de início e fim e data de conclusão real.
5. Calcular o status da tarefa sem seleção manual: concluída, atrasada, bloqueada, agendada ou aberta, na ordem de prioridade definida.
6. Manter Gantt, calendário, dependências FS/SS/FF/SF, caminho crítico, simulação, reagendamento e demais capacidades atuais sobre dados locais.
7. Cadastrar pessoas internas com nome obrigatório e e-mail opcional; atribuí-las sem convite ou uso do sistema.
8. Gerir acesso por projeto com os papéis proprietário, editor e leitor.
9. Reiniciar os dados de teste existentes, sem migração ou compatibilidade com usuários, projetos ou Gantts atuais.

### Pós-MVP (Desejável)

1. Sincronização opcional com Todoist, sem tornar a integração obrigatória ou fonte de verdade dos dados locais.
2. Detalhar o fluxo de convite e aceite para usuários do sistema.

### Fora de Escopo

- Exigir conexão, conta ou autorização do Todoist para acessar ou criar projetos.
- Preservar ou migrar os dados de teste atuais.
- Subtarefas ou tarefas que contenham outras tarefas.

## 5. Prioridades e Trade-offs

**Ordem de prioridade**: Autonomia e integridade dos dados locais > preservação das capacidades de planejamento > experiência de uso > integração futura com serviços externos.

**Decisões explícitas**:

- Todoist deixa de ser obrigatório e de ser a fonte de verdade do núcleo do produto.
- A ruptura de dados é aceita porque o ambiente contém somente dados de teste.
- Pessoas responsáveis podem existir sem conta, convite ou acesso ao projeto.

## 6. Restrições

| Restrição | Valor | Notas |
|---|---|---|
| Prazo | Não definido | Não informado. |
| Equipe | Não definida | Não informado. |
| Budget | Não definido | Não informado. |
| Técnica | Stack atual preservada | A mudança de domínio não exige troca de stack. |
| Dados | Ruptura autorizada | Limpar usuários e projetos de teste, sem retrocompatibilidade. |

## 7. Stack Técnica

| Camada | Tecnologia | Justificativa |
|---|---|---|
| Backend e domínio | PHP 8.4+ e Laravel | Stack atual; regras de planejamento permanecem desacopladas. |
| Interface web | Vue 3, TypeScript e Pinia | SPA atual responsiva. |
| Gantt | Implementação própria e SVG | Preserva controle sobre regras e interações. |
| Banco de dados | MySQL com `utf8mb4` | Persistência de projetos, pessoas, estrutura, tarefas e permissões. |
| Integrações | Todoist opcional no futuro | Não participa do fluxo do MVP. |

## 8. Qualidade e Padrões

**Padrões adotados**:

- Isolamento de dados e autorização por projeto para proprietário, editor e leitor.
- Regras de status, dependências e agendamento determinísticas e cobertas por testes proporcionais ao risco.
- Datas de planejamento e conclusão persistidas como dados locais, sem dependência de uma API externa.
- Acessibilidade e responsividade da SPA e do Gantt preservadas.

**Compliance**: Não definido; privacidade e retenção permanecem pendentes.

## 9. Visão de Futuro

**6 meses**: Gerenciamento local de projetos em operação, com estrutura, tarefas, pessoas, permissões e planejamento completo.

**12 meses**: Integração opcional com Todoist e evolução seletiva de colaboração, templates e relatórios.

**Riscos conhecidos**:

- A alteração da autoridade de dados exige substituir princípios, especificações, contratos e persistência voltados ao Todoist.
- O cálculo de progresso precisa de regra explícita na especificação.
- O fluxo de convite e aceite para membros usuários ainda será detalhado.

---

## Itens a Definir

| Item | Dimensão | Impacto |
|---|---|---|
| Fórmula de progresso de projeto | Escopo e prioridades | Médio |
| Fluxo de convite, aceite e remoção de membros usuários | Usuários e permissões | Médio |
| Regras de edição e exclusão de seções, pessoas e tarefas com dependências | Escopo e integridade | Alto |

---

**Próximo passo recomendado**: emendar a constituição para estabelecer o Ganttist como autoridade dos dados locais e, em seguida, especificar a feature de gerenciamento de projetos autônomo.
