# Modelo de Dados: Gerenciamento Local de Projetos

## Entity: Projeto

| Campo | Tipo | Restrições | Notas |
|---|---|---|---|
| id | ULID | PK | Identidade local. |
| name | string | obrigatório | Nome informado na criação. |
| owner_user_id | ULID | obrigatório | Usuário proprietário. |
| timestamps | timestamp | obrigatório | Criação e atualização. |

### Relationships

- Projeto 1:1 Configurações de projeto.
- Projeto 1:N Seções, tarefas, pessoas, membros, convites, dependências, operações e eventos de auditoria.

## Entity: Seção

| Campo | Tipo | Restrições | Notas |
|---|---|---|---|
| id | ULID | PK | Identidade local. |
| project_id | ULID | obrigatório | Projeto proprietário. |
| parent_section_id | ULID | nulo ou seção do mesmo projeto | Permite árvore de seções. |
| name | string | obrigatório | Rótulo da seção. |
| position | inteiro | obrigatório | Ordenação entre irmãos. |

### Relationships

- Seção 1:N Seções-filhas.
- Seção 1:N Tarefas.

## Entity: Tarefa

| Campo | Tipo | Restrições | Notas |
|---|---|---|---|
| id | ULID | PK | Identidade local. |
| project_id | ULID | obrigatório | Projeto proprietário. |
| section_id | ULID | nulo ou seção do mesmo projeto | Nulo representa a raiz do projeto. |
| title | string | obrigatório | Título da tarefa. |
| description | texto | nulo | Campo livre. |
| assignee_person_id | ULID | nulo | Responsável local. |
| planned_start | data civil | nulo | Início planejado. |
| planned_finish | data civil | nulo, não anterior ao início | Fim planejado. |
| completed_at | data civil | nulo | Data real; preenchida na conclusão. |
| position | inteiro | obrigatório | Ordenação na raiz ou seção. |

### State Transitions

`aberta/agendada/bloqueada/atrasada -> concluída -> estado recalculado ao reabrir`.

## Entity: Pessoa

| Campo | Tipo | Restrições | Notas |
|---|---|---|---|
| id | ULID | PK | Identidade local. |
| project_id | ULID | obrigatório | Cadastro interno por projeto. |
| name | string | obrigatório | Usado na busca interna. |
| email | string | nulo | Não cria convite automaticamente. |
| linked_user_id | ULID | nulo | Vínculo opcional com conta existente. |

## Entity: Membro e Convite

| Campo | Tipo | Restrições | Notas |
|---|---|---|---|
| project_members.project_id/user_id | ULID | par único | Acesso concedido. |
| project_members.role | enum | proprietário, editor, leitor | Proprietário único por projeto. |
| project_invitations.email | string | obrigatório | Destinatário do convite. |
| project_invitations.role | enum | editor, leitor | Papel proposto. |
| project_invitations.status | enum | pendente, aceito, recusado, revogado, expirado | Acesso somente em aceito. |

## Entity: Dependência

| Campo | Tipo | Restrições | Notas |
|---|---|---|---|
| project_id | ULID | obrigatório | Mesmo projeto das duas tarefas. |
| predecessor_task_id | ULID | obrigatório | Origem da relação. |
| successor_task_id | ULID | obrigatório | Destino da relação. |
| type | enum | FS, SS, FF, SF | Sem duplicidade por par e tipo. |

## Derived Values

- Status: concluída > atrasada > bloqueada > agendada > aberta.
- Progresso: soma da duração planejada de tarefas concluídas dividida pela soma total; tarefas sem duração entram conforme regra do core a ser validada nos testes de domínio.
- Atrasadas: tarefas não concluídas com fim planejado anterior à data civil corrente.
