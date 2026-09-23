# Requirements Checklist: Busca Unificada e Views de Tarefas

**Purpose**: Validar completude, clareza, consistência e mensurabilidade da especificação funcional.
**Created**: 2026-09-22
**Feature**: [spec.md](../spec.md)

## Cobertura e Consistência

- [x] CHK001 - Todos os comportamentos de busca, construtor, views, portabilidade e assistência estão definidos com requisitos verificáveis? [Completude, Spec §Functional Requirements FR-001–FR-028] {auto}
- [x] CHK002 - A fonte única da query e a proibição de filtros paralelos são consistentes entre escopo, stories e requisitos? [Consistência, Spec §US-001/US-002; FR-002; FR-012] {auto}
- [x] CHK003 - As decisões de privacidade por usuário/projeto, ausência de autosave e confirmação de sobrescrita estão explícitas e sem conflito? [Consistência, Spec §US-003; FR-017–FR-022] {auto}
- [x] CHK004 - O escopo futuro de dependências, caminho crítico, compartilhamento e assistente de linguagem natural está explicitamente excluído ou adiado? [Completude, Spec §Interface Coverage; Plan §DSL Canonical] {auto}

## Critérios e Casos de Contorno

- [x] CHK005 - As consultas inválidas, referências desconhecidas, datas relativas, duplicidade de nomes, estado vazio e cancelamento de importação possuem resultado esperado? [Cobertura, Spec §Edge Cases; US-001/US-005/US-006] {auto}
- [x] CHK006 - Os critérios de sucesso são objetivos, centrados na pessoa usuária e possuem limiares quando exigem desempenho? [Mensurabilidade, Spec §Success Criteria SC-001–SC-007] {auto}
- [x] CHK007 - A feature respeita fonte de verdade local, acesso explícito e integridade de status sem criar regra de domínio duplicada? [Constitution Alignment, Plan §Constitution Check] {auto}

## Rastreabilidade

- [x] CHK008 - Cada jornada independente é ligada a requisitos, critérios de sucesso e interação humana correspondente? [Traceability, Spec §User Scenarios; Interface §Traceability] {auto}

## Notes

- Itens `{auto}` foram resolvidos contra os artefatos citados.
- Nenhum gap funcional permanece aberto neste domínio.
