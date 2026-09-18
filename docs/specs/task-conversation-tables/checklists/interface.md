# Interface Checklist: Tabelas na conversa da tarefa

**Purpose**: Validar cobertura, estados, responsividade, acessibilidade e rastreabilidade do contrato de interação.
**Created**: 2026-09-11
**Feature**: [spec.md](../spec.md)

## Cobertura e estados

- [x] CHK001 - A única superfície humana declarada possui cobertura FULL e interações inventariadas para cronologia, criação e edição? [Completude, Interface §Interface Coverage e §Interaction Inventory] {auto}
- [x] CHK002 - A modificação da conversa existente identifica componente, comportamento atual e ponto de entrada? [Rastreabilidade, Interface §Current-State Evidence e INT-WEB-001] {auto}
- [x] CHK003 - Cada interação define ator, permissão, ações, transições, validações e feedback sem introduzir permissão específica por tabela? [Consistência, Interface §INT-WEB-001–003; Spec FR-005–006] {auto}
- [x] CHK004 - Todos os estados canônicos, inclusive offline, access-denied e partial-stale, estão definidos ou justificados para cada interação? [Cobertura, Interface §INT-WEB-001–003 States] {auto}
- [x] CHK005 - A perda da reserva preserva o rascunho e distingue claramente a ação de publicar cópia da alteração da origem? [Clareza, Interface §INT-WEB-003; Spec FR-017–018] {auto}

## Responsividade e acessibilidade

- [x] CHK006 - Desktop, tablet e telefone têm regras explícitas para janela, rolagem, teclado virtual, toolbar e seleção? [Cobertura, Interface §INT-WEB-001–003 Responsive/Adaptive Behavior] {auto}
- [x] CHK007 - Tabs, foco de entrada/retorno, alertas, diálogos, teclado e leitores de tela são definidos para as ações críticas? [Acessibilidade, Interface §INT-WEB-001–003 Accessibility] {auto}
- [x] CHK008 - A especificação reconhece e encaminha a validação da acessibilidade da grade de terceiros, sem assumir capacidade não comprovada? [Risco, Interface §Cross-Surface Rules / Shared Accessibility and Input] {auto}
- [x] CHK009 - Conteúdo, datas, mensagens de conflito e telemetria evitam vazar token, documento ou identidade desnecessária de outro participante? [Segurança/Localização, Interface §INT-WEB-001–003 Localization and Telemetry] {auto}

## Contratos e rastreabilidade

- [x] CHK010 - Cada interação mapeia stories, requisitos, critérios de sucesso e contrato correspondente? [Rastreabilidade, Interface §Traceability] {auto}
- [x] CHK011 - Os wireframes obrigatórios existem e ilustram abas, nova tabela, reserva ocupada e recuperação, mantendo o texto como fonte de verdade? [Completude, Interface §Wireframes; wireframes/int-web-001.md; wireframes/int-web-003.md] {auto}
- [x] CHK012 - A ausência intencional de polling/coedição está declarada de forma consistente em interface, spec e plano? [Consistência, Spec FR-019; Plan §Technical Context; Interface §Cross-Surface Rules] {auto}

## Notes

- Itens `{auto}` foram resolvidos contra os artefatos citados.
- Não há itens `{humano}` ou gaps de interface abertos.
