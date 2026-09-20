# Quickstart — dependencias de secao

1. Crie a tarefa `Liberar` com fim em 2026-09-08 e a secao `Execucao` com uma tarefa interna em 2026-09-07.
2. Crie `Liberar (task) -> Execucao (section)` do tipo `FS`.
3. **Expected**: a tarefa interna passa a ter inicio considerado em 2026-09-09, sem gravar sua data planejada.
4. Adicione outra tarefa interna planejada para 2026-09-14.
5. **Expected**: ela permanece em 2026-09-14, pois ja satisfaz o desbloqueio da secao.
6. Tente relacionar uma secao com uma tarefa interna dela.
7. **Expected**: a API rejeita a relacao por hierarquia invalida.
