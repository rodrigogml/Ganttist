# Implementation Plan: Acesso e Conta Todoist

**Feature**: `access-todoist` | **Date**: 2026-08-17 | **Spec**: [spec.md](spec.md)

## Summary

Completar acesso passwordless, sessÃµes e OAuth preservando os fluxos existentes, mas adicionando lifecycle de sessÃµes, isolamento verificÃ¡vel, revogaÃ§Ã£o, rotaÃ§Ã£o de credenciais e contratos de recuperaÃ§Ã£o. A auditoria classifica a base como **corrigir**.

## Technical Context

**Language/Version**: PHP 8.4+, TypeScript 5.x.
**Primary Dependencies**: Laravel 12, Vue 3, Pinia.
**Storage**: MySQL 9.7; sessÃ£o server-side.
**Testing**: PHPUnit, Vitest e E2E planejado.
**Target Platform**: web HTTPS, PHP-FPM/Apache ou equivalente.
**Project Type**: monÃ³lito modular com SPA.
**Performance Goals**: acesso e status responsivos; rate limits definidos por rota.
**Constraints**: token externo criptografado; sem enumeraÃ§Ã£o de contas; uma conta Todoist por usuÃ¡rio.
**Scale/Scope**: todos os usuÃ¡rios isolados e mÃºltiplos dispositivos por usuÃ¡rio.

## Interaction Surface Architecture

**Surface Catalog**: [interaction-surfaces.md](../../architecture/interaction-surfaces.md)
**Interface Design Applicability**: REQUIRED.

| Surface ID | Feature Coverage | Technology Decision | Module/Repository | Notes |
|---|---|---|---|---|
| SURF-WEB-ACCESS | FULL | Vue/TypeScript SPA | `resources/js` | Login, sessÃ£o e conexÃ£o |
| SURF-EMAIL-ACCESS | FULL | E-mail transacional | `resources/views/emails` | Link e cÃ³digo de uso Ãºnico |

## Constitution Check

| PrincÃ­pio | Status | Notas |
|---|---|---|
| Todoist como fonte nativa | PASS | Credencial Ã© metadado prÃ³prio; tarefas nÃ£o sÃ£o replicadas. |
| Core determinÃ­stico | N/A | NÃ£o calcula scheduling. |
| Integridade antes de sincronizaÃ§Ã£o | PASS | OAuth state, desafio e revogaÃ§Ã£o devem ser idempotentes. |
| Qualidade e seguranÃ§a | PASS | Isolamento, hash, criptografia e rate-limit sÃ£o gates. |
| SPA responsiva e acessÃ­vel | PASS | Fluxos web e e-mail adaptados. |

## Project Structure

`app/Http/Controllers`, `app/Models`, `app/Mail`, `database/migrations`, `resources/js`, `resources/views/emails`, `tests/Feature` e `tests/E2E` concentram a mudanÃ§a. Criar documentaÃ§Ã£o complementar de modelo, contratos e quickstart neste diretÃ³rio; nÃ£o duplicar lÃ³gica OAuth fora do adapter/controlador autorizado.

## ConvenÃ§Ãµes de Borda

| Camada | Case style | ValidaÃ§Ã£o | Fonte da verdade |
|---|---|---|---|
| DB columns | snake_case | migrations/constraints | migrations |
| API payload | camelCase | request/response schema | contracts da feature |
| Frontend DTO | camelCase | schema no boundary | tipos/mapper da SPA |

**Mapper layer (DB <-> DTO)**: controllers e serviÃ§os de aplicaÃ§Ã£o; o domÃ­nio nÃ£o recebe payload bruto.
**ValidaÃ§Ã£o de schema**: request e response, com testes de contrato.
