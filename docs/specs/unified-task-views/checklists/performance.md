# Performance Checklist: Busca Unificada e Views de Tarefas

**Purpose**: Validar mensurabilidade e cobertura de degradação do requisito de desempenho da consulta.
**Created**: 2026-09-22
**Feature**: [plan.md](../plan.md)

## Metas e Degradação

- [x] CHK001 - A jornada crítica possui população, percentual e limite temporal mensuráveis? [Mensurabilidade, Spec SC-004; Plan §Technical Context] {auto}
- [x] CHK002 - A estratégia evita requisição por tecla e define preservação do último resultado durante atualização? [Cobertura, Plan §Architecture and Delivery Approach; Interface §INT-WEB-001 processing] {auto}
- [x] CHK003 - Estados offline e parcial-stale definem degradação compreensível sem bloquear a consulta local? [Edge Case, Interface §INT-WEB-001 offline/partial-stale; §INT-WEB-002 offline/partial-stale] {auto}
- [x] CHK004 - A validação inclui regressão em busca existente e cenário nominal de até 2.000 tarefas? [Traceability, Plan §Validation Strategy; Spec SC-004] {auto}

## Notes

- Itens `{auto}` foram resolvidos contra os artefatos citados.
- Não há necessidade de cache distribuído, paginação ou otimização de recurso pesado para o escopo definido; qualquer necessidade observada na medição vira tarefa técnica posterior.
