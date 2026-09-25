# Project Briefing: Tarefas Recorrentes e Soneca

**Data**: 2026-09-24
**Status**: Aprovado
**Versão**: 1.0

---

## 1. Visão e Propósito

**O que é**: Evolução do Ganttist para permitir tarefas recorrentes e a soneca de uma ocorrência aberta, preservando o planejamento, o calendário e as dependências locais.

**Problema que resolve**: Hoje uma tarefa possui somente uma conclusão definitiva. Rotinas recorrentes perdem histórico e uma alteração pontual de data pode contaminar a repetição futura ou causar comportamento imprevisível na rede de planejamento.

**Proposta de valor**: O usuário agenda e acompanha rotinas com a mesma previsibilidade das tarefas finitas: cada ocorrência conserva sua identidade lógica, uma soneca altera somente sua execução corrente e o projeto não ganha artificialmente duração infinita.

## 2. Usuários e Stakeholders

| Ator | Papel | Ações Principais |
|------|-------|-----------------|
| Proprietário do projeto | Administra o planejamento | Cria, altera, adia, conclui definitivamente e consulta recorrências. |
| Editor do projeto | Planeja e executa trabalho | Cria, altera, adia e conclui ocorrências. |
| Leitor do projeto | Consulta o planejamento | Consulta a ocorrência atual, regra, projeção e histórico autorizado. |

**Stakeholders de decisão**: Usuário solicitante do produto.

## 3. Interfaces e Canais

| Interface/Canal | Usuários | Dispositivos/Plataformas | Cobertura | Conectividade | Paridade Esperada |
|-----------------|----------|--------------------------|-----------|---------------|-------------------|
| Workspace do projeto: Gantt e Tarefas | Proprietário, editor e leitor | Web responsiva, desktop, tablet e telefone | MVP | Online; preserva a leitura offline já existente quando aplicável [inferido] | A mesma regra, ocorrência atual e estado operacional nas duas views. |
| Editor de tarefa e menus de contexto | Proprietário e editor | Web responsiva | MVP | Online | Editor visual, expressão natural PT-BR e ações de soneca com a mesma semântica. |

**Restrições tecnológicas já obrigatórias**: Manter Laravel/PHP, Vue 3/TypeScript, Pinia, MySQL e o core de planejamento determinístico em PHP. Datas são civis e o calendário do projeto é a autoridade para dias úteis.

## 4. Escopo

### MVP (Essencial)

1. Modelar uma regra de recorrência estruturada, versionada e com uma ocorrência lógica corrente, separada do planejamento efetivo da ocorrência.
2. Oferecer editor visual e expressões naturais PT-BR de gramática limitada; ambas as entradas geram exatamente a mesma regra canônica.
3. Suportar recorrências diárias, semanais, mensais e anuais; intervalos em dias úteis, semanas, meses e anos; múltiplos dias da semana ou do mês; primeiro e último dia útil; ordinais mensais; data de início; data final inclusiva; e série sem fim.
4. Distinguir regras fixas de calendário, como `toda segunda`, de regras por intervalo, como `a cada 4 dias`.
5. Registrar cada conclusão de recorrência em histórico por ocorrência e tornar a conclusão idempotente contra clique ou retry duplicado.
6. Avançar regras fixas para a primeira data compatível estritamente posterior ao maior entre a data agendada da ocorrência e a conclusão efetiva; pular automaticamente ocorrências perdidas.
7. Avançar regras por intervalo a partir de `max(cursor lógico, data agendada da ocorrência, conclusão efetiva)`: dias contam dias úteis; semanas, meses e anos usam suas unidades de calendário e preservam a data lógica.
8. Permitir soneca para próximo dia útil, primeiro dia útil da próxima semana, 3 dias úteis, 7 dias úteis e data escolhida, sempre normalizada pelo calendário do projeto e preservando a duração.
9. Fazer soneca alterar somente a ocorrência aberta, com preview de impacto antes da confirmação, classificado como informativo, alerta ou crítico.
10. Permitir recorrente como sucessora de uma dependência, mas nunca como predecessora, inclusive quando a ponta for uma seção cuja expansão alcance uma tarefa recorrente.
11. Excluir recorrentes do término, da folga, do caminho crítico e do progresso finitos do projeto, sem excluí-las da projeção operacional da ocorrência corrente.
12. Permitir remover a recorrência e concluir definitivamente a tarefa, sem limite por quantidade de ocorrências.

### Pós-MVP (Desejável)

1. Política alternativa de recuperação de ocorrências perdidas, como `catch up`.
2. Dependências entre ocorrências correspondentes de séries recorrentes.
3. Linguagem natural PT-BR mais ampla, com interpretações assistidas, sem substituir a regra estruturada.
4. Relatórios e filtros avançados sobre histórico de ocorrências, atraso e aderência à rotina.

### Fora de Escopo

- Recorrência por quantidade de ocorrências.
- Uma recorrente como predecessora de tarefa ou seção.
- Inclusão de recorrências contínuas no caminho crítico, data final ou progresso finito do projeto.
- Datas ou durações em horas.
- Sintaxe natural fora da gramática PT-BR publicada na feature.

## 5. Prioridades e Trade-offs

**Ordem de prioridade**: Determinismo do domínio e integridade do cronograma > previsibilidade da experiência > amplitude da linguagem natural > paridade com ferramentas externas.

**Decisões explícitas**:

- A ocorrência lógica, seu agendamento e sua conclusão são conceitos distintos; soneca nunca altera silenciosamente a regra.
- O calendário do projeto veta qualquer data de soneca não útil; `amanhã` significa o próximo dia útil.
- Há uma única política de atraso na V1: pular ocorrências perdidas. Não haverá fila de conclusões antigas.
- A função de conclusão deve distinguir retry da confirmação intencional da ocorrência seguinte; cursor esperado e transação são obrigatórios.
- Todoist é referência de descoberta e UX, não fonte de verdade nem autoridade da semântica do Ganttist.
- O preview compara projeções antes e depois; a SPA não calcula cascata, criticidade ou próxima ocorrência.
- Na V1, excluir tarefa ou projeto exclui em cascata seus históricos de ocorrência. Remover recorrência ou concluir definitivamente preserva os históricos já registrados.

## 6. Restrições

| Restrição | Valor | Notas |
|-----------|-------|-------|
| Prazo | Não definido | Não informado. |
| Equipe | Não definida | Não informada. |
| Budget | Não definido | Não informado. |
| Produto | Nenhuma soneca em dia não útil | Datas rápidas e escolhidas são normalizadas pelo calendário do projeto. |
| Domínio | Projeto finito permanece finito | Recorrentes não alteram prazo global, CPM, folga ou progresso. |
| Integridade | Proibição de predecessora recorrente | Deve ser validada na criação/edição da dependência, da seção e da recorrência. |
| Concorrência | Conclusão idempotente | Uma repetição do mesmo comando não pode avançar duas ocorrências. |

## 7. Stack Técnica

| Camada | Tecnologia | Justificativa |
|--------|-----------|---------------|
| Backend | Laravel 12 / PHP | Aplicação e regras atuais de projetos locais. |
| Domínio | Core PHP independente de Laravel | O core atual já centraliza calendário, projeção, duração, precedências e scheduling. |
| Interface | Vue 3, TypeScript e Pinia | Workspace, editor de tarefa e estado reativo existentes. |
| Banco de dados | MySQL | Persistência local autoritativa de tarefas e histórico de ocorrências. |
| Integrações | Nenhuma obrigatória | A funcionalidade é local; Todoist não participa do fluxo. |

## 8. Qualidade e Padrões

**Padrões adotados**:

- A regra estruturada é a única fonte de cálculo; expressão natural e editor visual são conversores de entrada e uma representação legível, não fontes concorrentes.
- O core deve produzir o mesmo cursor, próxima ocorrência, planejamento e impacto para o mesmo estado persistido, relógio e calendário controlados.
- A conclusão de recorrência é transacional, registra o histórico e usa identificação/cursor esperado para idempotência.
- Soneca passa pelo normalizador de planejamento para preservar duração, validações e semântica de intenção existentes.
- A rede operacional pode projetar a ocorrência recorrente como sucessora; a rede finita do projeto deve excluí-la do cálculo de CPM, folga, término e progresso.
- Testes unitários, de integração e de interface devem cobrir calendário, regra, transições, duplicidade, atraso, soneca, dependências e preview de impacto.

**Compliance**: Nenhum requisito novo informado. Na V1, o histórico de ocorrência é apagado em cascata junto com a tarefa ou projeto; não há retenção, anonimização ou arquivamento independente.

## 9. Visão de Futuro

**6 meses**: Rotinas recorrentes e sonecas são comportamentos confiáveis e visíveis no Gantt e na view de Tarefas, com histórico auditável e filtros operacionais básicos.

**12 meses**: Relatórios de aderência e atraso, dependências por ocorrência, políticas alternativas de recuperação e ampliação assistida da linguagem natural podem evoluir sobre o mesmo modelo de ocorrência.

**Riscos conhecidos**:

- Uma gramática natural ampla pode criar interpretações ambíguas; a V1 precisa publicar e validar uma gramática limitada.
- A separação entre rede operacional e rede finita exige que projeção, CPM, progresso e estatísticas não misturem semânticas.
- Seções tornam a proibição de recorrente como predecessora indireta e exigem validação tanto ao criar relações quanto ao tornar uma tarefa recorrente.
- Alterações simultâneas podem duplicar ou avançar ocorrência indevidamente sem bloqueio transacional e cursor esperado.

---

## Itens a Definir

| Item | Dimensão | Impacto |
|------|----------|---------|
| Gramática PT-BR canônica, aliases aceitos e diagnósticos de cada expressão | Produto / Interface | Alto |
| Apresentação de regra, cursor, soneca e histórico nas views Tarefas e Gantt | Interface | Alto |
| Contrato de preview/confirmação e ações exigidas para impactos críticos | API / Interface | Alto |
| Indicadores operacionais de recorrência versus indicadores finitos do projeto | Produto / Métricas | Médio |

---

**Próximo passo recomendado**: `Dev Pipeline - 3. Specification - Specify` para converter este briefing em uma especificação funcional detalhada.
