# Data Model: Duração Planejada da Tarefa

## Entity: Tarefa

| Campo lógico | Tipo | Restrições | Notas |
|---|---|---|---|
| duração planejada | inteiro opcional | `null` ou entre 1 e 3650 | Quantidade explícita de dias úteis; `null` preserva a interpretação legada. |
| início planejado | data civil opcional | quando combinado ao fim, não posterior ao fim | Intenção explícita; não recebe valor de projeção. |
| fim planejado | data civil opcional | quando combinado ao início, não anterior ao início | Intenção explícita; pode ancorar a projeção com duração. |
| campo causador da edição | enum de comando, não persistido | `start`, `finish` ou `duration` quando aplicável | Resolve qual dos três valores é preservado no comando. |

### Relações e compatibilidade

- A duração pertence à tarefa local e não cria nova entidade nem relação.
- Uma tarefa pode possuir duração sem qualquer data, duração + início, duração + fim ou os três parâmetros coerentes.
- Registros existentes mantêm duração explícita nula. O core conserva sua interpretação atual até que um comando de planejamento informe duração.
- Duplicação de tarefa copia a duração explícita tal como copia as demais intenções de planejamento.

### Valores derivados

| Valor | Regra |
|---|---|
| duração resolvida | duração explícita quando presente; caso contrário, duração legada inferida pelas datas; fallback de um dia conforme o core atual. |
| início considerado | início explícito; ou início retrocalculado quando há fim + duração; ou início virtual operacional/restrito. |
| fim considerado | fim explícito quando compatível; caso contrário, início considerado acrescido da duração resolvida, submetido à política de projeção. |
| violação de planejamento | verdadeira quando a projeção não consegue satisfazer simultaneamente duração explícita, fim planejado e restrições aplicáveis. |

### Migração e integridade

- A migration é somente aditiva e não altera nem preenche datas existentes.
- A coluna aceita `NULL`. A aplicação rejeita valores fora de 1 a 3650 em todos os ambientes; em MySQL, a migration adiciona também a `CHECK` constraint para a mesma faixa.
- O nome físico é `plannedDurationWorkdays`: a decisão segue a convenção de colunas camelCase do banco do projeto para novos atributos. O mapeamento API usa o mesmo nome.
- Não há índice: a duração não é filtro, ordenação ou junção de nenhuma consulta projetada; as consultas de prazo continuam cobertas pelo índice existente de `project_tasks`.
