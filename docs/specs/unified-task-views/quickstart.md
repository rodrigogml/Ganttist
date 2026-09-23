# Quickstart: Busca Unificada e Views de Tarefas

## Scenario 1: Construir e aplicar consulta pelo funil

1. Abrir um projeto com tarefas abertas, atrasadas, concluídas, atribuídas e sem responsável.
2. Abrir o funil e selecionar os status aberta e atrasada, depois responsável atual e sem responsável.
3. Observar a expressão inserida no campo de busca.
4. Alternar entre Tarefas e Gantt.
5. **Expected**: a expressão é única, editável, e ambos os modos mostram o mesmo conjunto de tarefas.

## Scenario 2: Diagnosticar query inválida e referência desconhecida

1. Aplicar uma expressão válida e registrar o conjunto exibido.
2. Tornar a expressão inválida, por exemplo deixando um parêntese aberto.
3. Substituí-la por uma expressão válida que mencione um responsável inexistente.
4. **Expected**: o erro estrutural mantém o último resultado válido; a referência desconhecida é aceita, produz feedback não bloqueante e pode retornar conjunto vazio.

## Scenario 3: Salvar e restaurar view completa

1. Configurar query, agrupamento, ordenação, colunas, hierarquia e zoom do Gantt.
2. Salvar uma view nomeada.
3. Alterar todos os controles sem salvar.
4. Aplicar a view salva em Tarefas e depois em Gantt.
5. **Expected**: cada controle armazenado é restaurado, inclusive o específico do modo não aberto na hora do salvamento; mudanças intermediárias não geram aviso nem escrita automática.

## Scenario 4: Importar com colisão e referência ausente

1. Exportar uma view que mencione uma pessoa do projeto de origem.
2. No projeto de destino, criar uma view de mesmo nome e importar o arquivo.
3. Escolher criar uma cópia; reaplicar a importação e escolher sobrescrever; em outra tentativa, cancelar.
4. **Expected**: cada escolha tem somente o efeito selecionado, a referência ausente permanece na query com advertência e cancelamento não altera views.

## Scenario 5: Roundtrip End-to-End

1. Iniciar o ambiente local autenticado e abrir um projeto acessível.
2. Criar uma view pela barra de comandos.
3. Consultar o endpoint de listagem de views com a mesma sessão e capturar a representação retornada.
4. Comparar nomes, tipos, `camelCase`, versão, query e estado visual com `contracts/views-api.md`.
5. Recarregar o workspace e aplicar a view retornada.
6. **Expected**: não há divergência entre a resposta real, o contrato e o estado consumido pela interface; a configuração é restaurada.

## Scenario 6: Human Interaction Roundtrip

1. Em Tarefas, usar `/` para focar a busca e inserir uma consulta com autocomplete pelo teclado.
2. Abrir o funil por teclado, inserir novo critério e salvar uma nova view.
3. Alternar para Gantt e aplicar a mesma view.
4. Exportar e importar a view em outro projeto usando os controles acessíveis.
5. **Expected**: foco, sugestão, confirmação, mensagem e resultado permanecem compreensíveis, e cada ação atravessa o contrato real até o estado observável esperado.
