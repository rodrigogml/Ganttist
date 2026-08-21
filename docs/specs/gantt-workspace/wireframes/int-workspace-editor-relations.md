# INT-WORKSPACE-004 — Relações no editor

```text
┌─ RELAÇÕES ─────────────────────────────┐
│ Depende de                             │
│ ┌────────────────────────────────────┐ │
│ │ [FS] Nome completo da predecess... 🗑│ │
│ └────────────────────────────────────┘ │
│                                       │
│ Dependentes                            │
│ ┌────────────────────────────────────┐ │
│ │ [SS] Tarefa sucessora com título... 🗑│ │
│ └────────────────────────────────────┘ │
│                                       │
│ [Outra tarefa…] [FS]       [Adicionar]│
└───────────────────────────────────────┘
```

- Badge e lixeira nunca encolhem; o título recebe ellipsis.
- Hover/foco no título expõe o nome completo.
- Quadros vazios mantêm seus títulos e exibem mensagem contextual.
