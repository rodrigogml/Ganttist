# INT-WORKSPACE-002/003 — Gestos do timeblock

```text
READY / HOVER

      porta início                         porta fim
           □                                  □
           │  grip interno          grip interno │
           ▼      ▼                      ▼       ▼
linha ─── [◆│============================│◆] ────
             centro: mover   cursor mão
             bordas: resize  cursor ↔
             portas: conectar cursor mira

RESIZE

original oculto       [···· ghost aderente às colunas ····]
                      2026-08-18 → 2026-08-24

CONNECT

 [□│====== origem ======│■] ~~~~~ FS ~~~~~> [■│==== destino ====│□]
   endpoints do destino aparecem; inválidos ficam desabilitados
```

- Seção e resumo derivado: nenhum grip de resize; seção também não possui portas.
- Tarefa concluída: resize indisponível; portas continuam disponíveis quando a relação for válida.
- `Escape` cancela qualquer preview sem mutação.
- Touch: controles aparecem após seleção e usam hit areas ampliadas.
