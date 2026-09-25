# Requirements Checklist: Tarefas Recorrentes e Soneca

**Purpose**: Validar clareza, completude, consistência, mensurabilidade e rastreabilidade dos requisitos antes do backlog de implementação.
**Created**: 2026-09-24
**Feature**: [spec.md](../spec.md)

## Domínio e ciclo de ocorrência

- [x] CHK001 - A regra, cursor lógico, agendamento e conclusão são conceitos distintos e definidos sem fonte concorrente? [Completude, Spec §FR-001–002; Data Model §Tarefa recorrente] {auto}
- [x] CHK002 - A gramática V1 enumera frequência, listas, ordinal, dia útil, início e término em vez de exigir interpretação aberta? [Clareza, Spec §FR-003–006; Research §Decision 5] {auto}
- [x] CHK003 - Regras fixas e por intervalo possuem fórmulas de avanço verificáveis, unidades de dias úteis/calendário e bordas de fim de mês/29 de fevereiro sem conflito entre spec, plan e quickstart? [Consistência, Spec §FR-006–009; Plan §Validation Scenarios; Quickstart §Scenario 1–2, 7] {auto}
- [x] CHK004 - A política única de pular ocorrências perdidas é explícita e cobre conclusão atrasada e nova data futura? [Cobertura, Spec §FR-009; Spec §Edge Cases] {auto}
- [x] CHK005 - Início, término inclusivo, série contínua, remoção da regra e conclusão definitiva têm transições observáveis? [Completude, Spec §FR-012–013; Data Model §State Transitions] {auto}
- [x] CHK006 - A duplicação preserva independência da série e não copia histórico, sem contrariar o fluxo de tarefa existente? [Consistência, Spec §FR-026; Interface §INT-WEB-001] {auto}

## Soneca, planejamento e impacto

- [x] CHK007 - As cinco opções de soneca e seus significados em dias úteis estão definidos de maneira mensurável? [Clareza, Spec §FR-014–016] {auto}
- [x] CHK008 - A normalização de data manual não útil, preservação de duração e não alteração da regra são requisitos separados e testáveis? [Completude, Spec §FR-016–017; Plan §Validation Scenarios] {auto}
- [x] CHK009 - O preview declara dados mínimos, severidades, cancelamento sem escrita e revalidação antes da confirmação? [Completude, Spec §FR-018–019; Contracts §Preview e confirmação de soneca] {auto}
- [x] CHK010 - Cenários de impacto em sucessoras, criticidade, término finito e restrição estão cobertos sem definir caminho crítico como erro automático? [Consistência, Spec §Edge Cases; Interface §INT-WEB-002] {auto}

## Integridade, concorrência e indicadores

- [x] CHK011 - A conclusão repetida distingue replay idempotente de cursor obsoleto e define o resultado observável de ambos? [Clareza, Spec §FR-010–011; Contracts §Concluir] {auto}
- [x] CHK012 - Histórico tem atributos, unicidade, autor e snapshot de regra suficientes para impedir interpretação retroativa? [Completude, Data Model §Histórico de ocorrência; Research §Decision 3] {auto}
- [x] CHK013 - Uma recorrente como sucessora é permitida e toda predecessora recorrente direta ou por seção é proibida em todos os pontos de mutação relevantes? [Cobertura, Spec §FR-020–021; Plan §Design e Sequência de Implementação] {auto}
- [x] CHK014 - Projeção operacional e rede finita possuem limites claros para status, bloqueio, término, folga, caminho crítico e progresso? [Consistência, Spec §FR-022–023; Research §Decision 4] {auto}
- [x] CHK015 - O requisito de não gerar ocorrências por processo periódico é explícito e compatível com o ciclo dirigido por comando/consulta? [Premissa, Spec §Decisões de infraestrutura; Plan §Technical Context] {auto}

## Interface e acessibilidade

- [x] CHK016 - A cobertura FULL da superfície web possui inventário para editor, soneca/preview, conclusão/histórico e representação do workspace? [Completude, Interface §Interface Coverage; Interface §Interaction Inventory] {auto}
- [x] CHK017 - Cada interação define permissões, todos os estados canônicos, preservação de rascunho, erros remotos, offline e estado obsoleto? [Cobertura, Interface §INT-WEB-001–004] {auto}
- [x] CHK018 - Gantt e Tarefas têm paridade de regra, ocorrência, estado e indicação de soneca, com diferenças apenas de densidade responsiva documentadas? [Consistência, Spec §FR-023; Interface §Cross-Surface Rules] {auto}
- [x] CHK019 - Formulários novos usam uma única confirmação padrão e ações destrutivas/operacionais não usam esse atalho indevidamente? [Constituição de interface, Interface §INT-WEB-001–003; Plan §Design e Sequência de Implementação] {auto}
- [x] CHK020 - Teclado, toque, foco, anúncio dinâmico, contraste e textos alternativos estão especificados para todos os fluxos críticos? [Acessibilidade, Spec §FR-025; Interface §INT-WEB-001–004] {auto}
- [x] CHK021 - Wireframes obrigatórios existem e o texto do contrato permanece fonte de verdade para editor, preview e histórico? [Rastreabilidade, Interface §Wireframes; wireframes/int-web-001.md–int-web-003.md] {auto}

## Contratos, segurança e qualidade

- [x] CHK022 - Cada comando de recorrência, soneca, conclusão e histórico declara autenticação por projeto, validação, conflito e resposta observável? [Completude, Contracts §Interpretar ou salvar recorrência–§Concluir ocorrência e consultar histórico] {auto}
- [x] CHK023 - Os limites de paginação, chave de idempotência, cursor esperado, revisão de preview e proibição de confiança no navegador estão explícitos? [Segurança e consistência, Contracts §Preview e confirmação de soneca; Contracts §Concluir; Plan §Convenções de Borda] {auto}
- [x] CHK024 - Há critérios mensuráveis para equivalência de regra, dias úteis, idempotência, atraso, rede finita, acessibilidade e preview em 2.000 tarefas? [Mensurabilidade, Spec §SC-001–007] {auto}
- [x] CHK025 - A política V1 de exclusão em cascata de histórico após excluir tarefa/projeto está definida e preserva histórico ao remover recorrência ou concluir definitivamente? [Risco, Briefing §Decisões explícitas; Spec §FR-028; Data Model §Integridade e migração; Quickstart §Scenario 8] {humano, aprovado 2026-09-24}

## Notes

- Itens `{auto}` foram resolvidos por evidência citada nos artefatos SDD.
- CHK025 foi aprovado pelo dono do produto em 2026-09-24: a V1 usa exclusão em cascata do histórico quando tarefa ou projeto são excluídos, sem retenção independente.
