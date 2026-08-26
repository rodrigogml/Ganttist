# Backlog de Implementação: Gerenciamento Local de Projetos

**Escopo**: Substituir o fluxo obrigatório Todoist por gerenciamento local, preservando o Gantt e scheduling existentes.

**Legenda**: `[C]` crítico, `[A]` alto, `[M]` médio. Todas as subtarefas devem ter evidência de teste.

## FASE 1 - Fundação e ruptura de dados

### 1.1 Modelo persistente local `[C]`

Ref: Spec FR-001–021; Data Model

- [ ] 1.1.1 Criar migrations para projetos, seções, tarefas, pessoas, membros e convites locais.
- [ ] 1.1.2 Substituir referências Todoist nas entidades de calendário, dependência, operação e auditoria.
- [ ] 1.1.3 Criar limpeza explícita dos dados de teste e remover dependências de schema não utilizadas.
- [ ] 1.1.4 Testar constraints de projeto, árvore, datas, membros e unicidade.

### 1.2 Autorização por projeto `[C]`

Ref: Spec FR-015–017/021

- [ ] 1.2.1 Implementar resolução de proprietário, editor e leitor por projeto.
- [ ] 1.2.2 Implementar convite por e-mail, aceite, recusa e revogação.
- [ ] 1.2.3 Garantir que pessoa responsável não concede acesso.
- [ ] 1.2.4 Cobrir isolamento e matriz de permissões em testes de feature.

## FASE 2 - Domínio e workspace local

### 2.1 Tarefas, seções e pessoas `[A]`

Ref: Spec FR-004–012/020

- [ ] 2.1.1 Implementar comandos locais de seção e tarefa, incluindo raiz e exclusão em cascata de seção.
- [ ] 2.1.2 Implementar cadastro, busca e remoção de pessoa preservando tarefas sem responsável.
- [ ] 2.1.3 Implementar status calculado, conclusão real e reabertura.
- [ ] 2.1.4 Cobrir regras de status e estrutura com testes unitários e de integração.

### 2.2 Planejamento sobre dados locais `[A]`

Ref: Spec FR-013–014; Plan §Sequência 3–4

- [ ] 2.2.1 Adaptar snapshots e projeções para tarefas locais.
- [ ] 2.2.2 Adaptar dependências FS/SS/FF/SF, caminho crítico e reagendamento aos IDs locais.
- [ ] 2.2.3 Preservar calendário, simulação, auditoria e eventos SSE.
- [ ] 2.2.4 Executar regressão do motor de scheduling e grafo.

## FASE 3 - API e contratos

### 3.1 Recursos de projeto `[A]`

Ref: contracts/projects-api.md; Interface INT-WEB-001–004

- [ ] 3.1.1 Implementar lista e criação idempotente de projetos com indicadores.
- [ ] 3.1.2 Implementar workspace e eventos por projeto.
- [ ] 3.1.3 Implementar recursos de seções, tarefas, pessoas, membros, convites e dependências.
- [ ] 3.1.4 Cobrir contratos, erros de domínio e autorização por projeto.

### 3.2 Remoção do fluxo Todoist `[A]`

Ref: Spec FR-002/018; Interface INT-WEB-005

- [ ] 3.2.1 Remover gate, rotas ativas, webhooks, jobs e mensagens Todoist do fluxo operacional.
- [ ] 3.2.2 Remover segredos e configuração Todoist não usados no novo MVP.
- [ ] 3.2.3 Atualizar health/readiness e fixtures para domínio local.
- [ ] 3.2.4 Cobrir ausência de chamadas externas nos fluxos locais.

## FASE 4 - SPA incremental

### 4.1 Dashboard e criação `[A]`

Ref: Interface INT-WEB-001/002

- [ ] 4.1.1 Criar dashboard usando topbar, cards, botões e tokens existentes.
- [ ] 4.1.2 Implementar modal de projeto e todos os estados definidos.
- [ ] 4.1.3 Integrar dados reais, responsividade, teclado e leitores de tela.
- [ ] 4.1.4 Cobrir componentes e roundtrip de criação.

### 4.2 Workspace e pessoas `[A]`

Ref: Interface INT-WEB-003/004

- [ ] 4.2.1 Remover dependência de `TodoistSetup` sem reconstruir o Gantt existente.
- [ ] 4.2.2 Adaptar seletor, editor e criação rápida a projetos, seções e tarefas locais.
- [ ] 4.2.3 Adicionar aba Pessoas e acesso reutilizando modal de configurações atual.
- [ ] 4.2.4 Validar desktop, touch, teclado, acessibilidade e testes de componentes.

## FASE 5 - Qualidade e operação

### 5.1 Verificação final `[C]`

Ref: Quickstart; Checklist CHK007

- [ ] 5.1.1 Executar testes PHP, Vitest, build e cenários do quickstart.
- [ ] 5.1.2 Validar performance com 2.000 tarefas e indicadores da lista.
- [ ] 5.1.3 Configurar backup diário, retenção de 30 dias e evidência de restauração.
- [ ] 5.1.4 Atualizar README, modelo de dados, runbook e documentação de operação.

## Matriz de Dependências

```text
1.1 -> 1.2 -> 2.1 -> 2.2 -> 3.1 -> 4.1 -> 4.2 -> 5.1
                         \-> 3.2 -----------/
```

## Cobertura de Interfaces

| Interação | Tarefa |
|---|---|
| INT-WEB-001 | 4.1 |
| INT-WEB-002 | 4.1 |
| INT-WEB-003 | 4.2 |
| INT-WEB-004 | 4.2 |
| INT-WEB-005 | 3.2, 4.2 |

## Resumo Quantitativo

| Fase | Tarefas | Subtarefas |
|---|---:|---:|
| 1 | 2 | 8 |
| 2 | 2 | 8 |
| 3 | 2 | 8 |
| 4 | 2 | 8 |
| 5 | 1 | 4 |
| **Total** | **9** | **36** |

## Escopo Coberto

- Projetos locais, estrutura, tarefas, pessoas, membros, convites, autorização, planejamento, dashboard e Gantt incremental.

## Escopo Excluído

- Integração/sincronização opcional futura com Todoist; colaboração além dos papéis definidos; notificações além do convite.
