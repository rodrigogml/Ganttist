# Implementation Plan: NavegaÃ§Ã£o e ExperiÃªncia Gantt

**Feature**: `gantt-navigation-experience` | **Date**: 2026-08-17 | **Spec**: [spec.md](spec.md)

## Summary

Converter o vertical slice visual em renderer prÃ³prio escalÃ¡vel: janela temporal/virtualizaÃ§Ã£o, Ã¡rvore e SVG sincronizados, busca/filtros/contexto oculto e interaÃ§Ãµes acessÃ­veis. Reaproveitar tokens e componentes simples; nÃ£o o intervalo temporal fixo.

## Technical Context

Vue 3/TypeScript/Tailwind; API JSON+SSE; Vitest, componentes, E2E e benchmark; navegadores modernos desktop/tablet/celular; nominal 2.000 e stress 5.000 tarefas; Gantt prÃ³prio, sem biblioteca estrutural.

## Interaction Surface Architecture

**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)
**Interface Design Applicability**: REQUIRED.

| Surface ID | Feature Coverage | Technology Decision | Module/Repository | Notes |
|---|---|---|---|---|
| SURF-WEB-OPERATIONS | FULL | Vue/TypeScript | `resources/js`, `resources/css` | Desktop e touch adaptados |

## Constitution Check

| PrincÃ­pio | Status | Notas |
|---|---|---|
| Iâ€“IV | PASS | Consome projeÃ§Ã£o, sem regra duplicada. |
| V | PASS | Spike 2k/5k Ã© gate antes do renderer final. |

## Project Structure

Extrair `App.vue` em mÃ³dulos de Ã¡rvore, timeline, relaÃ§Ãµes, filtros, seleÃ§Ã£o e acessibilidade; manter store como boundary de dados; criar cenÃ¡rio de benchmark e E2E; nÃ£o espalhar cÃ¡lculo de datas pela UI.

## ConvenÃ§Ãµes de Borda

API/SPA em camelCase, schema no boundary; preferÃªncias visuais locais separadas de DTO de negÃ³cio; resposta de workspace Ã© a fonte de verdade de visualizaÃ§Ã£o.
