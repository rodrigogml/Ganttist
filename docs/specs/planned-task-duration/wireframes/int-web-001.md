# INT-WEB-001 — Editor de planejamento da tarefa

```text
┌─ DETALHES DA TAREFA ────────────────────────────────┐
│ Título                                                │
│ [ Preparar ambiente                                  ]│
│                                                       │
│ Responsável                                           │
│ [ Sem responsável                                  ▾ ]│
│                                                       │
│ Início planejado       Fim planejado                 │
│ [ 07/10/2026        ]  [ 09/10/2026                ] │
│                                                       │
│ Duração planejada                                      │
│ [ 3 ] dias úteis                                       │
│                                                       │
│ ┌─ PROJEÇÃO CALCULADA ─────────────────────────────┐ │
│ │ Status: Agendada                                  │ │
│ │ Início considerado: 07/10/2026                    │ │
│ │ Fim considerado:    09/10/2026                    │ │
│ └──────────────────────────────────────────────────┘ │
│                         Cancelar  Salvar alterações   │
└───────────────────────────────────────────────────────┘
```

- Início e fim compartilham uma linha; duração ocupa a linha seguinte por inteiro em qualquer largura.
- A duração usa campo numérico com unidade textual adjacente, nunca como placeholder.
- Erro de duração fica associado ao campo e preserva os demais valores; violação de prazo aparece na projeção calculada.
