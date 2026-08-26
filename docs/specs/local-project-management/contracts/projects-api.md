# Contratos: Projetos Locais

## Listar e criar projetos

**Métodos**: `GET /api/v1/projects`, `POST /api/v1/projects`

**Auth**: sessão autenticada obrigatória.

### Criação

| Campo | Tipo | Obrigatório | Validação |
|---|---|---|---|
| name | string | sim | Não vazio. |
| commandId | string | sim | Chave de idempotência por usuário e operação. |

### Resposta de projeto resumido

| Campo | Tipo | Descrição |
|---|---|---|
| id | string | Identidade do projeto. |
| name | string | Nome do projeto. |
| taskCount | número | Quantidade de tarefas. |
| progress | número | Percentual ponderado por duração. |
| overdueTaskCount | número | Tarefas atrasadas. |
| role | string | Papel do usuário atual. |
| updatedAt | timestamp | Última alteração visível. |

## Workspace local

**Métodos**: `GET /api/v1/projects/{projectId}/workspace`, `GET /api/v1/projects/{projectId}/events`

**Auth**: membro do projeto.

**Resposta**: projeto, permissões efetivas, configurações de calendário, seções, tarefas, pessoas, dependências, estatísticas derivadas e versão do workspace. O payload usa `camelCase`; dados persistidos usam a convenção interna do banco.

## Escritas de domínio

**Grupos**: seções, tarefas, pessoas, membros, convites, dependências, calendário e operações de reagendamento sob `/api/v1/projects/{projectId}/...`.

**Auth**: proprietário para membros, convites e exclusão do projeto; proprietário ou editor para estrutura, tarefas, relações, calendário e operações; leitor não pode escrever.

**Erros comuns**:

| Status | Código | Descrição |
|---|---|---|
| 403 | PROJECT_ACCESS_DENIED | Papel insuficiente ou ausência de acesso. |
| 404 | PROJECT_NOT_FOUND | Projeto não visível ao usuário. |
| 409 | PROJECT_VERSION_CONFLICT | Alteração concorrente em versão desatualizada. |
| 422 | DOMAIN_VALIDATION_ERROR | Estrutura, data, dependência ou transição inválida. |
