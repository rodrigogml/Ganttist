# Requirements Checklist: Duração Planejada da Tarefa

**Purpose**: Validar completude, clareza, consistência e rastreabilidade das regras de duração, projeção e persistência.
**Created**: 2026-09-21
**Feature**: [spec.md](../spec.md)

## Domínio e integridade

- [x] CHK001 - A duração possui unidade, opcionalidade, intervalo e comportamento para ausência definidos de forma verificável? [Completude, Spec FR-001; Edge Cases] {auto}
- [x] CHK002 - Todas as combinações permitidas de início, fim e duração, incluindo fim + duração sem início, estão especificadas? [Cobertura, Spec FR-002–007; Data Model §Relações e compatibilidade] {auto}
- [x] CHK003 - A regra para identificar o campo causador e normalizar o trio evita interpretação múltipla do mesmo comando? [Clareza, Research Decision 2; Contracts §Normalização observável] {auto}
- [x] CHK004 - A compatibilidade de tarefas existentes sem duração explícita é definida sem backfill ou alteração implícita de datas? [Consistência, Spec FR-012; Research Decision 1; Data Model §Migração e integridade] {auto}
- [x] CHK005 - A distinção entre intenção persistida, duração resolvida e datas consideradas está definida em todas as camadas? [Consistência, Spec FR-006; Data Model §Valores derivados; Plan §Convenções de Borda] {auto}

## Projeção, dependências e estados

- [x] CHK006 - A âncora de fim calcula início retroativo em dias úteis sem gravar o valor projetado como data planejada? [Clareza, Spec FR-007; Research Decision 3] {auto}
- [x] CHK007 - A duração resolvida participa de FS, SS, FF, SF, grupos, folga e caminho crítico de forma rastreável? [Completude, Spec FR-008–009; Plan §Design e Sequência de Implementação] {auto}
- [x] CHK008 - O conflito entre prazo, duração e precedência tem resultado observável sem mudança silenciosa dos valores planejados? [Cobertura, Spec Edge Cases e FR-011; Research Decision 4; Contracts §Workspace] {auto}
- [x] CHK009 - A precedência de status e o efeito específico de duração isolada estão consistentes com a constituição e a implementação vigente? [Consistência, Spec FR-010; Constitution III] {auto}

## Fronteiras, segurança e validação

- [x] CHK010 - Criação, edição, duplicação e leitura de workspace declaram os campos, validações e erros necessários? [Completude, Spec FR-012; Contracts §Criar tarefa–Workspace] {auto}
- [x] CHK011 - O contrato mantém autorização por projeto e impede que o navegador forneça valores derivados como autoridade? [Segurança, Plan §Constitution Check e §Convenções de Borda; Contracts §Workspace] {auto}
- [x] CHK012 - Os critérios de sucesso e quickstart permitem verificar os fluxos principal, inválido, compatível, de relação e roundtrip? [Mensurabilidade, Spec SC-001–004; Quickstart §Scenario 1–5] {auto}

## Notes

- Todos os itens verificáveis estão resolvidos contra a spec, plano, modelo, contrato e quickstart.
- Não há gaps, ambiguidades ou decisões humanas pendentes para a criação do backlog.
