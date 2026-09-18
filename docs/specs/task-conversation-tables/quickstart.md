# Quickstart: Tabelas na conversa da tarefa

## Scenario 1: Publicar e consultar uma tabela

1. Entrar como editor e abrir a conversa de uma tarefa.
2. Selecionar “Nova Tabela”, preencher dados, aplicar negrito e fundo a uma célula, mesclar duas células e inserir `=SUM(A1:A2)`.
3. Publicar a tabela.
4. Reabrir a conversa como leitor.
5. **Expected**: há um bloco de tabela com o nome/data do publicador, o conteúdo, estilo, mesclagem e resultado da fórmula; o leitor não pode editá-lo.

## Scenario 2: Exclusividade e renovação tardia

1. Entrar como editor A e iniciar a edição de uma tabela publicada.
2. Tentar editar a mesma tabela como editor B antes de 10 segundos.
3. Esperar mais de 10 segundos sem renovação de A, sem B adquirir a reserva.
4. Renovar como A e salvar.
5. **Expected**: B é bloqueado no passo 2; A recupera a reserva no passo 4 e seu salvamento atualiza a tabela original.

## Scenario 3: Perder reserva e publicar cópia

1. Editor A inicia uma edição e deixa a reserva vencer mantendo seu rascunho aberto.
2. Editor B adquire a reserva e inicia a edição.
3. Editor A tenta renovar ou salvar.
4. Editor A seleciona “Publicar como nova tabela”.
5. **Expected**: A recebe explicação de perda, seu rascunho permanece disponível sem edição e gera novo bloco; a tabela original não é alterada por A.

## Scenario 4: Limites e autorização

1. Tentar publicar documento acima de 200 linhas, 100 colunas ou 500 KB.
2. Abrir a mesma tarefa como leitor e tentar publicar ou editar uma tabela.
3. **Expected**: o primeiro caso informa o limite e preserva o rascunho; o segundo não oferece ação de escrita e uma chamada direta é recusada.

## Scenario 5: Roundtrip End-to-End

1. Iniciar backend e SPA locais com banco migrado e dois usuários de teste, um editor e um leitor.
2. Publicar uma tabela real pela SPA e capturar a resposta de contexto da tarefa.
3. Comparar o bloco retornado com `contracts/tables-api.md`: nomes `snake_case`, documento, versão, autor, data e `editable`.
4. Adquirir, renovar e liberar reserva por chamadas reais; repetir a aquisição concorrente com dois usuários.
5. Atualizar pela SPA usando o token retornado e recarregar a conversa.
6. **Expected**: o payload, o contrato, os tipos da SPA e o estado visível coincidem; somente uma reserva é concedida e a atualização preserva o documento publicado.
