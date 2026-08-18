# Implementation Plan: OperaÃ§Ãµes e Reagendamento

**Feature**: `rescheduling-operations` | **Date**: 2026-08-17 | **Spec**: [spec.md](spec.md)

## Summary

Substituir aplicaÃ§Ã£o sÃ­ncrona por operaÃ§Ã£o lÃ³gica persistida: intenÃ§Ã£o, snapshot, simulaÃ§Ã£o, revalidaÃ§Ã£o, itens, batches, idempotÃªncia, recuperaÃ§Ã£o e publicaÃ§Ã£o de estado. Manter apenas affordances de ghost/painel existentes.

## Technical Context

PHP 8.4+/Laravel 12; Vue 3/TypeScript; MySQL 9.7 com filas persistentes; PHPUnit/Vitest/E2E; SPA HTTPS; cascatas atÃ© 2.000 tarefas; Todoist nÃ£o Ã© chamado dentro de transaÃ§Ã£o local.

## Interaction Surface Architecture

**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)
**Interface Design Applicability**: REQUIRED.

| Surface ID | Feature Coverage | Technology Decision | Module/Repository | Notes |
|---|---|---|---|---|
| SURF-WEB-OPERATIONS | FULL | Vue/TypeScript | `resources/js` | Ghost, confirmaÃ§Ã£o, progresso e recuperaÃ§Ã£o |

## Constitution Check

| PrincÃ­pio | Status | Notas |
|---|---|---|
| I | PASS | Escritas nativas atravessam adapter. |
| II | PASS | Core calcula snapshot, nÃ£o a SPA. |
| III | PASS | OperaÃ§Ã£o idempotente e recuperÃ¡vel Ã© obrigatÃ³ria. |
| IV | PASS | Falha parcial e destrutivas tÃªm testes/gates. |
| V | PASS | Estados claros em todos os form factors. |

## Project Structure

Evoluir `recalculations`, `recalculation_items`, `sync_operations` e jobs/serviÃ§os de aplicaÃ§Ã£o; manter controllers como comando/consulta; substituir `ScheduleApplyController` atual; adicionar integraÃ§Ã£o/E2E e projeÃ§Ãµes de operaÃ§Ã£o no workspace.

## ConvenÃ§Ãµes de Borda

| Camada | Case style | ValidaÃ§Ã£o | Fonte da verdade |
|---|---|---|---|
| DB | snake_case | state transitions + constraints | migrations |
| Command/API | camelCase | idempotency key/schema | contracts |
| SPA | camelCase | response schema | tipos/store |

**Mapper layer**: application service converte intenÃ§Ã£o em operaÃ§Ã£o. **ValidaÃ§Ã£o**: requests, snapshots e estados de item.
