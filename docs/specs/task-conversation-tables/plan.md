# Implementation Plan: Tabelas na conversa da tarefa

**Feature**: `task-conversation-tables` | **Date**: 2026-09-10 | **Spec**: [spec.md](spec.md)

## Summary

Adicionar tabelas como segundo tipo de bloco na conversa de tarefa. Comentários Markdown existentes permanecem intactos; tabelas são persistidas como um documento de planilha, renderizadas em bloco próprio e alteradas por proprietários e editores sob reserva temporária exclusiva. A reserva é transacional, dura 10 segundos, é renovada pelo cliente e é capaz de sobreviver a uma pausa sem permitir sobrescrita depois de outra aquisição.

## Technical Context

**Language/Version**: PHP 8.4+ e TypeScript 5.9.
**Primary Dependencies**: Laravel 12, Vue 3.5, Vite 7, Vitest 3 e Univer Sheets 0.25.x.
**Storage**: MySQL `utf8mb4`, documento de planilha em JSON.
**Testing**: PHPUnit/Laravel Feature Tests, Vitest e Vue Test Utils.
**Target Platform**: aplicação web responsiva em navegadores modernos.
**Project Type**: monólito Laravel com SPA Vue.
**Performance Goals**: abrir uma conversa sem carregar o runtime de planilha até a primeira tabela; suportar o documento máximo aprovado de 200 × 100 / 500 KB.
**Constraints**: uma aba por tabela; sem plugins Pro, coedição, polling, SSE de conversa, importação/exportação ou avaliação de fórmulas no servidor.
**Scale/Scope**: tabelas associadas a tarefas locais e participantes já autorizados no projeto.

## Interaction Surface Architecture

**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)
**Interface Design Applicability**: REQUIRED — a conversa, seu compositor, os estados de bloqueio e o editor de planilha exigem especificação de tela e comportamento.

| Surface ID | Feature Coverage | Technology Decision | Module/Repository | Notes |
|---|---|---|---|---|
| SURF-WEB-OPERATIONS | FULL | Vue 3, TypeScript, Vite e Univer Sheets | `resources/js/TaskCommentsWindow.vue` e novos componentes adjacentes | SPA responsiva; runtime e CSS da planilha em chunk assíncrono. |

## Constitution Check

*GATE: PASS antes do Phase 0 e rechecado após Phase 1.*

| Princípio | Status | Notas |
|---|---|---|
| I. Fonte de verdade local | PASS | Tabelas e reservas são dados locais do projeto, sem integração externa. |
| II. Core determinístico e independente da interface | PASS | Não altera cálculos de planejamento; a reserva é serviço de aplicação, não regra do Gantt. |
| III. Estrutura e integridade | PASS | Projeto, tarefa e usuário são validados; a reserva impede gravação concorrente. |
| IV. Acesso explícito | PASS | Usa exatamente proprietário/editor/leitor; não concede acesso por publicação. |
| V. Qualidade, segurança e experiência | PASS | Limites, autorização, testes de conflito, interface responsiva e fallback de recuperação são obrigatórios. |

## Project Structure

### Documentation

```text
docs/specs/task-conversation-tables/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
└── contracts/
    └── tables-api.md
```

### Source Code

```text
app/Http/Controllers/Api/ProjectController.php     # contexto e operações de tabela
database/migrations/                               # tabela e campos de reserva
routes/api.php                                     # endpoints de tabela e reserva
resources/js/TaskCommentsWindow.vue                # composição e cronologia de blocos
resources/js/types.ts                              # união de blocos e DTOs
resources/js/TaskTableBlock.vue                    # novo visualizador de tabela
resources/js/TaskTableEditor.vue                   # novo editor lazy e ciclo de reserva
resources/js/task-table/                           # adaptador Univer e normalização de documento
tests/Feature/LocalProjectsApiTest.php             # autorização, limites, reserva e persistência
resources/js/TaskCommentsWindow.test.ts            # abas, estados e recuperação
resources/js/task-table/*.test.ts                  # adaptador, renovação e blocos
```

**Structure Decision**: manter a janela de conversa como orquestradora e isolar a dependência Univer, sua serialização e o timer de renovação em componentes/composables de tabela. O controlador existente permanece a fronteira HTTP do domínio local.

## Architecture and Data Flow

1. A leitura da conversa busca comentários existentes e tabelas, normaliza ambos em blocos ordenados e só instancia o visualizador de tabela quando necessário.
2. Publicar uma nova tabela valida o documento e cria bloco independente, sem precisar de reserva, pois não há tabela existente a sobrescrever.
3. Editar tabela publicada adquire reserva atômica e recebe um token opaco. O editor renova a cada 2–3 segundos enquanto está aberto.
4. Salvar, remover ou liberar exige o token vigente. A transação compara usuário, hash do token e expiração antes de alterar o documento ou a reserva.
5. Uma solicitação tardia do mesmo token a recupera somente quando nenhuma aquisição concorrente substituiu os campos da reserva. Caso contrário, a interface preserva o rascunho e permite cópia ou publicação como novo bloco.

## Persistence Design

- Criar entidade de tabela de tarefa, sem modificar nem migrar o conteúdo de `project_task_comments`.
- Modelar os campos descritos em [data-model.md](data-model.md), com relacionamento obrigatório ao projeto, tarefa e publicador; validar que tarefa pertence ao projeto.
- Guardar o documento apenas como JSON e calcular seu tamanho sobre a representação serializada recebida antes de gravar.
- Na aquisição/renovação/salvamento/liberação, usar uma única transação e bloqueio de linha da tabela. A aquisição é permitida se a reserva não existe, expirou ou pertence ao mesmo token; uma aquisição por outro usuário substitui titular, token e expiração somente se já expirada.
- A remoção exige a mesma reserva de edição, evitando apagar tabela em edição ativa por outro participante.
- Ao duplicar uma tarefa, copiar tabelas como novos blocos, sem reserva ativa; manter o publicador original e o documento, pois a cópia é uma nova tarefa.

## Frontend Design

- Criar `TaskTableEditor.vue` como componente assíncrono; montar/destruir uma instância de Univer por abertura e converter entre seu snapshot e o documento persistido.
- Usar o preset básico de Sheets com interface, estilos, fórmulas e locale `pt-BR`; excluir colaboração, exportação, gráficos, múltiplas abas e plugins não requisitados.
- Criar `TaskTableBlock.vue` para exibição da tabela publicada, inicialmente somente leitura; reutilizar o mesmo adaptador de documento para garantir fidelidade entre editar e visualizar.
- Estender `TaskCommentsWindow.vue` com abas de compositor, união cronológica de blocos, diálogo de exclusão de tabela e indicador de reserva ocupada.
- Durante a edição, iniciar timer de renovação em 2 segundos; parar em salvar, cancelar, desmontar, falha definitiva ou perda da reserva. Não liberar no evento de página oculta.
- Em `TABLE_LOCK_LOST`, congelar o editor, exibir a mensagem de recuperação e habilitar copiar conteúdo e publicar como nova tabela. A ação de cópia usa uma representação portável do documento, sem atualizar a origem.
- Carregar o CSS e módulos do Univer somente no chunk do editor/visualizador. Medir o chunk gerado no build antes da entrega.

## API and Validation Design

Os endpoints e shapes estão em [contracts/tables-api.md](contracts/tables-api.md). O serviço de aplicação deve centralizar:

- autorização de leitura/escrita pelo papel do projeto;
- localização de tabela escopada por projeto e tarefa;
- normalização e validação estrutural do documento, incluindo uma aba, dimensões, tamanho e JSON aceito;
- geração de token criptograficamente aleatório e persistência somente de seu hash;
- decisões atômicas de adquirir, renovar, liberar e verificar a reserva;
- mapeamento explícito de conflitos para `409 TABLE_LOCKED` ou `409 TABLE_LOCK_LOST`.
- limite de frequência documentado para aquisição e renovação: 90 requisições por minuto por usuário e tabela, acima das 30 renovações/minuto esperadas com intervalo de 2 segundos.

## Convenções de Borda

| Camada | Case style | Validação | Fonte da verdade |
|---|---|---|---|
| DB columns | camelCase para a nova entidade, conforme convenção de banco aplicável | migration, FK e índices | `database/migrations/` |
| Backend DTO | snake_case na borda de conversa existente | regras de request e mapeador | `ProjectController` e serviço de tabelas |
| Frontend DTO | snake_case, espelhando o payload atual de comentários | tipos discriminados e testes de componente | `resources/js/types.ts` |
| API payload | snake_case | testes Feature e contratos | `contracts/tables-api.md` |
| URL path params | camelCase, conforme rotas atuais | router e testes Feature | `routes/api.php` |

**Mapper layer (DB <-> DTO)**: serviço de tabelas e métodos privados do `ProjectController` traduzem a entidade persistida em bloco de contexto.
**Validação de schema**: request no backend para toda escrita; resposta coberta por testes Feature e tipos discriminados no frontend.

## Validation Plan

- PHPUnit: permissões de proprietário/editor/leitor, escopo projeto/tarefa, publicação, limites, ordenação do contexto, duplicação e exclusão.
- PHPUnit: duas requisições de aquisição concorrentes resultam em um token válido; renovação tardia do titular, tomada por outro usuário, token obsoleto e liberação são cobertos com relógio controlado.
- Vitest: alternância de abas, estado sujo, exibição de tabela, indisponibilidade por reserva, congelamento após perda e publicação como cópia.
- Vitest: adaptador preserva valores, fórmulas, estilos, dimensões e mesclagens no roundtrip de snapshot.
- Build: `npm run build` confirma resolução de CSS, chunk assíncrono e tipos; a suíte PHP cobre migrations e contratos reais.
- Executar os cenários de [quickstart.md](quickstart.md), incluindo roundtrip backend–SPA real.

## Complexity Tracking

Nenhuma violação da Constituição. A reserva persistida é a menor complexidade que atende a prevenção de sobrescrita sem introduzir colaboração em tempo real.
