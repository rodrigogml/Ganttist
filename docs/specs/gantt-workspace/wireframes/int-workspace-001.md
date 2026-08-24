# INT-WORKSPACE-001 — Workspace

```text
Desktop: [Barra do sistema]
┌ Colunas ⚙ ─────────────────────────────────────────────────────┐
│ Tarefa         │ Resp. │ Status │ Início │ Prazo │ Coment. │ Gantt │
├────────────────┼───────┼────────┼────────┼───────┼─────────┼───────┤
│ Seção ▾        │       │        │        │       │         │ escala│
│   Grupo ⌄                │ [ resumo derivado ]                 │
│     ─⚑│Tarefa P1         │   [ barra ]                          │
│        │descrição Todoist│                                      │
│     ──│Tarefa P4         │                                      │
└──────────────────────────┴──────────────────────────────────────┘

Colunas ⚙: [✓ Tarefa (obrigatória)] [✓ Responsável] [✓ Status]
           [ ] Início [ ] Deadline [ ] Comentários
Resize Tarefa: mínimo = largura base; máximo = 25% da viewport.

Telefone: Barra → árvore OU timeline; painel abre em sobreposição.

Filtro por estado:
┌─────────────────────────┐
│ Todas                   │
│ Desbloqueadas           │
│   Abertas               │
│   Agendadas             │
│   Atrasadas             │
│ Bloqueadas              │
│ Concluídas              │
└─────────────────────────┘
```

- Grupo/seção: título em linha única, sem prioridade nem descrição.
- Folha: slot terminal fixo de 22 px antes do conteúdo; P1/P2/P3 substituem a extensão horizontal por bandeira ampliada, enquanto P4 mantém apenas a extensão. Título e descrição opcional começam no mesmo eixo em todas as folhas.
- Filtro: “Desbloqueadas” combina abertas, agendadas e atrasadas; as três opções individuais ficam identadas e “Bloqueadas”/“Concluídas” permanecem no primeiro nível.
