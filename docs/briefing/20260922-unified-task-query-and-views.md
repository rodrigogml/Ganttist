# Project Briefing: Busca Unificada e Views de Tarefas

**Data**: 2026-09-22
**Status**: Aprovado
**Versão**: 1.0

---

## 1. Visão e Propósito

**O que é**: Evolução da barra de comandos das visualizações Tarefas e Gantt para reunir busca textual e filtros em uma única expressão de consulta, acompanhada de views salvas por usuário e projeto.

**Problema que resolve**: Hoje busca e filtros são estados separados. O usuário precisa administrar interfaces distintas para restringir a mesma lista e não pode preservar, reutilizar ou transportar suas configurações de trabalho.

**Proposta de valor**: Uma única consulta, editável tanto por texto quanto pelo construtor visual do funil, permite encontrar rapidamente conjuntos úteis de tarefas e recuperá-los como views completas em Tarefas ou Gantt.

## 2. Usuários e Stakeholders

| Ator | Papel | Ações Principais |
|---|---|---|
| Usuário do projeto | Planeja e acompanha o trabalho | Busca tarefas, constrói filtros, aplica, cria, altera, duplica, remove, exporta e importa views próprias |
| Usuário destinatário de exportação | Reutiliza uma configuração recebida | Importa JSON, escolhe como resolver colisão de nome e aplica a view |

**Stakeholders de decisão**: Usuário solicitante do produto.

## 3. Interfaces e Canais

| Interface/Canal | Usuários | Dispositivos/Plataformas | Cobertura | Conectividade | Paridade Esperada |
|---|---|---|---|---|---|
| Barra de comandos nas views Tarefas e Gantt | Usuários do projeto | Web responsiva | MVP | Online; preservar o comportamento atual de leitura offline quando aplicável [inferido] | Mesma query e mesma view aplicadas nas duas visualizações |
| Popover do funil e gestão de views | Usuários do projeto | Web responsiva | MVP | Online | Constrói a mesma expressão exibida no campo de busca |

**Restrições tecnológicas já obrigatórias**: Manter a barra de busca acionada por `/`; preservar os operadores existentes `&`, `|`, `!`, parênteses, `*` e escape `\\`; a implementação deve seguir a stack web existente [inferido].

## 4. Escopo

### MVP (Essencial)

1. Corrigir o caractere `>` residual no cabeçalho do popover de filtros.
2. Unificar busca e filtros em uma linguagem de consulta única, com booleanos existentes e predicados no formato `campo:valor`.
3. Fazer o funil compor e inserir a expressão no campo de busca, sem manter filtros paralelos.
4. Suportar, além de texto, status, responsável, período/data relativa, prioridade, seção, concluída/não concluída e bloqueada/desbloqueada.
5. Oferecer datas relativas `hoje`, `amanhã` e `proximos-7-dias`.
6. Criar views privadas por usuário e projeto, que capturem query e todas as preferências visuais comuns ou específicas de Tarefas e Gantt: agrupamento, subagrupamento, ordenação, subordenação, zoom, colunas visíveis e estado de hierarquia.
7. Aplicar a configuração completa da view nos dois modos, ainda que certas preferências não estejam visíveis no modo atualmente aberto.
8. Entregar duas views iniciais editáveis: **Minhas Tarefas** (abertas ou atrasadas, atribuídas ao usuário atual ou sem responsável) e **Tarefas Equipe** (abertas ou atrasadas, atribuídas a qualquer outra pessoa ou sem responsável).
9. Permitir criar, editar, sobrescrever mediante confirmação, duplicar e excluir views; não avisar alterações não salvas nem salvar automaticamente.
10. Exportar uma view como JSON e importá-la em outro projeto ou por outro usuário. Em colisão de nome, oferecer sobrescrever, duplicar ou cancelar.
11. Aceitar a importação mesmo quando uma referência textual não existir no projeto de destino; a consulta pode resultar vazia.
12. Oferecer autocomplete contextual, ajuda com exemplos inseríveis, validação não bloqueante de valores desconhecidos, histórico de consultas, contagem de resultados e explicação legível dos critérios ativos.

### Pós-MVP (Desejável)

1. Incluir dependências e caminho crítico na linguagem de consulta.
2. Assistente em linguagem natural que converta intenção em query, após a linguagem determinística estar consolidada [inferido].
3. Favoritar, reordenar e categorizar views [inferido].

### Fora de Escopo

- Compartilhamento ou edição colaborativa de views dentro do projeto.
- Salvamento automático de alterações em uma view aplicada.
- Bloquear importação por responsáveis, seções ou outros valores inexistentes no destino.

## 5. Prioridades e Trade-offs

**Ordem de prioridade**: Consistência entre Tarefas e Gantt > descoberta e facilidade de uso > poder expressivo > expansão de escopo.

**Decisões explícitas**:

- O campo de busca continua sendo a fonte única de verdade; o funil é apenas um construtor visual da query.
- A sintaxe atual deve permanecer compatível.
- Views são snapshots completos e privados, não configurações compartilhadas.
- Alterar controles após aplicar uma view não cria vínculo de edição, aviso de alterações ou persistência automática.
- Sobrescrever uma view exige confirmação explícita para evitar perda acidental.
- Expressões exportadas são portáveis como texto; referências ausentes não invalidam a importação.

## 6. Restrições

| Restrição | Valor | Notas |
|---|---|---|
| Prazo | Não definido | Não informado |
| Equipe | Não definida | Não informada |
| Budget | Não definido | Não informado |
| Técnica | Compatibilidade com a busca existente | Não alterar o atalho `/` nem os operadores já suportados |
| Produto | Privacidade por usuário e projeto | Não há compartilhamento automático de views |

## 7. Stack Técnica

| Camada | Tecnologia | Justificativa |
|---|---|---|
| Backend | Laravel 12 / PHP | Stack existente [inferido] |
| Interface web | Vue 3, TypeScript e Pinia | Barra de comandos e estado de workspace existentes [inferido] |
| Banco de dados | MySQL | Persistência de views privadas por projeto e usuário [inferido] |

## 8. Qualidade e Padrões

**Padrões adotados**:

- Consultas inválidas mantêm a última expressão válida aplicada, com diagnóstico no campo.
- Valores válidos sintaticamente, mas desconhecidos no projeto, não bloqueiam a query nem a importação; a interface explica que podem resultar em zero tarefas.
- Autocomplete é contextual ao cursor e aos valores existentes no projeto.
- Exemplos, ajuda e seleção no funil devem produzir a mesma sintaxe canônica, evitando dois comportamentos de filtragem.
- A importação deve validar estrutura e versão do JSON antes de oferecer a resolução de conflito de nome [inferido].

**Compliance**: Nenhum específico informado.

## 9. Visão de Futuro

**6 meses**: Views se tornam o modo rápido de alternar entre recortes operacionais do projeto, com query, agrupamento, ordenação e aparência consistentes em Tarefas e Gantt.

**12 meses**: A linguagem pode incorporar dependências, caminho crítico e assistência por linguagem natural sem abandonar a expressão textual portátil [inferido].

**Riscos conhecidos**:

- Uma gramática poderosa pode se tornar difícil de descobrir sem autocomplete, ajuda e feedback claros.
- Identificadores textuais importados podem não existir ou ser ambíguos no projeto de destino; a tolerância à importação precisa ser comunicada claramente.
- O snapshot visual entre duas visualizações pode incluir preferências incompatíveis ou ainda inexistentes, exigindo versionamento da definição de view [inferido].

---

## Itens a Definir

| Item | Dimensão | Impacto |
|---|---|---|
| Vocabulário canônico final dos campos e valores da DSL, incluindo aliases em português | Produto / Interface | Alto |
| Regras exatas de resolução para nomes duplicados de responsáveis ou seções no autocomplete | Interface | Médio |
| Limites de quantidade e tamanho de views e do arquivo JSON de importação | Qualidade / Técnica | Médio |
| Ordem, disponibilidade e semântica dos novos controles de agrupamento e ordenação | Interface | Alto |

---

**Próximo passo recomendado**: `Dev Pipeline - 3. Specification - Specify` para transformar o briefing em especificação funcional detalhada.
