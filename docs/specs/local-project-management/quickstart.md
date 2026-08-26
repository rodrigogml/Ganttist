# Cenários de Validação: Gerenciamento Local de Projetos

## Projeto local sem integração

1. Usuário autenticado abre a área inicial sem conexão externa.
2. Cria o projeto `Produto Aurora` somente com nome.
3. Reabre a lista de projetos.
4. **Expected**: o projeto aparece com zero tarefas, zero por cento de progresso e zero atrasadas; nenhum fluxo Todoist é exibido.

## Estrutura, pessoa e status

1. Proprietário cria seção `Entrega` e subseção `Interface`.
2. Cria uma tarefa na raiz e outra em `Interface`; cadastra pessoa sem conta e atribui a segunda tarefa.
3. Define fim planejado da segunda tarefa no passado e cria predecessora ainda não concluída.
4. **Expected**: a tarefa é atrasada, não bloqueada, pois atraso tem prioridade; a pessoa não recebe acesso.

## Convite e autorização

1. Proprietário envia convite de leitor para um e-mail.
2. Usuário destinatário tenta abrir o projeto antes do aceite.
3. Destinatário aceita o convite e abre novamente o projeto.
4. **Expected**: antes do aceite o acesso é recusado; depois, o projeto abre somente em leitura.

## Roundtrip end-to-end

1. A SPA envia criação de projeto com nome e chave de idempotência.
2. O backend retorna o resumo do projeto conforme `contracts/projects-api.md`.
3. A SPA valida o payload, atualiza a lista e abre o workspace pelo ID retornado.
4. **Expected**: nome, indicadores e papel do proprietário são apresentados; uma repetição da mesma chave não cria segundo projeto.
