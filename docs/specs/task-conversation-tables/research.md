# Research: Tabelas na conversa da tarefa

## Decision 1: Motor de planilha

**Decision**: Usar Univer Sheets em modo de presets/plugins mínimos, com carregamento assíncrono do editor e visualizador de tabela.

**Rationale**: O núcleo aberto do Univer inclui planilhas, motor de fórmulas, interface, temas e APIs de documento. A composição por plugins permite incluir somente a superfície necessária e não carregar a planilha quando a conversa contém apenas texto. A escolha atende formatação, mesclagem e fórmulas sem implementar um grid próprio.

**Alternatives considered**:

- Handsontable com HyperFormula: integração Vue madura, mas exige licença comercial para uso de produto e a formatação rica precisaria de toolbar própria.
- Jspreadsheet CE: leve e MIT, mas os recursos modernos de fórmulas e formatação são separados em extensões/edições comerciais.
- Luckysheet: descartado por estar encerrado pelo próprio mantenedor em favor do Univer.

**Sources**: [Univer](https://github.com/dream-num/univer), [presets](https://github.com/dream-num/univer-presets), [Luckysheet EOL](https://github.com/dream-num/Luckysheet).

## Decision 2: Documento de tabela e cálculo

**Decision**: Armazenar um único documento de planilha por bloco de tabela, com valores, fórmulas, estilo, dimensões e mesclagens; calcular fórmulas somente no navegador.

**Rationale**: O documento estruturado mantém fidelidade para nova edição e não introduz HTML não confiável nem execução de fórmula no servidor. Uma única aba cobre o MVP e reduz limites, validação e exibição.

**Alternatives considered**:

- Markdown ou HTML: não preservam edição, formatação de células e fórmulas de forma segura.
- Imagem/PDF: impede edição e acessibilidade de conteúdo.
- Múltiplas abas e processamento no servidor: fora de escopo e sem necessidade funcional atual.

## Decision 3: Trava renovável

**Decision**: Manter a reserva de edição junto à tabela, contendo participante, token opaco e instante de expiração; adquirir, renovar, liberar e salvar em operações atômicas.

**Rationale**: A expiração torna a reserva roubável, sem descartar rascunhos. Um token opaco impede que uma aba antiga — inclusive do mesmo participante — renove ou salve após uma nova aquisição. Operações atômicas garantem somente um titular em requisições concorrentes e em múltiplas instâncias do produto.

**Alternatives considered**:

- Reserva apenas no navegador: não protege contra outros participantes.
- Expiração que cancela o editor: contradiz a recuperação necessária quando a página fica oculta.
- Coedição em tempo real: requer sincronização de documento, presença e resolução de conflito; fora do MVP.

## Decision 4: Blocos e compatibilidade da conversa

**Decision**: Manter comentários textuais existentes e adicionar tabelas como entidade irmã; a resposta de contexto acrescenta itens de tabela e a interface mescla ambos cronologicamente.

**Rationale**: Evita migrar conteúdo Markdown existente e preserva os contratos já utilizados para comentários. A tela pode representar os dois como uma união tipada de blocos.

**Alternatives considered**:

- Converter a tabela em comentário textual: perde o documento estruturado.
- Substituir todos os comentários por uma tabela genérica de blocos: amplia a migração sem valor para o MVP.
