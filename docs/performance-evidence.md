# Evidência automatizada de escala

## Cobertura local

O renderer usa `virtualWindow` para limitar a janela vertical. A suíte Vitest amostra todas as posições de scroll a cada 17 linhas nos cenários de 2.000 e 5.000 tarefas e verifica que:

- a faixa está sempre dentro dos limites do projeto;
- a janela nunca excede 37 linhas para viewport de 620 px, altura de linha de 49 px e overscan 12;
- o cálculo amostrado termina em menos de 250 ms no runner de CI.

O endpoint `/benchmark?size=2000` ou `/benchmark?size=5000`, quando a feature flag está habilitada, permite a medição de DOM e renderização no navegador sem usar dados externos.

## Limite da evidência

Isso prova a complexidade limitada da janela de virtualização e protege contra regressão algorítmica. Não prova fluidez de GPU, input de touch, memória do navegador ou percepção humana; esses continuam gates de hardware descritos em `docs/release-acceptance.md`.
