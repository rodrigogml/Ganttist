# Backlog de Implementação: Tabelas na conversa da tarefa

**Escopo**: Adicionar blocos de tabela formatada e editável à conversa de tarefa, com reserva renovável, recuperação por cópia e os papéis globais existentes.

**Legenda de status:**
- `[ ]` Pendente
- `[~]` Em andamento
- `[x]` Concluído
- `[!]` Bloqueado

**Legenda de criticidade:**
- `[C]` Crítico — impacto de segurança, concorrência ou operação bloqueante.
- `[A]` Alto — funcionalidade essencial da feature.
- `[M]` Médio — necessário, mas adiável sem bloquear o fluxo principal.

Todas as subtarefas devem produzir evidência de teste ou validação.

## FASE 1 - Fundação de dados e concorrência

### 1.1 Modelo persistente de tabela `[C]`

Ref: Spec FR-002–004/FR-020–022; Plan §Persistence Design; data-model.md

- [x] 1.1.1 Criar migration da entidade de tabela de tarefa com projeto, tarefa, publicador, documento, versão e campos de reserva.
- [x] 1.1.2 Declarar FKs, índices e regras de exclusão coerentes com projeto e tarefa locais.
- [x] 1.1.3 Criar serviço/repositório que normaliza e valida uma aba, dimensões e máximo de 500 KB antes de persistir.
- [x] 1.1.4 Adaptar duplicação de tarefa para copiar tabelas sem reserva ativa e manter o publicador.
- [x] 1.1.5 Cobrir schema, limites, cópia e isolamento projeto–tarefa em testes Feature.

### 1.2 Reserva atômica e proteção de frequência `[C]`

Ref: Spec FR-012–016/FR-020–022; Plan §Architecture and Data Flow; checklists/api.md CHK009

- [x] 1.2.1 Definir e registrar limite de frequência por usuário/tabela que aceite renovação a cada 2–3 segundos sem permitir abuso.
- [x] 1.2.2 Implementar aquisição transacional com token opaco armazenado somente como hash e vencimento de 10 segundos.
- [x] 1.2.3 Implementar renovação tardia pelo mesmo token, tomada após expiração por outro participante e liberação explícita.
- [x] 1.2.4 Garantir que salvar e excluir verificam a reserva na mesma transação, inclusive em múltiplas instâncias.
- [x] 1.2.5 Cobrir corrida de aquisição, token obsoleto, retomada, tomada e throttle nos testes Feature com relógio controlado.

## FASE 2 - API e leitura unificada da conversa

### 2.1 Blocos de tabela e contexto de tarefa `[A]`

Ref: Spec FR-001–006/FR-019/FR-021; contracts/tables-api.md §Contexto/Publicar

- [x] 2.1.1 Estender o contexto da tarefa com tabelas escopadas e DTO consistente com comentários existentes.
- [x] 2.1.2 Criar publicação de nova tabela para proprietário/editor com validação, autor, data e versão inicial.
- [x] 2.1.3 Atualizar contagem de conversa e ordenação para incluir comentários e tabelas sem migrar Markdown existente.
- [x] 2.1.4 Rejeitar leitor, tabela inexistente e referência fora de projeto/tarefa sem vazamento de dados.
- [x] 2.1.5 Cobrir contexto, publicação, autorização e contagem em testes de integração.

### 2.2 Contratos de edição exclusiva `[C]`

Ref: Spec FR-012–018/FR-022; contracts/tables-api.md §Adquirir/Renovar/Salvar/Excluir

- [x] 2.2.1 Expor endpoints de aquisição, renovação e liberação com os códigos `TABLE_LOCKED` e `TABLE_LOCK_LOST`.
- [x] 2.2.2 Expor salvamento e exclusão condicionados ao token de reserva vigente.
- [x] 2.2.3 Garantir que respostas não retornam hash ou token fora da sessão que o adquiriu.
- [x] 2.2.4 Criar testes de contrato para todos os payloads, erros 403/404/409/422 e versão incrementada.
- [ ] 2.2.5 Executar o roundtrip HTTP de quickstart contra backend real e registrar o shape observado.

## FASE 3 - Adaptador de planilha e tipos da SPA

### 3.1 Integração lazy do Univer `[A]`

Ref: Plan §Frontend Design; research.md Decision 1–2; Spec FR-007–011

- [x] 3.1.1 Adicionar versões compatíveis dos pacotes Univer e estilos necessários, sem módulos Pro de colaboração.
- [x] 3.1.2 Criar adaptador que monte e destrua uma instância por editor/visualizador e carregue o runtime em chunk assíncrono.
- [x] 3.1.3 Normalizar snapshot de uma aba para documento persistido, preservando valores, fórmulas, estilos, dimensões e mesclagens.
- [x] 3.1.4 Configurar toolbar e operações do MVP; ocultar múltiplas abas, importação/exportação, gráficos e recursos fora de escopo.
- [x] 3.1.5 Criar testes do adaptador e confirmar build/chunks/CSS sem erro.

### 3.2 Tipos e cliente de tabelas `[A]`

Ref: Plan §Convenções de Borda; contracts/tables-api.md; Interface INT-WEB-001–003

- [x] 3.2.1 Criar união tipada para comentário e tabela e DTOs de documento, reserva e conflitos.
- [x] 3.2.2 Implementar cliente HTTP para contexto, publicação, reserva, renovação, salvamento, liberação e exclusão.
- [x] 3.2.3 Centralizar CSRF, parse de erro e mapeamento dos códigos de reserva para estados de interface.
- [x] 3.2.4 Criar teste smoke de paridade entre payload real, contrato e tipos do cliente.

## FASE 4 - Experiência de conversa e tabelas

### 4.1 Conversa, blocos e compositor por abas `[A]`

Ref: Interface INT-WEB-001; Spec FR-001–006/FR-019; wireframes/int-web-001.md

- [x] 4.1.1 Alterar a cronologia para renderizar comentários Markdown e cartões de tabela ordenados, com autor/data e contagem total.
- [x] 4.1.2 Implementar `tablist` com “Novo Comentário” e “Nova Tabela”, preservando rascunhos ao alternar e no aviso de descarte.
- [x] 4.1.3 Integrar publicação de tabela, estados de carga/erro/sucesso e restrição visual para leitor.
- [x] 4.1.4 Aplicar regras desktop/tablet/telefone para janela, tabs, rolagem e teclado virtual.
- [ ] 4.1.5 Cobrir componentes, teclado de tabs, permissões e roundtrip de publicação com dados reais.

### 4.2 Editor, visualizador e acessibilidade de tabela `[A]`

Ref: Interface INT-WEB-002; Spec FR-004/FR-007–011; wireframes/int-web-001.md

- [x] 4.2.1 Implementar editor de nova tabela com toolbar, limites visíveis e ação “Publicar tabela”.
- [x] 4.2.2 Implementar visualizador somente leitura do documento publicado em cartão de tabela.
- [x] 4.2.3 Implementar inserção/remoção, mesclagem, estilos, dimensões, copiar/colar, undo/redo e fórmulas básicas.
- [ ] 4.2.4 Verificar foco, nomes acessíveis, alternativa textual de cópia e uso por teclado/touch da grade integrada.
- [ ] 4.2.5 Cobrir preservação de documento e os estados empty, validation-error, remote-error e offline.

### 4.3 Edição exclusiva e recuperação `[A]`

Ref: Interface INT-WEB-003; Spec FR-012–018; wireframes/int-web-003.md

- [x] 4.3.1 Implementar abertura de tabela publicada com aquisição e renovação silenciosa a cada 2 segundos.
- [x] 4.3.2 Parar renovação e liberar reserva em salvar/cancelar/desmontar, sem liberar por página oculta.
- [x] 4.3.3 Implementar estado de reserva ocupada, salvamento/exclusão protegidos e feedback de conflito.
- [x] 4.3.4 Ao receber perda de reserva, congelar rascunho, mover foco ao alerta e oferecer cópia ou “Publicar como nova tabela”.
- [ ] 4.3.5 Cobrir retomada tardia, tomada por outro editor, recovery por cópia, touch e teclado em testes de componente.

## FASE 5 - Regressão, segurança e entrega

### 5.1 Validação integral `[C]`

Ref: quickstart.md; Spec SC-001–005; checklists/interface.md; checklists/api.md

- [x] 5.1.1 Executar testes PHP, Vitest, verificação de tipos e build de produção, registrando falhas reais.
- [ ] 5.1.2 Executar todos os cenários de quickstart com dois usuários, incluindo aquisição concorrente e publicação como cópia.
- [ ] 5.1.3 Inspecionar visualmente os wireframes em desktop, tablet e telefone e validar foco, contraste e alvo touch.
- [ ] 5.1.4 Verificar que telemetria/logs não contêm token, fórmulas ou documento de tabela.
- [ ] 5.1.5 Atualizar documentação de uso e operação com limites, ausência de coedição e comportamento da reserva.

## Matriz de Dependências

```text
1.1 ─┬─> 2.1 ─> 3.2 ─> 4.1 ─┐
     │                       ├─> 5.1
1.2 ─┴─> 2.2 ─> 3.2 ─> 4.3 ─┤
                3.1 ─> 4.2 ─┘
```

## Cobertura de Interfaces

| Interação | Tarefa |
|---|---|
| INT-WEB-001 | 4.1 |
| INT-WEB-002 | 3.1, 4.2 |
| INT-WEB-003 | 1.2, 2.2, 4.3 |

## Resumo Quantitativo

| Fase | Tarefas | Subtarefas |
|---|---:|---:|
| 1 | 2 | 10 |
| 2 | 2 | 10 |
| 3 | 2 | 9 |
| 4 | 3 | 15 |
| 5 | 1 | 5 |
| **Total** | **10** | **49** |

## Escopo Coberto

- Blocos de tabela, documento estruturado, formatação e fórmulas básicas.
- Papéis globais, reserva renovável, tomada após expiração e recuperação por publicação como cópia.
- APIs, SPA responsiva, acessibilidade, testes concorrentes e validação integral.
- Proteção de frequência da reserva, oriunda de `checklists/api.md CHK009`.

## Escopo Excluído

- Coedição, atualização automática, auditoria, permissões por tabela/tarefa e merge automatizado.
- Múltiplas abas, importação/exportação, gráficos, fórmulas avançadas e módulos Pro do Univer.
