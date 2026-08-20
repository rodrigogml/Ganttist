# INT-NAV-001 — Árvore e timeline Gantt

```text
Desktop amplo — cartão ocupa a largura e a altura disponíveis

[Buscar] [Filtros] [Dia | Semana | Mês] [Hoje]
┌──────────────────────────────┬─────────────────────────────────────────────┐
│ TAREFA / RESP. / STATUS      │ AGO 2026                    SET 2026         │
├──────────────────────────────┼─────────────────────────────────────────────┤
│ ▾ Seção                      │ ━━━━━━━━━ grupo ━━━━━━━━━                   │
│ │ ☐ Tarefa A                 │       ███████                               │
│ ▸ Subárvore recolhida        │                                             │
│ ▌ ☑ Tarefa selecionada       │                 █████████                   │
│   ☐ Tarefa sob hover         │──────────────── régua hover ────────────────│
│     área vertical fluida e virtualizada; scroll único e sincronizado      │
├──────────────────────────────┴─────────────────────────────────────────────┤
│ 2 selecionadas                              [Editar] [Simular] [Limpar]    │
├────────────────────────────────────────────────────────────────────────────┤
│ ● Em execução  ● Crítica  ● Concluída  ● Sem data       Fuso: São Paulo   │
└────────────────────────────────────────────────────────────────────────────┘

▌ = cursor de teclado; a barra contextual existe somente com seleção.

Telefone: [Árvore | Timeline] [Buscar] [Filtros], com a barra contextual acima
da legenda e sem alterar a altura externa do cartão.
```
