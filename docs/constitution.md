<!--
Sync Impact Report
- Version: none -> 1.0.0
- Princípios modificados: criação dos cinco princípios fundamentais de governança.
- Seções adicionadas: Limites de Arquitetura e Dados; Qualidade, Segurança e Operação; Governance.
- Seções removidas: nenhuma.
- Artefatos que precisam atualização: AGENTS.md (ausente; nenhum impacto), docs/specs/ (ausente; aplicar a constituição nas próximas specs), docs/architecture/ (ausente; aplicar nos próximos artefatos), tasks.md (ausente; aplicar os gates nas próximas tarefas).
- TODOs pendentes: responsáveis de decisão, prazo/equipe/orçamento, compliance e retenção, SLO/SLA/RPO/RTO e metas quantitativas de performance.
-->

# Constituição do Ganttist

## Core Principles

### I. Todoist é a fonte de verdade para campos nativos

O sistema DEVE ler e alterar pela API do Todoist a existência, conteúdo, projeto, seção, hierarquia, prioridade, conclusão, data e deadline das tarefas. O banco próprio DEVE persistir somente identidade, integração, configurações, dependências, calendário, operações e demais metadados exclusivos. Nenhuma mudança pode introduzir tarefas locais independentes nem uma réplica autoritativa dos campos nativos. O adapter Todoist é a única fronteira permitida para comunicação com essa API.

**Racional**: preserva a autoridade declarada do Todoist e evita divergência silenciosa de dados.

### II. O core de planejamento é determinístico e independente da interface

Regras de calendário, duração, precedência, grupos, estados, criticidade e reagendamento DEVEM viver em uma camada de domínio PHP desacoplada do Laravel e do renderizador. O domínio DEVE operar em datas civis `YYYY-MM-DD`, dias inteiros e calendário explícito; interface e integração apenas solicitam cálculos e apresentam/aplicam resultados. Toda alteração que alcance o Todoist DEVE respeitar o resultado do core.

**Racional**: regras matemáticas previsíveis devem ser testáveis e produzir o mesmo resultado para o mesmo estado e relógio controlado.

### III. Integridade vem antes de conveniência de sincronização

O grafo de dependências DEVE impedir autodependências, duplicatas, ciclos e relações de grupo proibidas. Operações que cruzam MySQL e Todoist DEVEM ser modeladas como operações lógicas idempotentes, rastreáveis, recuperáveis e reconciliáveis; uma transação MySQL NUNCA pode aguardar uma resposta HTTP externa. Falhas parciais, eventos duplicados ou fora de ordem e edições concorrentes DEVEM resultar em estado explícito de sincronização, não em perda ou corrupção silenciosa de dados.

**Racional**: o sistema integra uma fonte externa assíncrona e precisa preservar o planejamento mesmo em condições de falha.

### IV. Qualidade e segurança são gates de entrega

Cada mudança de domínio DEVE ser coberta por testes determinísticos proporcionais ao risco; motor matemático e integridade do grafo exigem golden tests. Antes de merge ou deployment, CI DEVE executar lint, testes unitários, integração, verificações básicas de segurança e build; E2E e carga devem executar no pipeline apropriado. Releases são bloqueados por falhas de isolamento entre usuários, integridade do grafo, datas, sincronização sem loops, persistência, OAuth/autenticação ou risco de perda/corrupção de dados. Tokens são criptografados, sessões são server-side, endpoints aplicam validação e rate limit, e logs não expõem segredos.

**Racional**: cronogramas, credenciais e operações de cascata são dados e fluxos de alto impacto.

### V. A SPA Gantt é própria, responsiva e acessível

A área autenticada DEVE ser uma SPA Vue/TypeScript, com Gantt desenvolvido no projeto; bibliotecas não podem impor modelo de Gantt ou regras de negócio. A interface DEVE manter semântica de domínio consistente em desktop, tablet e celular, adaptando controles para viewport e toque, e DEVE cumprir os requisitos de acessibilidade especificados. O spike do Gantt DEVE validar árvore, virtualização, relações, drag, resize, zoom, grupos, ghosts, filtros e responsividade com 2.000 tarefas, além de observar degradação em 5.000, antes da consolidação visual.

**Racional**: o Gantt é o principal diferencial e precisa permanecer utilizável em dispositivos e volumes reais.

## Limites de Arquitetura e Dados

O produto é um monólito modular em PHP 8.4+ e Laravel, com Vue 3, TypeScript, store reativa centralizada, MySQL 9.7 `utf8mb4`, API JSON versionada e SSE para servidor→navegador. PHP-FPM + Apache ou equivalente deve suportar HTTPS, OAuth, webhooks e SSE. Filas persistentes inicialmente usam MySQL e o scheduler executa tarefas periódicas.

O Gantt é associado a um único projeto Todoist por usuário. Dependências são intraprojeto; grupos podem ser predecessores, nunca sucessores no MVP. Campos e dados derivados seguem a autoridade definida na especificação: Todoist para campos nativos, banco próprio para metadados e core para regras calculadas. Exceções a esses limites exigem ADR e alteração explícita da especificação quando alterarem comportamento, contratos, segurança, persistência, matemática, UX ou autoridade dos dados.

## Qualidade, Segurança e Operação

Ambientes de desenvolvimento, staging e produção DEVEM ser separados, com credenciais e callbacks próprios. Deploys, migrações, workers e scheduler devem ser reproduzíveis e documentados. A aplicação DEVE oferecer logs estruturados, métricas, liveness/readiness e visibilidade de filas e integrações; a infraestrutura é responsável por backups, restauração, certificados, alertas e segredos de produção.

O idioma inicial é `pt-BR`, e a arquitetura deve permitir internacionalização. A aplicação não é offline-first: indisponibilidade do Todoist pode permitir consulta do último estado conhecido, sinalizando degradação, mas escritas devem preservar consistência. Privacidade operacional requer minimização de dados em logs e métricas; a definição formal de compliance e de retenção permanece pendente.

## Governance

Esta constituição prevalece sobre convenções implícitas de implementação e é subordinada à especificação do cliente v1.0 para requisitos funcionais aprovados. Toda spec, plano, interface, tarefa e PR DEVE declarar ou demonstrar conformidade com os princípios aplicáveis. Conflitos, exceções e decisões locais que não alterem esses princípios devem ser registrados em ADR quando seu impacto for arquitetural ou duradouro.

Emendas exigem justificativa, análise de impacto nos artefatos afetados e atualização deste documento. Use versionamento semântico: MAJOR para remover/redefinir princípio de modo incompatível; MINOR para adicionar princípio ou expandir uma seção material; PATCH para clarificação sem mudança semântica. Não é permitido contornar um princípio com TODO implícito, feature flag ou decisão de implementação.

As pendências de decisão — responsáveis, prazo/equipe/orçamento, compliance/retenção, SLO/SLA/RPO/RTO e metas quantitativas de desempenho — devem permanecer explícitas nos artefatos seguintes até decisão autorizada. Elas não autorizam inventar requisitos de produto.

**Version**: 1.0.0 | **Ratified**: 2026-08-17 | **Last Amended**: 2026-08-17
