# Briefing do Projeto: Tabelas na conversa da tarefa

**Data**: 2026-09-10
**Status**: Validado

## Visão e Propósito

Permitir que a conversa de uma tarefa contenha, além de comentários em Markdown, blocos de tabela no estilo planilha. A tabela é uma forma estruturada de comunicar e organizar dados de planejamento sem transformar seu conteúdo em texto ou HTML perdido.

## Usuários e Stakeholders

| Ator | Papel | Ações principais |
|---|---|---|
| Proprietário do projeto | Participante com escrita | Criar e editar qualquer tabela do projeto. |
| Editor do projeto | Participante com escrita | Criar e editar qualquer tabela do projeto. |
| Leitor do projeto | Participante sem escrita | Visualizar comentários e tabelas. |

O bloco sempre exibe o nome da pessoa que o publicou e a data de publicação. Não haverá permissões próprias por tabela ou por tarefa nesta fase.

## Interfaces e Canais

| Interface/canal | Usuários | Plataforma | Cobertura | Conectividade | Paridade esperada |
|---|---|---|---|---|---|
| Janela web de conversa da tarefa | Proprietário, editor e leitor | Navegador em desktop, tablet e celular | MVP | Online | A leitura é disponível a todos; a edição respeita o papel global do projeto. |

O compositor passa a ter duas abas: **Novo Comentário**, que mantém o editor Markdown atual, e **Nova Tabela**, que abre uma planilha. A tabela publicada é exibida como bloco independente na cronologia da conversa.

## Escopo

### MVP

1. Criar e publicar uma tabela como novo bloco de conversa, preservando autor e data da publicação.
2. Persistir a tabela como documento JSON versionado, e não como Markdown, HTML ou imagem.
3. Oferecer planilha com até 200 linhas, 100 colunas e 500 KB de JSON por tabela.
4. Permitir inserir e remover linhas e colunas, mesclar e desmesclar células, negrito, cor de texto, cor de fundo, alinhamento, tamanho de linhas/colunas, copiar/colar e desfazer/refazer.
5. Suportar fórmulas básicas entre células, como operações aritméticas e `SUM`.
6. Permitir que proprietário ou editor altere qualquer tabela, sem controle de autoria específico por bloco.
7. Usar trava de edição no servidor: validade de 10 segundos, renovação pela interface a cada 2 a 3 segundos, liberação no salvar ou cancelar e sem cancelamento automático do rascunho local quando a validade expirar.
8. Quando a trava expirada ainda não tiver sido adquirida por outra pessoa, permitir que o mesmo usuário a renove e continue editando. Quando outra pessoa a adquirir, bloquear o rascunho local, preservar seu conteúdo e oferecer copiar conteúdo ou **Publicar como nova tabela**.
9. Atualizar dados somente ao recarregar ou reabrir a conversa; não haverá atualização automática para visualizadores.

### Pós-MVP

1. Comparar duas tabelas e auxiliar a mesclagem de dados publicados como cópias.
2. Coedição em tempo real, histórico/auditoria, atualização automática e notificações de alteração.
3. Múltiplas abas de planilha, importação/exportação Excel, gráficos e fórmulas avançadas.

### Fora de Escopo

- Permissões específicas por tabela ou tarefa.
- Auditoria de alterações de tabela.
- Coedição em tempo real.
- Atualização automática, polling ou SSE para a conversa.

## Prioridades e Trade-offs

**Ordem de prioridade**: flexibilidade de formatação e experiência familiar de planilha > controle fino de implementação > fórmulas avançadas.

**Decisões explícitas**:

- Usar Univer Sheets, carregado sob demanda, para reaproveitar uma interface completa de planilha.
- Aceitar uma dependência de interface maior em troca de formatação, células mescladas e fórmulas prontos.
- Preferir uma trava leve e renovável a coedição ou locking rígido: ela previne sobrescrita simultânea, mas uma ausência temporária pode resultar na perda do direito de salvar a tabela original.
- Preservar o rascunho que perdeu a trava e permitir publicá-lo como novo bloco, em vez de descartá-lo.

## Restrições

| Restrição | Valor | Notas |
|---|---|---|
| Técnica | Vue 3/TypeScript, Laravel e MySQL existentes | A tabela integra a janela de conversa atual. |
| Componente | Univer Sheets | Deve ser carregado apenas para editar ou visualizar blocos de tabela. |
| Persistência | JSON versionado, máximo 500 KB | Deve conservar estrutura, estilos, mesclagens e fórmulas. |
| Concorrência | Trava de 10 segundos | Renovação a cada 2 a 3 segundos; expiração torna a trava adquirível, mas não encerra o rascunho. |

## Stack Técnica

| Camada | Tecnologia | Justificativa |
|---|---|---|
| Backend | Laravel/PHP | Autorização pelos papéis existentes, persistência e API de trava. |
| Interface | Vue 3 e TypeScript | Stack existente da SPA. |
| Planilha | Univer Sheets | Base reutilizável para formatação, células mescladas e fórmulas. |
| Banco de dados | MySQL | Persistir bloco de conversa, documento da tabela e estado da trava. |

## Qualidade e Padrões

- Validar no servidor os limites de dimensão e tamanho do documento, a participação no projeto e a posse válida da trava antes de salvar uma alteração.
- Nunca avaliar fórmulas arbitrárias no servidor nesta fase; preservar a expressão e deixar o motor da planilha calculá-la no cliente.
- Testar aquisição, renovação, retomada e perda da trava, inclusive o caso de publicação como cópia.
- Manter a criação e edição bloqueadas para o papel global de leitor.

## Visão de Futuro

Em uma evolução posterior, tabelas divergentes poderão ser comparadas e mescladas. Coedição, auditoria, atualizações em tempo real, planilhas com múltiplas abas e recursos avançados de escritório serão avaliados apenas após validar o uso do MVP.

## Itens a Definir

Não há pendências críticas para iniciar a especificação da feature.

**Próximo passo recomendado**: `Dev Pipeline - 3. Specification - Specify` para detalhar requisitos, contratos, estados de trava e critérios de aceite.
