# RevisÃ£o de ClarificaÃ§Ã£o das Specs

**Data**: 2026-08-17
**MÃ©todo**: reconciliaÃ§Ã£o autÃ´noma das specs com `docs/especificacao_ganttist_v1.0.md`, o briefing e a constituiÃ§Ã£o. A solicitaÃ§Ã£o do usuÃ¡rio autorizou usar a fonte aprovada em vez de abrir entrevista complementar.

## Resultado

Nenhuma ambiguidade funcional crÃ­tica permaneceu sem uma resposta na especificaÃ§Ã£o do cliente. As oito specs permanecem em **Draft** porque ainda precisam de plano, interface, checklist e tarefas; nÃ£o porque dependam de decisÃ£o funcional desconhecida.

| Spec | Fonte principal reconciliada | Status | Tratamento |
|---|---|---|---|
| access-todoist | 18G, 24â€“27 | Clear | Passwordless, sessÃµes, OAuth, isolamento e desconexÃ£o estÃ£o definidos. PolÃ­ticas de infraestrutura seguem para o plano. |
| gantt-workspace | 3â€“4, 11â€“13, 22 | Clear | AssociaÃ§Ã£o projetoâ€“Gantt, hierarquia, grupos e superfÃ­cies principais estÃ£o definidos. |
| calendar-task-dates | 5, 9â€“10, 18Dâ€“18E | Clear | Datas civis, calendÃ¡rio, grupos, tarefas sem data, conclusÃ£o e timezone estÃ£o definidos. |
| dependencies-critical-path | 7â€“8, 18Bâ€“18C | Clear | Tipos, ciclos, grupos, criticidade, folga e situaÃ§Ãµes de borda estÃ£o definidos. |
| rescheduling-operations | 14â€“17, 19B, 20 | Clear | Drag/resize, simulaÃ§Ã£o, cascata, inserÃ§Ã£o/exclusÃ£o de rota e falhas parciais estÃ£o definidos. |
| todoist-synchronization | 6, 18F, 19A | Clear | Fontes da verdade, webhook, reconciliaÃ§Ã£o, conflitos, offline e SSE estÃ£o definidos. |
| gantt-navigation-experience | 18Hâ€“18I, 18Kâ€“18L, 21â€“23 | Clear | Responsividade, filtros, busca, feedback, acessibilidade e escala estÃ£o definidos. |
| audit-traceability | 18J, 18Mâ€“18O | Clear | Eventos, cadeia causal, consulta, retenÃ§Ã£o, operaÃ§Ã£o e observabilidade estÃ£o definidos. |

## PendÃªncias de governanÃ§a, nÃ£o de feature

As seguintes decisÃµes nÃ£o foram inventadas nem bloqueiam a especificaÃ§Ã£o funcional; permanecerÃ£o como parÃ¢metros explÃ­citos nos planos e tarefas:

- responsÃ¡veis, prazo, equipe e orÃ§amento;
- regime formal de compliance e valor definitivo de retenÃ§Ã£o;
- SLO/SLA, RPO/RTO, plataforma de monitoramento e capacidade de infraestrutura;
- metas quantitativas adicionais alÃ©m dos gates jÃ¡ definidos (2.000 tarefas nominal e 5.000 em stress).

## Diretrizes para as etapas seguintes

1. Cada plano tÃ©cnico deve citar os trechos fonte desta tabela e o achado correspondente da auditoria de cÃ³digo.
2. DecisÃµes de infraestrutura auditÃ¡veis devem ser explicitadas nos planos, nÃ£o deixadas como suposiÃ§Ã£o de implementaÃ§Ã£o.
3. As interfaces devem detalhar comportamento por desktop/tablet/celular, incluindo estados de sincronizaÃ§Ã£o, erro e operaÃ§Ã£o destrutiva.
4. Nenhuma implementaÃ§Ã£o comeÃ§a antes da anÃ¡lise transversal e da revisÃ£o do usuÃ¡rio.
