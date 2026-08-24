# INT-WORKSPACE-004 — Relações no editor

```text
┌─ RELAÇÕES ─────────────────────────────┐
│ Depende de                       1  [+]│
│ ┌────────────────────────────────────┐ │
│ │ [FS] Nome completo da predecess... 🗑│ │
│ └────────────────────────────────────┘ │
│                                       │
│ Dependentes                      1  [+]│
│ ┌────────────────────────────────────┐ │
│ │ [SS] Tarefa sucessora com título... 🗑│ │
│ └────────────────────────────────────┘ │
└───────────────────────────────────────┘

                 ┌─ NOVA PREDECESSORA ───────────────────┐
                 │  [ bloco escolhido ] ───▶ [ atual ]   │
                 │  FS — término → início                │
                 │                                       │
                 │  Buscar tarefa                        │
                 │  [ trecho do título…                ] │
                 │  ┌ resultados por correspondência ┐  │
                 │  └─────────────────────────────────┘  │
                 │  Tipo: [FS] [SS] [FF] [SF]            │
                 │                      Cancelar  Criar   │
                 └───────────────────────────────────────┘
```

- Badge e lixeira nunca encolhem; o título recebe ellipsis.
- Hover/foco no título expõe o nome completo.
- Quadros vazios mantêm seus títulos e exibem mensagem contextual.
- Cada `+` abre o modal já configurado como predecessora ou dependente.
- A prévia aparece após tarefa e tipo serem escolhidos e possui descrição textual equivalente.
