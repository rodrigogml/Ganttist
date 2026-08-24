# INT-WORKSPACE-005 — Configurações do projeto

```text
Barra superior                                      [⚙ Configurações]

                 ┌ Configurações do projeto ─────────────────────── [×] ┐
                 │ [ Calendário ] [ Automação ]                         │
                 ├──────────────────────────────────────────────────────┤
                 │ Defina os dias úteis e exceções...                   │
                 │                                                      │
                 │ Semana útil  [Seg] [Ter] [Qua] [Qui] [Sex] [Sáb]    │
                 │ Modo de reagendamento       [ Manual             ▾ ] │
                 │ Projeção após desbloqueio   [ Preservar duração  ▾ ] │
                 │ Deadline em dia bloqueado   [ Dia útil anterior  ▾ ] │
                 │ [✓] Permitir tarefas sem data                       │
                 │ Exceções                              [ Adicionar ]  │
                 │                                                      │
                 ├────────────────────────────── [Cancelar] [Simular] ──┤
                 └──────────────────────────────────────────────────────┘

Automação:

                 ┌ Configurações do projeto ─────────────────────── [×] ┐
                 │ [ Calendário ] [ Automação ]                         │
                 ├──────────────────────────────────────────────────────┤
                 │ [ ] Definir automaticamente o início de tarefas  [?] │
                 │     bloqueadas na data prevista de desbloqueio       │
                 │ [ ] Manter sem datas no Todoist as tarefas que   [?] │
                 │     possuem subtarefas                               │
                 ├────────────────────────────── [Cancelar] [Salvar] ────┤
                 └──────────────────────────────────────────────────────┘

Ajuda `?`: cada linha abre sua explicação. A primeira exemplifica a relação FS
e a preservação do deadline. A segunda esclarece que data e deadline do pai são
removidos do Todoist, enquanto seu intervalo continua derivado das filhas no Gantt.
O backdrop não executa fechamento.

Tentativa de fechar/trocar uma aba alterada:

                    ┌ Alterações não salvas ───────────────────┐
                    │ Deseja descartar as alterações desta aba? │
                    │ [Descartar alterações]          [Voltar]  │
                    └────────────────────────────────────────────┘

- Voltar/Escape: mantém aba, rascunho e diálogo.
- Descartar: restaura o baseline e executa a troca ou fechamento pendente.
- Salvar/Simular pertencem à aba; salvar não fecha o diálogo.
```
