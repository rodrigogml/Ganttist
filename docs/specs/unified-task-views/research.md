# Research: Busca Unificada e Views de Tarefas

## Decision 1: Avaliar a query no cliente sobre a projeção autorizada

**Decision**: Manter a avaliação interativa da consulta sobre as tarefas já entregues ao workspace autorizado; o servidor permanece a autoridade de acesso e de persistência das views.

**Rationale**: A busca precisa reagir durante a digitação, operar igualmente em Tarefas e Gantt e nunca ampliar o conjunto de dados que o usuário já pode consultar. A projeção atual já contém título, status calculado, responsável, seção, prioridade e datas necessários para os critérios do MVP.

**Alternatives considered**: Consultar o servidor a cada alteração de texto adicionaria latência, concorrência de requisições e estados de carregamento sem trazer dados adicionais; manter filtros paralelos violaria a fonte única de verdade.

## Decision 2: DSL textual canônica em pt-BR com valores estáveis

**Decision**: Preservar os operadores existentes e acrescentar predicados `campo:valor`; a interface gera campos canônicos em minúsculas sem acento (`status`, `responsavel`, `data`, `prioridade`, `secao`) e aceita aliases de leitura com acentos onde isso não cria ambiguidade.

**Rationale**: O texto é exportável, legível e editável. Valores especiais como `eu`, `sem` e `outros` são portáveis entre projetos; nomes livres continuam válidos mesmo quando uma referência não existe no destino.

**Alternatives considered**: Guardar apenas identificadores internos prejudicaria portabilidade; usar exclusivamente nomes sem assistência tornaria duplicidades e espaços frágeis; linguagem natural livre é adiada por não ser determinística.

## Decision 3: Views como recurso privado persistido e versionado

**Decision**: Persistir uma view por usuário e projeto com nome, query, snapshot visual estruturado e versão de formato; entregar as duas views iniciais como registros privados editáveis.

**Rationale**: Permite restaurar preferências de ambos os modos, evoluir o formato sem invalidar exportações e aplicar autorização de projeto no servidor.

**Alternatives considered**: Armazenar somente no navegador impede portabilidade e recuperação; modelos globais imutáveis contradizem a decisão de que o usuário pode alterar ou excluir as views iniciais.

## Decision 4: Importação tolerante, validação estrutural rigorosa

**Decision**: Validar versão, forma e limites do arquivo antes de importar, mas não rejeitar query sintaticamente válida por valor desconhecido no projeto de destino.

**Rationale**: Uma exportação deve circular entre projetos e usuários; referências por nome podem legitimamente não existir no destino. O usuário recebe aviso, não bloqueio.

**Alternatives considered**: Mapear pessoas e seções durante toda importação tornaria o fluxo lento e inviabilizaria a busca textual simples; descartar critérios alteraria silenciosamente a intenção original.

## Decision 5: Barra de comandos assistida, sem segundo estado

**Decision**: O funil, autocomplete, exemplos, histórico e explicação legível são clientes da mesma query. O funil lê a expressão atual, oferece critérios compatíveis e reescreve somente o trecho sob sua responsabilidade.

**Rationale**: Mantém a promessa de um único campo sem exigir que a pessoa memorize sintaxe, e evita divergência entre um painel de filtros e a busca exibida.

**Alternatives considered**: Chips não editáveis substituindo a expressão reduziriam transparência; um construtor visual separado manteria dois estados e exigiria regras de sincronização adicionais.
