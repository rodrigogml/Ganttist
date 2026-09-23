# Interface Checklist: Busca Unificada e Views de Tarefas

**Purpose**: Validar cobertura, estados, contratos e rastreabilidade da interface web responsiva.
**Created**: 2026-09-22
**Feature**: [interface-spec.md](../interface-spec.md)

## Cobertura e Estados

- [x] CHK001 - A única superfície FULL possui inventário de interações para consulta, gestão e portabilidade de views? [Completude, Interface §Interface Coverage; §Interaction Inventory] {auto}
- [x] CHK002 - Cada interação define permissões, entrada, dados, ações, validação, feedback e todos os estados canônicos? [Cobertura, Interface §INT-WEB-001–003] {auto}
- [x] CHK003 - As diferenças intencionais entre Tarefas e Gantt preservam a mesma query e restauram preferências não visíveis? [Consistência, Interface §Cross-Surface Rules; Spec FR-018–FR-019] {auto}

## Adaptação e Acessibilidade

- [x] CHK004 - Desktop, tablet e telefone têm regras de reflow, painel, teclado e toque sem exigir hover? [Cobertura, Interface §INT-WEB-001–003 Responsive/Adaptive Behavior] {auto}
- [x] CHK005 - Foco, leitor de tela, anúncios, Esc, contraste, zoom e alvos de toque foram definidos para os fluxos críticos? [Cobertura, Interface §INT-WEB-001–003 Accessibility] {auto}
- [x] CHK006 - Formulários de salvar e renomear identificam a ação padrão e a confirmação de sobrescrita sem conflitar com atalhos da query? [Consistência, Interface §INT-WEB-002 Components and Design System; AGENTS.md §Ação padrão de formulários] {auto}

## Design e Rastreabilidade

- [x] CHK007 - Componentes e tokens existentes são reutilizados e ações de salvar/excluir usam papéis semânticos de botão? [Constitution Alignment, Interface §INT-WEB-001–003 Components and Design System; AGENTS.md §Tokens de botões] {auto}
- [x] CHK008 - Interações com alteração estrutural possuem wireframe de baixa fidelidade existente e coerente com a fonte textual? [Completude, Interface §Wireframes; wireframes/int-web-001.md–int-web-003.md] {auto}
- [x] CHK009 - Cada interação mapeia histórias, FRs, critérios e contrato aplicável? [Traceability, Interface §Traceability] {auto}

## Notes

- Itens `{auto}` foram resolvidos contra os artefatos citados.
- Nenhum gap de contrato de interface permanece aberto.
