# Implementation Plan: DependÃªncias e Caminho CrÃ­tico

**Feature**: `dependencies-critical-path` | **Date**: 2026-08-17 | **Spec**: [spec.md](spec.md)

## Summary

Centralizar validaÃ§Ã£o de relaÃ§Ãµes e cÃ¡lculo de criticidade no domÃ­nio, com regras de grupo, conclusÃ£o, visibilidade e persistÃªncia projetadas para o Gantt. Reaproveitar tipos/topological sort; corrigir validaÃ§Ã£o de pertencimento e cÃ¡lculo/entrega de folga.

## Technical Context

**Language/Version**: PHP 8.4+, TypeScript 5.x. **Dependencies**: Laravel 12/Vue 3. **Storage**: MySQL 9.7 para metadados; Todoist para tarefas. **Testing**: golden cases, integraÃ§Ã£o e E2E. **Platform**: SPA. **Scope**: grafo acÃ­clico intraprojeto, 2.000 tarefas. **Constraints**: FS/SS/FF/SF; sem lag/lead; grupos apenas predecessores.

## Interaction Surface Architecture

**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)
**Interface Design Applicability**: REQUIRED.

| Surface ID | Feature Coverage | Technology Decision | Module/Repository | Notes |
|---|---|---|---|---|
| SURF-WEB-OPERATIONS | FULL | Vue/TypeScript SPA | `resources/js` | CriaÃ§Ã£o, inspeÃ§Ã£o e desenho SVG |

## Constitution Check

| PrincÃ­pio | Status | Notas |
|---|---|---|
| Todoist como fonte nativa | PASS | RelaÃ§Ãµes sÃ£o metadados prÃ³prios. |
| Core determinÃ­stico | PASS | Regra e criticidade no domÃ­nio. |
| Integridade/sincronizaÃ§Ã£o | PASS | Ciclo, identidade e estado validados antes da escrita. |
| Qualidade/seguranÃ§a | PASS | Casos golden de cada relaÃ§Ã£o. |
| SPA responsiva | PASS | RelaÃ§Ãµes ocultas/touch terÃ£o especificaÃ§Ã£o prÃ³pria. |

## Project Structure

Evoluir `app/Domain/Scheduling`, serviÃ§o de aplicaÃ§Ã£o para comandos de dependÃªncia, `DependencyController`, `task_dependencies`, DTOs do workspace e testes de domÃ­nio/feature. Nenhum controller farÃ¡ busca recursiva como Ãºnica fonte de validaÃ§Ã£o.

## ConvenÃ§Ãµes de Borda

| Camada | Case style | ValidaÃ§Ã£o | Fonte da verdade |
|---|---|---|---|
| DB | snake_case | unique/foreign scope + migration | migrations |
| DomÃ­nio | objetos de relaÃ§Ã£o | golden tests | domÃ­nio |
| API/SPA | camelCase | schema e contrato | contracts |

**Mapper layer**: comando valida IDs no snapshot/projeÃ§Ã£o autorizada. **ValidaÃ§Ã£o**: request e response; relaÃ§Ãµes ocultas preservam referÃªncia.
