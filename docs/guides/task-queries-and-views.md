# Consultas e views de tarefas

Use o campo de busca para texto livre e critérios estruturados. Combine critérios com `&` (e), `|` (ou), `!` (não) e parênteses.

Exemplos: `status:aberta & responsavel:eu`, `data:proximos-7-dias & !status:concluida` e `responsavel:"Ana Silva"`.

Campos disponíveis: `status`, `responsavel`, `data`, `prioridade` e `secao`. Valores desconhecidos são aceitos: a consulta pode não retornar tarefas, mas continua válida e portátil.

O funil apenas escreve critérios nessa mesma expressão. Tarefas e Gantt usam o mesmo resultado.

Views são privadas por usuário e projeto. Salve uma view somente quando quiser persistir a configuração; mudanças posteriores não são salvas automaticamente. Uma view pode ser duplicada, renomeada, sobrescrita ou excluída.

Exportar gera um JSON portável sem IDs de tarefas, pessoas ou projeto. Ao importar uma view de mesmo nome, escolha Copiar, Sobrescrever ou Cancelar. Referências de texto que não existam no projeto de destino não impedem a importação.
