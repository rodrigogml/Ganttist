# Matemática do planejamento

Todas as operações usam dias civis úteis. O início conta como o primeiro dia:

`F(T) = AddWorkDays(S(T), D(T) - 1)`

- FS: `S(B) >= NextWorkDay(F(A))`
- SS: `S(B) >= S(A)`
- FF: `F(B) >= F(A)` e o início é recalculado preservando `D(B)`
- SF: `F(B) >= S(A)` e o início é recalculado preservando `D(B)`

Múltiplas predecessoras aplicam a restrição mais tardia. O passe ocorre em ordem topológica; ciclo interrompe toda a operação. Tarefa concluída não é movida e usa `completion_date_override ?? Todoist.completed_at` como realidade conhecida.

`OperationalToday = IsWorkDay(LocalToday) ? LocalToday : NextWorkDay(LocalToday)`

Tarefa sem data usa início virtual para cálculo, mantendo sua semântica visual não programada e sem persistência automática no modo manual.

Criticidade usa forward/backward pass e `TotalFloat = LS - ES` em dias úteis. `TotalFloat = 0` indica tarefa crítica. Grupos não são nós: `S(grupo)=min(S descendentes)` e `F(grupo)=max(F descendentes)`.
