# Quickstart: Duração Planejada da Tarefa

## Scenario 1: duração sem datas

1. Crie uma tarefa sem início e fim planejados.
2. Informe duração planejada de `5` dias úteis e salve.
3. Reabra o editor e atualize o workspace.
4. **Expected**: a duração explícita continua `5`; início e fim planejados são nulos; a tarefa permanece aberta se não possuir bloqueio `FS`; a projeção usa duração cinco sem persistir data virtual.

## Scenario 2: fim ancorado e cálculo reverso

1. Configure segunda a sexta como dias úteis.
2. Crie uma tarefa com fim planejado em `2026-10-09` e duração de `3` dias úteis, sem início.
3. Atualize o workspace.
4. **Expected**: início planejado permanece nulo; início considerado é `2026-10-07`; fim considerado é `2026-10-09`.

## Scenario 3: edição coerente

1. Crie tarefa com início em `2026-10-05` e duração de `3` dias úteis.
2. Altere a duração para `5` dias úteis e salve.
3. Altere o fim para `2026-10-16` e salve.
4. **Expected**: após a primeira edição, o fim planejado é `2026-10-07`; após a segunda, a duração é recalculada segundo o calendário, sem intervalo inválido.

## Scenario 4: relação FF e violação visível

1. Crie predecessora com duração de cinco dias úteis e sucessora com fim planejado e duração de três dias úteis, sem início.
2. Crie relação `FF` que force o término da sucessora além do prazo informado.
3. Atualize o workspace.
4. **Expected**: as datas consideradas, folga e criticidade são recalculadas; as datas planejadas não são sobrescritas; a sucessora informa `schedule_constraint_state = violated` com motivo legível.

## Scenario 5: Roundtrip End-to-End

1. Suba o backend e a SPA usando o ambiente local do projeto.
2. Crie uma tarefa pela API com `plannedFinish`, `plannedDurationWorkdays` e `planningDriver: "duration"`, sem `plannedStart`.
3. Faça `GET /api/v1/projects/{projectId}/workspace` e capture a tarefa retornada.
4. Confirme que `plannedDurationWorkdays` é número, `resolved_duration_workdays` é número, `start` é `null`, `finish` preserva o valor enviado e as datas consideradas são strings `YYYY-MM-DD`.
5. Abra a mesma tarefa na SPA, altere a duração e salve.
6. **Expected**: o payload consumido pela SPA satisfaz o contrato, a projeção atualiza após reload e não há divergência de tipo ou case entre API, parser e interface.

## Evidências executadas

Em 2026-09-22, os cenários foram verificados pelos testes automatizados do repositório:

- `php artisan test`: 113 testes e 581 asserções aprovados, incluindo criação, edição, autorização, compatibilidade legada, dependências, grupos, status, criticidade e violação sem mutação silenciosa das intenções persistidas.
- `npx vue-tsc --noEmit` e `npm test`: contratos e tipos da SPA aprovados (23 arquivos e 76 testes).
- `npm run build`: build de produção aprovado.
- `npm run test:e2e`: 21 cenários aprovados, incluindo duração no drawer, reload autoritativo, Ctrl/Cmd+Enter, foco e erro, leitor/offline, ponteiro, touch, Escape, falha remota e reflow em desktop, tablet e telefone.
