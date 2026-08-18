# Requirements Checklist: Ganttist MVP

**Purpose**: validar clareza, cobertura, consistÃªncia e rastreabilidade dos requisitos transversais antes de criar o backlog.
**Created**: 2026-08-17
**Features**: [catÃ¡logo](../specs/README.md)

## Completude e Autoridade

- [x] CHK001 - As oito capacidades do MVP possuem fronteira funcional, histÃ³rias e requisitos rastreÃ¡veis? [Completude, CatÃ¡logo e Specs] {auto}
- [x] CHK002 - A autoridade de Todoist, banco prÃ³prio e core estÃ¡ definida sem conflito? [ConsistÃªncia, ConstituiÃ§Ã£o Iâ€“III] {auto}
- [x] CHK003 - Cada feature declara se o cÃ³digo atual deve ser aproveitado, corrigido, substituÃ­do ou criado? [Completude, Auditoria Â§Matriz] {auto}
- [x] CHK004 - OperaÃ§Ãµes distribuÃ­das possuem requisito explÃ­cito de idempotÃªncia, recuperaÃ§Ã£o e ausÃªncia de transaÃ§Ã£o aguardando HTTP externo? [Clareza, ConstituiÃ§Ã£o III; Plan operaÃ§Ãµes] {auto}
- [x] CHK005 - Datas civis, calendÃ¡rio, grupos, tarefas sem data e conclusÃ£o possuem regras separadas e testÃ¡veis? [Completude, Spec calendÃ¡rio; EspecificaÃ§Ã£o Â§5/18D/18E] {auto}

## Interfaces, UX e Acessibilidade

- [x] CHK006 - Cada superfÃ­cie FULL possui interaÃ§Ã£o identificada, estados canÃ´nicos, responsividade e rastreabilidade? [Cobertura, oito interface-specs] {auto}
- [x] CHK007 - AÃ§Ãµes por drag tÃªm alternativa para teclado/touch e validaÃ§Ã£o explÃ­cita? [Acessibilidade, Interface deps/ops/nav] {auto}
- [x] CHK008 - Estados de vazio, erro remoto, offline, acesso negado e dados parciais estÃ£o definidos onde aplicÃ¡veis? [Cobertura, interface-specs] {auto}
- [x] CHK009 - OperaÃ§Ãµes destrutivas e cascatas exigem preview/confirmaÃ§Ã£o e nÃ£o se confundem com sucesso local? [ConsistÃªncia, Interface operaÃ§Ãµes] {auto}
- [x] CHK010 - Itens filtrados/recolhidos preservam contexto de grupos e dependÃªncias? [Cobertura, Interface navegaÃ§Ã£o] {auto}

## SeguranÃ§a, IntegraÃ§Ã£o e OperaÃ§Ã£o

- [x] CHK011 - Acesso, isolamento, tokens, webhook e logs sensÃ­veis possuem regras verificÃ¡veis? [SeguranÃ§a, Spec acesso/sync; ConstituiÃ§Ã£o IV] {auto}
- [x] CHK012 - OAuth, webhook, retry, conflito e reconciliaÃ§Ã£o possuem comportamento de falha e recuperaÃ§Ã£o especificado? [IntegraÃ§Ã£o, Spec sync; Interface sync] {auto}
- [x] CHK013 - Auditoria distingue eventos funcionais, operaÃ§Ãµes compostas e logs tÃ©cnicos? [Clareza, Spec auditoria; Plan auditoria] {auto}
- [x] CHK014 - EstÃ£o definidos SLA/SLO, RPO/RTO, dono operacional e plataforma de alertas? [Completude, `docs/operations-baseline.md` Â§Responsabilidade/Â§Objetivos operacionais] {auto}
- [x] CHK015 - A polÃ­tica formal de compliance, retenÃ§Ã£o definitiva de auditoria e backups estÃ¡ aprovada? [Completude, `docs/operations-baseline.md` Â§Privacidade e retenÃ§Ã£o] {auto}

## Escala e Aceite

- [x] CHK016 - A meta nominal de 2.000 tarefas e stress de 5.000 Ã© rastreada como gate do renderer? [Mensurabilidade, ConstituiÃ§Ã£o V; Plan navegaÃ§Ã£o] {auto}
- [x] CHK017 - CritÃ©rios de aceite para determinismo, grafo, isolamento, sincronizaÃ§Ã£o e operaÃ§Ãµes destrutivas estÃ£o documentados? [Completude, EspecificaÃ§Ã£o Â§18N/32; ConstituiÃ§Ã£o IV] {auto}
- [x] CHK018 - As mÃ©tricas quantitativas de performance percebida e capacidade de infraestrutura estÃ£o fechadas alÃ©m dos gates de volume? [Completude, `docs/operations-baseline.md` Â§Gates de escala] {auto}

## Notes

- Itens `{auto}` foram resolvidos contra os artefatos citados.
- Os valores operacionais sÃ£o defaults aprovados para a implementaÃ§Ã£o; a aprovaÃ§Ã£o de infraestrutura e de release continua um gate externo registrado em `docs/pending.md`.
