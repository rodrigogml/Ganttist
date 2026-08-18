# Implementation Plan: SincronizaÃ§Ã£o com Todoist

**Feature**: `todoist-synchronization` | **Date**: 2026-08-17 | **Spec**: [spec.md](spec.md)

## Summary

Completar adapter, ingestÃ£o de webhook, fila, reconciliaÃ§Ã£o incremental, deduplicaÃ§Ã£o/ordem, conflito e eventos de workspace. Reaproveitar gateway/HMAC, substituindo timestamp como mecanismo de estado.

## Technical Context

PHP 8.4+/Laravel 12; Vue 3/TypeScript; MySQL 9.7/fila persistente; contract tests, integraÃ§Ã£o e E2E; HTTPS/webhook/SSE; meta de convergÃªncia segura e 2.000 tarefas; sem transaÃ§Ã£o local aguardando Todoist.

## Interaction Surface Architecture

**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)
**Interface Design Applicability**: REQUIRED.

| Surface ID | Feature Coverage | Technology Decision | Module/Repository | Notes |
|---|---|---|---|---|
| SURF-WEB-OPERATIONS | FULL | Vue/TypeScript | `resources/js` | Estado e reconciliaÃ§Ã£o visÃ­veis |
| SURF-TODOIST | FULL | Adapter PHP/HTTP | `app/Infrastructure/Todoist` | Ãšnica fronteira externa |

## Constitution Check

| PrincÃ­pio | Status | Notas |
|---|---|---|
| I | PASS | Adapter exclusivo. |
| II | PASS | Dados reconciliados alimentam core. |
| III | PASS | Evento/command idempotentes e recuperÃ¡veis. |
| IV | PASS | HMAC, tokens e logs minimizados. |
| V | PASS | SSE sÃ³ publica projeÃ§Ã£o autorizada. |

## Project Structure

Evoluir `TodoistGateway`, `TodoistSyncService`, webhook, jobs, `todoist_events`/`sync_operations`, stream e DTO de workspace; adicionar contract tests, replay de eventos e testes multi-cliente.

## ConvenÃ§Ãµes de Borda

| Camada | Case style | ValidaÃ§Ã£o | Fonte da verdade |
|---|---|---|---|
| DB | snake_case | event/operation uniqueness | migrations |
| Todoist mapper | provider shape | contract tests | adapter contracts |
| API/SSE/SPA | camelCase | event/response schema | contracts |

**Mapper layer**: adapter e reconciler; **ValidaÃ§Ã£o**: payload webhook, snapshot e eventos publicados.
