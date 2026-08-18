# Implementation Plan: Auditoria e Rastreabilidade

**Feature**: `audit-traceability` | **Date**: 2026-08-17 | **Spec**: [spec.md](spec.md)

## Summary

Transformar tabelas existentes em auditoria funcional imutÃ¡vel, correlacionada a operaÃ§Ãµes, com consulta paginada e diagnÃ³stico seguro. A implementaÃ§Ã£o atual Ã© classificada como ausente alÃ©m do schema inicial.

## Technical Context

PHP 8.4+/Laravel 12; Vue 3/TypeScript; MySQL 9.7; PHPUnit/integraÃ§Ã£o/E2E; SPA HTTPS; histÃ³rico sob demanda; isolamento completo e minimizaÃ§Ã£o de dados; retenÃ§Ã£o parametrizada atÃ© decisÃ£o final de produto.

## Interaction Surface Architecture

**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)
**Interface Design Applicability**: REQUIRED.

| Surface ID | Feature Coverage | Technology Decision | Module/Repository | Notes |
|---|---|---|---|---|
| SURF-WEB-OPERATIONS | FULL | Vue/TypeScript | `resources/js` | HistÃ³rico por projeto/tarefa |

## Constitution Check

| PrincÃ­pio | Status | Notas |
|---|---|---|
| I | PASS | Somente metadados e referÃªncias necessÃ¡rias. |
| II | PASS | Eventos descrevem resultados autorizados do core. |
| III | PASS | OperaÃ§Ãµes/falhas possuem cadeia causal. |
| IV | PASS | Imutabilidade, isolamento e dados mÃ­nimos. |
| V | PASS | Consulta acessÃ­vel/paginada. |

## Project Structure

Evoluir `audit_events` e serviÃ§os de aplicaÃ§Ã£o; instrumentar comandos/reconciler sem acoplar logs tÃ©cnicos ao histÃ³rico funcional; criar consulta autorizada, DTOs e painel sob demanda; testes de isolamento, causalidade, paginaÃ§Ã£o e retenÃ§Ã£o.

## ConvenÃ§Ãµes de Borda

| Camada | Case style | ValidaÃ§Ã£o | Fonte da verdade |
|---|---|---|---|
| DB | snake_case | append-only/ownership constraints | migrations |
| API/SPA | camelCase | response schema e paginaÃ§Ã£o | contracts |

**Mapper layer**: audit writer e query service. **ValidaÃ§Ã£o**: eventos de domÃ­nio/operacionais e DTO filtrado por usuÃ¡rio.
