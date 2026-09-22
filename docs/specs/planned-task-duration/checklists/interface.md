# Interface Checklist: Duração Planejada da Tarefa

**Purpose**: Validar cobertura de interação, estados, adaptação e acessibilidade do editor e dos gestos temporais.
**Created**: 2026-09-21
**Feature**: [spec.md](../spec.md)

## Cobertura e estados

- [x] CHK001 - A única superfície humana aplicável possui cobertura FULL e cada interação alterada possui identificador, estado atual e entrada definida? [Completude, Interface §Interface Coverage, §Current-State Evidence e §Interaction Inventory] {auto}
- [x] CHK002 - O editor define conteúdo, unidade, ajuda, valores planejados, projeções e apresentação de violação sem confundir intenção e cálculo? [Clareza, Interface INT-WEB-001 Content and Data; Spec FR-006 e FR-011] {auto}
- [x] CHK003 - Salvar, cancelar, validação, falha remota, offline, acesso negado e snapshot desatualizado estão definidos com preservação do rascunho quando aplicável? [Cobertura, Interface INT-WEB-001 States] {auto}
- [x] CHK004 - Os gestos distinguem movimento, resize de início e resize de fim por intenção e oferecem resultado autorizado do servidor? [Consistência, Interface INT-WEB-002 Actions and Behavior; Contracts §Normalização observável] {auto}

## Responsividade e acessibilidade

- [x] CHK005 - A disposição desktop, tablet e telefone define reflow da grade e alternativa de negócio completa ao gesto de precisão? [Cobertura, Interface INT-WEB-001 e INT-WEB-002 Responsive/Adaptive Behavior] {auto}
- [x] CHK006 - O campo de duração possui rótulo, unidade, mínimo, máximo, incremento, ajuda e erro semanticamente associados? [Acessibilidade, Interface INT-WEB-001 Accessibility] {auto}
- [x] CHK007 - Teclado, leitores de tela, foco inicial/retorno, anúncio de erro e zoom estão definidos para o fluxo crítico? [Acessibilidade, Interface INT-WEB-001 e INT-WEB-002 Accessibility] {auto}
- [x] CHK008 - A violação de planejamento e os estados dos gestos possuem texto equivalente e não dependem só de cor ou ghost visual? [Acessibilidade, Interface INT-WEB-001 Validation and Feedback; INT-WEB-002 Accessibility] {auto}

## Padrões e rastreabilidade

- [x] CHK009 - A confirmação padrão usa o contêiner `v-default-form` e `DefaultSubmitButton`, sem introduzir comando primário concorrente? [Consistência, Interface INT-WEB-001 Actions and Behavior; AGENTS.md §Ação padrão de formulários] {auto}
- [x] CHK010 - Componentes, feedback e tokens reutilizam o drawer, timeblock, toast e padrões atuais sem criar um novo sistema visual? [Consistência, Interface INT-WEB-001–002 Components and Design System] {auto}
- [x] CHK011 - A telemetria contém apenas categoria e resultado, excluindo título, ID, datas e duração da tarefa? [Privacidade, Interface INT-WEB-001–002 Telemetry] {auto}
- [x] CHK012 - O wireframe obrigatório existe e a rastreabilidade liga cada interação às stories, requisitos, critérios e contrato? [Rastreabilidade, Interface §Traceability e §Wireframes; wireframes/int-web-001.md] {auto}

## Notes

- Todos os itens verificáveis estão resolvidos contra o contrato de interface.
- Não há gaps, ambiguidades ou decisões humanas pendentes para a criação do backlog.
