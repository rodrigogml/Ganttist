# Implementation Plan: CalendÃ¡rio e Datas das Tarefas

**Feature**: `calendar-task-dates` | **Date**: 2026-08-17 | **Spec**: [spec.md](spec.md)

## Summary

Evoluir o domÃ­nio de calendÃ¡rio para receber configuraÃ§Ãµes reais por Gantt, preservar a semÃ¢ntica de tarefas sem data e produzir grupos/duraÃ§Ã£o/estados corretos. O `WorkCalendar` Ã© reaproveitÃ¡vel; controladores que aceitam calendÃ¡rio e duraÃ§Ã£o do cliente serÃ£o substituÃ­dos.

## Technical Context

**Language/Version**: PHP 8.4+, TypeScript 5.x. **Dependencies**: Laravel 12, Vue 3. **Storage**: MySQL 9.7, campos nativos no Todoist. **Testing**: golden tests PHP, integraÃ§Ã£o MySQL e E2E. **Platform**: SPA HTTPS. **Performance/Scope**: cÃ¡lculo determinÃ­stico para 2.000 tarefas. **Constraints**: datas civis, dias inteiros, timezone de planejamento explÃ­cito.

## Interaction Surface Architecture

**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)
**Interface Design Applicability**: REQUIRED.

| Surface ID | Feature Coverage | Technology Decision | Module/Repository | Notes |
|---|---|---|---|---|
| SURF-WEB-OPERATIONS | FULL | Vue/TypeScript SPA | `resources/js` | ConfiguraÃ§Ã£o e visualizaÃ§Ã£o de calendÃ¡rio |

## Constitution Check

| PrincÃ­pio | Status | Notas |
|---|---|---|
| Todoist como fonte nativa | PASS | Datas nativas sÃ£o escritas somente pela camada autorizada. |
| Core determinÃ­stico | PASS | CalendÃ¡rio e grupos ficam fora do controller/UI. |
| Integridade/sincronizaÃ§Ã£o | PASS | Recalcular antes de persistir impactos. |
| Qualidade/seguranÃ§a | PASS | Golden cases e relÃ³gio controlÃ¡vel. |
| SPA responsiva | PASS | Feedback temporal adaptado. |

## Project Structure

Expandir `app/Domain/Scheduling`, introduzir repositÃ³rio de configuraÃ§Ãµes/calendÃ¡rio em `app/Infrastructure` ou aplicaÃ§Ã£o, manter `calendar_exceptions`/`project_settings`, e adicionar suites golden em `tests/Unit`. A SPA recebe somente calendÃ¡rio/projeÃ§Ã£o autorizados.

## ConvenÃ§Ãµes de Borda

| Camada | Case style | ValidaÃ§Ã£o | Fonte da verdade |
|---|---|---|---|
| DB | snake_case | constraints | migrations |
| DomÃ­nio | value objects civis | golden tests | domÃ­nio |
| API/SPA | camelCase e `YYYY-MM-DD` | schemas | contracts |

**Mapper layer**: adaptador Todoist â†” modelo de planejamento; **ValidaÃ§Ã£o**: request de intenÃ§Ã£o e response calculada.
