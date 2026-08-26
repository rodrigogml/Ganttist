<!--
Sync Impact Report
- Version: 1.0.0 -> 2.0.0
- Princípios modificados: Todoist deixa de ser a fonte de verdade; o domínio passa a gerir projetos, estrutura e tarefas locais; a integridade passa a abranger hierarquia, dependências, pessoas e permissões locais.
- Seções adicionadas: Limites de Arquitetura e Dados locais; Acesso, Pessoas e Privacidade.
- Seções removidas: nenhuma.
- Artefatos que precisam atualização: README.md, docs/data-model.md, docs/discovery-briefing.md, docs/pending.md, todas as specs em docs/specs/, contratos de API, migrations, fixtures e testes. As specs access-todoist e todoist-synchronization deixam de descrever o MVP; as demais precisam substituir conceitos e campos Todoist por dados locais.
- TODOs pendentes: fórmula de progresso; fluxo de convite, aceite e remoção de membros usuários; regras de exclusão de seções, pessoas e tarefas com dependências; compliance, retenção, SLO/SLA/RPO/RTO e metas quantitativas de desempenho.
-->

# Constituição do Ganttist

## Core Principles

### I. O Ganttist é a fonte de verdade dos projetos locais

O sistema DEVE persistir e administrar localmente projetos, seções, tarefas, pessoas, membros, permissões, datas, conclusão, dependências, calendário, operações e auditoria. A autenticação no Ganttist DEVE permitir acessar e criar projetos sem conexão, conta ou autorização do Todoist. Integrações externas futuras DEVEM ser opcionais, isoladas por adapter e não podem tornar a disponibilidade do produto ou a autoridade dos dados locais dependentes de um provedor externo.

**Racional**: o produto precisa operar como gerenciador de projetos autônomo e manter seus dados utilizáveis sem um serviço de terceiros.

### II. O core de planejamento é determinístico e independente da interface

Regras de calendário, duração, precedência FS/SS/FF/SF, estados, criticidade, progresso e reagendamento DEVEM viver em uma camada de domínio PHP desacoplada de Laravel e do renderizador. O domínio DEVE operar em datas civis `YYYY-MM-DD`, dias inteiros e calendário explícito; interface e integrações apenas solicitam cálculos e apresentam ou aplicam resultados. Para o mesmo estado persistido e relógio controlado, o resultado DEVE ser idêntico.

**Racional**: regras previsíveis são essenciais para o cronograma, seus indicadores e testes confiáveis.

### III. Estrutura, dependências e estados preservam integridade

Seções DEVEM formar uma árvore acíclica de profundidade livre. Tarefas DEVEM pertencer diretamente à raiz do projeto ou a uma seção, e nunca a outra tarefa. O grafo de dependências DEVE impedir autodependências, duplicatas, referências entre projetos e ciclos. O status de uma tarefa DEVE ser calculado, sem escolha manual, com `CONCLUÍDA` acima de `ATRASADA`, `BLOQUEADA`, `AGENDADA` e `ABERTA`; ao reabrir uma tarefa, sua data de conclusão real DEVE ser removida e o status recalculado.

**Racional**: estrutura e regras explícitas evitam dados contraditórios e cronogramas incoerentes.

### IV. Acesso explícito e pessoas separadas de contas

Cada projeto DEVE ter um proprietário e conceder acesso somente por papéis explícitos: proprietário, editor ou leitor. Proprietário DEVE gerir membros e poder excluir o projeto; editor DEVE criar e alterar estrutura e tarefas; leitor DEVE apenas consultar. Responsáveis DEVEM ser pessoas locais com nome obrigatório e e-mail opcional, podendo ou não ter uma conta, ser membro ou aceitar convite. Toda leitura e escrita DEVE verificar a autorização do usuário autenticado no projeto, sem expor dados de outro usuário ou projeto.

**Racional**: gestores precisam delegar a pessoas que não usam o sistema, sem enfraquecer isolamento e controle de acesso.

### V. Qualidade, segurança e experiência são gates de entrega

Mudanças de domínio DEVEM ter testes determinísticos proporcionais ao risco; cálculos de calendário, status, dependências, permissões e integridade exigem testes de unidade e integração. Antes de entrega, CI DEVE executar lint, testes, verificações básicas de segurança e build. A SPA Gantt DEVE ser própria, responsiva e acessível, mantendo semântica de domínio consistente em desktop, tablet e celular. Sessões DEVEM permanecer server-side; endpoints DEVEM validar entrada, aplicar autorização e rate limit; logs e métricas não podem expor dados pessoais desnecessários ou segredos.

**Racional**: projetos, pessoas e permissões são dados sensíveis, e o Gantt precisa ser confiável em qualquer dispositivo suportado.

## Limites de Arquitetura e Dados locais

O produto é um monólito modular em PHP 8.4+ e Laravel, com Vue 3, TypeScript, store reativa centralizada, MySQL `utf8mb4`, API JSON versionada e SSE. PHP-FPM + Apache ou equivalente deve suportar HTTPS e SSE. O Gantt é associado a um projeto local; dependências são intraprojeto. Persistência local é a autoridade de todos os campos do MVP. A integração Todoist, se introduzida, exige especificação, contrato, adapter, estratégia de sincronização, resolução explícita de conflitos e não pode reduzir as funcionalidades locais.

## Acesso, Pessoas e Privacidade

Dados de pessoas sem conta DEVEM ser minimizados ao nome obrigatório e e-mail opcional. Um responsável sem acesso ao projeto não ganha permissão implícita por receber atribuição. Convites, aceite, remoção de membros e notificações a contas existentes devem ser especificados antes de implementação. A definição formal de compliance, retenção, exclusão de conta e backups permanece pendente e não autoriza persistir dados além do necessário para a funcionalidade aprovada.

## Governance

Esta constituição prevalece sobre convenções implícitas e é subordinada a requisitos funcionais aprovados mais recentes. Toda spec, plano, interface, tarefa e PR DEVE demonstrar conformidade com os princípios aplicáveis. Conflitos, exceções e decisões duradouras que alterem comportamento, contratos, segurança, persistência, matemática, UX ou autoridade de dados DEVEM ser registrados em ADR e, quando contrariarem estes princípios, exigem emenda explícita.

Emendas exigem justificativa, análise de impacto nos artefatos afetados e atualização deste documento. Use versionamento semântico: MAJOR para remover ou redefinir princípio de modo incompatível; MINOR para adicionar princípio ou expandir materialmente uma seção; PATCH para clarificação sem mudança semântica. Não é permitido contornar um princípio com TODO implícito, feature flag ou decisão de implementação.

As pendências sobre progresso, colaboração, exclusões, compliance/retenção, SLO/SLA/RPO/RTO, responsáveis operacionais e metas quantitativas de desempenho DEVEM permanecer explícitas nos artefatos seguintes até decisão autorizada. Elas não autorizam inventar requisitos de produto.

**Version**: 2.0.0 | **Ratified**: 2026-08-17 | **Last Amended**: 2026-08-25
