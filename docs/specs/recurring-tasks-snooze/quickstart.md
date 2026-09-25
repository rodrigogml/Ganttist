# Quickstart: Tarefas Recorrentes e Soneca

## Scenario 1: Regra fixa, soneca e conclusão

1. Criar uma tarefa de um dia útil e definir `toda segunda` pelo editor visual.
2. Confirmar que a expressão canônica e a data lógica da próxima segunda aparecem em Gantt e Tarefas.
3. Solicitar `Amanhã` para a ocorrência e confirmar o preview sem impacto crítico.
4. Concluir a ocorrência adiada.
5. **Expected**: o histórico registra segunda como data lógica e o agendamento adiado; a tarefa fica aberta na próxima segunda futura e preserva sua duração.

## Scenario 2: Intervalo, conclusão antecipada e atraso

1. Definir `a cada 4 dias` em uma tarefa.
2. Adiar a ocorrência para o próximo dia útil e concluí-la antes dessa data.
3. Repetir com uma conclusão após a data agendada e após uma data de intervalo perdida.
4. **Expected**: a primeira próxima ocorrência usa a data agendada como base; a segunda usa a conclusão efetiva; ambas ignoram ocorrências intermediárias vencidas.

## Scenario 3: Bloqueio permitido e predecessora proibida

1. Criar uma tarefa finita predecessora e uma recorrente sucessora com relação FS.
2. Consultar a recorrente antes e depois de concluir a predecessora.
3. Tentar criar a relação invertida e tentar tornar recorrente uma predecessora já existente.
4. **Expected**: a sucessora respeita bloqueio; as duas tentativas de predecessora recorrente são recusadas sem alterar a rede.

## Scenario 4: Preview crítico cancelado

1. Criar uma cadeia finita cujo término aumenta ao adiar uma tarefa.
2. Solicitar preview de `7 dias` para a ocorrência.
3. Cancelar a confirmação.
4. **Expected**: o preview relata impacto crítico; regra, cursor, planejamento, relações e indicadores persistidos permanecem inalterados.

## Scenario 5: Idempotência e conflito

1. Enviar duas vezes a mesma confirmação de conclusão, com o mesmo cursor e chave de comando.
2. Enviar uma nova confirmação usando o cursor antigo e outra chave.
3. **Expected**: a primeira repetição retorna o resultado original e cria um único histórico; a segunda é recusada como ocorrência desatualizada.

## Scenario 6: Roundtrip End-to-End

1. Autenticar como editor e criar uma recorrência por expressão em uma tarefa real.
2. Fazer um preview e uma confirmação reais de soneca; capturar a resposta de workspace e de confirmação.
3. Comparar nomes camelCase, tipos de regra/ocorrência, enumerações de impacto e datas com `contracts/recurrence-api.md`.
4. Abrir a tarefa real nas views Gantt e Tarefas e concluir a ocorrência com uma chave de comando real.
5. **Expected**: respostas, tipos consumidos pelo cliente e estado observável das duas views permanecem coerentes, sem fixture ou mock.

## Scenario 7: Intervalos de calendário e normalização útil

1. Definir `a cada 1 mês` a partir de 31 de janeiro e concluir a ocorrência elegível.
2. Definir uma regra anual em 29 de fevereiro e avançá-la para um ano não bissexto.
3. Usar uma data lógica semanal, mensal ou anual que recaia em dia não útil no calendário do projeto.
4. **Expected**: fevereiro usa seu último dia válido, 29 de fevereiro anual usa 28 de fevereiro em ano não bissexto e o agendamento efetivo é normalizado para dia útil sem alterar a data lógica.

## Scenario 8: Exclusão em cascata do histórico

1. Concluir uma ocorrência e confirmar que ela aparece no histórico autorizado da tarefa.
2. Excluir a tarefa ou o projeto que a contém.
3. **Expected**: nenhuma ocorrência do item removido permanece consultável; remover apenas a recorrência ou concluir definitivamente, em contraste, preserva o histórico existente.
