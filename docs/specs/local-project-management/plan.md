# Plano de Implementação: Gerenciamento Local de Projetos

**Feature**: `local-project-management` | **Data**: 2026-08-25 | **Spec**: [spec.md](spec.md)

## Summary

Substituir a seleção e sincronização obrigatórias do Todoist por um domínio local de projetos. A transição limpa dados de teste, preserva autenticação, SPA, Gantt e motor de scheduling, e redireciona os fluxos de workspace para persistência e autorização por projeto.

## Technical Context

**Language/Version**: PHP 8.4+, TypeScript 5.9.
**Primary Dependencies**: Laravel 12, Vue 3, Pinia e Vite.
**Storage**: MySQL `utf8mb4`; fila persistente para operações de planejamento já existente.
**Testing**: PHPUnit e Vitest.
**Target Platform**: Aplicação web com sessão server-side, SSE e navegador responsivo.
**Project Type**: Monólito modular com SPA.
**Performance Goals**: Workspace e Gantt para até 2.000 tarefas por projeto; indicadores da lista sem carregar todos os workspaces.
**Constraints**: Projetos e tarefas locais são a fonte de verdade; sem migração dos dados de teste; autorização por projeto obrigatória.
**Scale/Scope**: Um usuário pode possuir ou participar de vários projetos; seções possuem profundidade ilimitada.

## Constitution Check

*GATE: PASS antes da Phase 0; rechecado após o design.*

| Princípio | Status | Notas |
|---|---|---|
| Fonte de verdade local | PASS | Persistência local substitui snapshots e escrita Todoist. |
| Core determinístico | PASS | O motor recebe tarefas e relações locais. |
| Integridade estrutural | PASS | Entidades distintas impedem tarefas-filhas e preservam grafo válido. |
| Acesso explícito e pessoas | PASS | Membro, convite e responsável são separados. |
| Qualidade, segurança e UX | PASS | Mantém sessão, testes, SSE, responsividade e auditoria. |

## Interaction Surface Architecture

**Surface Catalog**: `docs/architecture/interaction-surfaces.md` deve ser atualizado para substituir a pré-configuração Todoist pela área inicial de projetos.
**Interface Design Applicability**: REQUIRED — a tela inicial autenticada e o workspace ganham fluxos e estados novos.

| Surface ID | Feature Coverage | Technology Decision | Module/Repository | Notes |
|---|---|---|---|---|
| SURF-WEB-APP | FULL | Vue 3, TypeScript, Pinia e Vite | `resources/js/` | SPA responsiva existente; remover gate Todoist. |

## Project Structure

### Documentation

```text
docs/specs/local-project-management/
├── spec.md
├── research.md
├── data-model.md
├── plan.md
└── contracts/projects-api.md
```

### Source Code

```text
app/Domain/Scheduling/          # motor preservado e adaptado a tarefas locais
app/Http/Controllers/Api/       # recursos locais e autorização por projeto
app/Services/                   # snapshots/repositórios locais e operações
database/migrations/            # novo domínio e limpeza de teste
resources/js/                   # dashboard de projetos e workspace
routes/api.php                  # rotas versionadas por projeto
tests/Feature/ tests/Unit/      # autorização, API, domínio e regressão
```

**Structure Decision**: criar módulos locais de projeto, seção, tarefa, pessoa, membro e convite; remover do fluxo ativo os controllers, gateways, jobs e configuração Todoist. O motor de scheduling não depende de controllers nem de integração externa.

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
|---|---|---|---|
| Colunas do banco | snake_case | migrations e constraints | migrations Laravel |
| DTO backend | camelCase | validação de request | controllers e serviços de aplicação |
| DTO frontend | camelCase | parser de contrato no fetch | tipos e contratos em `resources/js/` |
| Payload API | camelCase | contrato nos dois lados | `contracts/projects-api.md` |
| URL e parâmetros | kebab-case | rotas | `routes/api.php` |

**Mapper layer (DB <-> DTO)**: serviços de leitura e escrita locais são responsáveis pela conversão.

**Validação de schema**: requests e responses; contratos e parsers do frontend validam campos obrigatórios antes de atualizar a store.

## Sequência de Implementação

1. Criar migrations de substituição e rotina explícita de limpeza dos dados de teste; remover o bootstrap obrigatório de integração Todoist.
2. Implementar persistência local, regras de autorização, pessoas, membros e convites; cobrir isolamento e papéis.
3. Adaptar tarefas, seções, dependências e status calculado ao novo modelo; portar snapshots do workspace para leitura local.
4. Adaptar Gantt, calendário, simulação, reagendamento, caminho crítico e auditoria para IDs e dados locais.
5. Criar API de lista/criação de projetos e recursos do workspace; preservar idempotência de escritas e eventos SSE.
6. Substituir a tela de configuração Todoist pelo dashboard de projetos e conectar o workspace por projeto.
7. Remover rotas, jobs, webhooks, segredos e testes Todoist do fluxo ativo; manter uma fronteira opcional vazia apenas se houver plano aprovado de reintegração.
8. Executar testes de domínio, feature, autorização, componentes, build e cenários end-to-end do quickstart.

## Complexity Tracking

Nenhuma violação da constituição é necessária.
