# INT-NAV-003 — Editor seguro de tarefa

## Desktop — painel flutuante

```text
┌──────────────────────────── workspace/Gantt ───────────────────────┬──────────────┐
│ conteúdo permanece visível e operável; não existe backdrop         │ [pin] [X]    │
│                                                                    │ Tarefa       │
│                                                                    │ campos       │
│                                                                    │ dependências │
│                                                                    │              │
│                                                                    │ Cancelar Salvar
└────────────────────────────────────────────────────────────────────┴──────────────┘
                                                                     sobreposto →
```

## Desktop — painel fixado e redimensionável

```text
┌────────────────────────────── barra superior ────────────────────────────────────┐
├──────────────────── workspace/Gantt redimensionado ───────────────┬┬──────────────┤
│                                                                  ││ [pin*] [X]  │
│ gráfico usa toda a área restante                                 ││ Tarefa       │
│                                                                  ││ campos       │
│                                                                  ││              │
│                                                                  ││ Cancelar Salvar
└──────────────────────────────────────────────────────────────────┴┴──────────────┘
                                                                   ↑ separador
                                                largura: 390 px … 50% da viewport
```

## Alterações não salvas

```text
┌──────────── Alterações não salvas ────────────┐
│ Salve ou descarte as alterações antes de sair.│
│                                               │
│ [Continuar editando] [Descartar] [Salvar]     │
└───────────────────────────────────────────────┘
```

O diálogo fica contido no editor. Clique externo e `Escape` não encerram o painel.
