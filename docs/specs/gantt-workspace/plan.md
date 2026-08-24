# Implementation Plan: Workspace de Projeto Gantt

**Feature**: `gantt-workspace` | **Date**: 2026-08-17 | **Spec**: [spec.md](spec.md)

## Summary

Construir projeÃ§Ã£o autorizada de um projeto Todoist: Ã¡rvore preservada, grupos derivados, calendÃ¡rio/configuraÃ§Ã£o e estados calculados. Reaproveitar o carregamento de snapshot e substituir a projeÃ§Ã£o simplificada atual.

## Technical Context

**Language/Version**: PHP 8.4+, TypeScript 5.x. **Dependencies**: Laravel 12, Vue 3, Pinia. **Storage**: MySQL 9.7 + Todoist. **Testing**: PHPUnit, Vitest, E2E. **Platform**: SPA HTTPS. **Performance/Scope**: 2.000 tarefas nominais; 5.000 em stress. **Constraints**: Todoist mantÃ©m campos nativos; core produz estado derivado.

## Interaction Surface Architecture

**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)
**Interface Design Applicability**: REQUIRED.

| Surface ID | Feature Coverage | Technology Decision | Module/Repository | Notes |
|---|---|---|---|---|
| SURF-WEB-OPERATIONS | FULL | Vue/TypeScript SPA | `resources/js` | Ãrvore e timeline sincronizadas |

## Constitution Check

| PrincÃ­pio | Status | Notas |
|---|---|---|
| Todoist como fonte nativa | PASS | Snapshot nÃ£o vira rÃ©plica autoritativa. |
| Core determinÃ­stico | PASS | ProjeÃ§Ã£o consome resultado do domÃ­nio. |
| Integridade/sincronizaÃ§Ã£o | PASS | Estado possui versÃ£o/sincronizaÃ§Ã£o explÃ­cita. |
| Qualidade/seguranÃ§a | PASS | Isolamento por usuÃ¡rio/projeto e testes de contrato. |
| SPA responsiva | PASS | Interface segue catÃ¡logo. |

## Project Structure

Evoluir `app/Http/Controllers/Api/WorkspaceController.php`, serviÃ§o de projeÃ§Ã£o em `app/Domain`/`app/Services`, consultas de configuraÃ§Ã£o em `database`, tipos/store em `resources/js`, e testes de contrato em `tests/Feature`. A projeÃ§Ã£o nÃ£o pode conter regra matemÃ¡tica duplicada no controller.

Gestos temporais serão isolados de `App.vue` por uma máquina de estados/composable em `resources/js/composables`, com utilitários puros de snap e mapeamento de endpoints em `resources/js/utils`. O endpoint de datas aceitará intents explícitos `MOVE`, `RESIZE_START` e `RESIZE_END`; apenas o primeiro desloca início e deadline juntos. Resize persiste diretamente os campos nativos escolhidos e, em seguida, força reconciliação da projeção. A criação gráfica reutiliza `DependencyController`, cuja validação de escopo, grupos, duplicidade e ciclo permanece autoritativa.

O cliente usa um único conjunto de listeners globais durante cada gesto e um único overlay SVG para preview de conexão, evitando listeners permanentes por timeblock. Atualizações de ponteiro são quantizadas por largura de coluna e limitadas a uma mutação visual por frame. Grupos/seções continuam derivados e não recebem resize.

No editor, relações são derivadas do contrato existente em duas coleções computadas: entradas (`to === tarefa atual`) e saídas (`from === tarefa atual`). A apresentação reutiliza o ID da relação para remoção e resolve o título pelo workspace, sem duplicar dados nem alterar o contrato da API.

O editor consulta colaboradores e comentários somente ao abrir uma tarefa. Campos nativos usam o gateway Todoist (`description`, `priority`, `assignee_id`); comentários possuem endpoints próprios de listagem, criação e edição, e são reconciliados após mutação. A criação de relação deixa de renderizar um select completo: busca local normalizada percorre o snapshot paginado, limita a lista apresentada e só habilita o tipo após escolher a outra tarefa. A direção do quadro determina `from`/`to`; um SVG compacto reutiliza a semântica dos endpoints do Gantt para pré-visualizar FS/SS/FF/SF.

As colunas fixas são definidas por um catálogo frontend com ID, rótulo e largura estável. A largura total sticky é sempre derivada da largura de Tarefa mais as colunas opcionais visíveis e alimenta cabeçalho, linhas, origem da timeline, virtualização e autoscroll pelo mesmo valor. Somente Tarefa possui resize nesta entrega; seu valor é limitado entre a largura base existente e `25vw`. Preferências ficam em `localStorage`, com validação e fallback. “Comentários” usa `note_count` já presente no objeto de tarefa Todoist v1, evitando requisições N+1 ao endpoint de comentários.

O mapper do workspace expõe `description` nativa e mantém `priority` no valor bruto da API Todoist. A SPA converte somente para apresentação (`4→P1`, `3→P2`, `2→P3`, `1→P4`) e nunca exibe prioridade ou descrição em linhas derivadas/agrupadoras.

O gateway combina `/tasks` (ativas) com `/tasks/completed/by_completion_date` (concluídas). Como o Todoist limita cada consulta histórica a três meses, a data `created_at` do projeto define o início e as janelas são consultadas em lotes paralelos, com paginação por cursor dentro de cada janela. Itens concluídos são normalizados explicitamente, deduplicados por ID e sobrescritos por uma versão ativa em caso de reabertura. Raízes ligadas a uma seção histórica indisponível são mantidas no workspace em vez de descartadas.

O filtro de status usa um conjunto reativo dos cinco estados calculados, inicialmente completo. Cada checkbox altera somente sua parcela; “Desbloqueadas” opera sobre `OPENED`, `SCHEDULED` e `LATE` e expõe estado indeterminado para seleção parcial. O popover fecha por Escape ou evento externo de ponteiro, preservando as marcações já aplicadas.

## ConvenÃ§Ãµes de Borda

| Camada | Case style | ValidaÃ§Ã£o | Fonte da verdade |
|---|---|---|---|
| DB | snake_case | migration/constraint | migrations |
| DomÃ­nio/projeÃ§Ã£o | objetos nomeados | testes golden | contratos de domÃ­nio |
| API/SPA | camelCase | schema nos dois lados | contracts da feature |

**Mapper layer**: serviÃ§o de projeÃ§Ã£o converte snapshot+metadados em DTO do workspace. **ValidaÃ§Ã£o**: responses e schema da SPA.
