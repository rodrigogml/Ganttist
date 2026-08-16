# Discovery Briefing — Ganttist v1.0

Fonte autoritativa: `docs/especificacao_ganttist_v1.0.md`, aprovada para desenvolvimento.

## Visão e resultado

O Ganttist transforma projetos Todoist em planejamento visual confiável. O Todoist continua dono das tarefas e campos nativos; a aplicação acrescenta dependências, calendário, scheduling, criticidade, simulação, auditoria e experiência Gantt.

Usuário primário: pessoa responsável por planejar e acompanhar um projeto já operado no Todoist. Seu trabalho principal é enxergar a rota de entrega, revelar riscos e reagendar sem destruir duração, hierarquia ou histórico.

## Escopo do MVP

1. Passwordless e múltiplas sessões.
2. OAuth Todoist, escolha de projeto e hierarquia completa.
3. Tarefas com/sem data, grupos derivados e estados operacionais.
4. Calendário por Gantt, semana configurável e exceções concretas.
5. FS, SS, FF e SF sem lag/lead, intraprojeto e acíclicas.
6. Caminho crítico em dias úteis.
7. Drag/resize em dias inteiros, simulação ghost, confirmação e cascata.
8. Busca, filtros, edição, responsividade e feedback de sincronização.
9. Webhook, reconciliação, fila persistente, SSE e auditoria.

Fora do MVP: microserviços, JWT como sessão principal, Gantt de terceiros, tarefas locais independentes, PWA offline-first, lag/lead, feriados automáticos/recorrentes, compartilhamento e papéis avançados.

## Regras que orientam todas as decisões

- Datas de planejamento são civis `YYYY-MM-DD`, sem horas.
- O core PHP é autoridade de calendário, precedência, grupos, reagendamento e criticidade.
- Alteração por dependência só desloca para frente e preserva duração.
- Grupo pode ser predecessor; não pode ser sucessor no MVP.
- Campos nativos são reconciliados com Todoist; banco próprio não vira réplica autoritativa.
- Transação MySQL nunca aguarda HTTP Todoist.
- Operações externas são idempotentes, enfileiradas e auditáveis.

## Decisões internas assumidas

- Laravel 12 é usado por ser compatível com PHP 8.3 local e com o alvo PHP 8.4.
- ULID foi escolhido para entidades próprias.
- Fixture local permite avaliar UX e contratos sem credenciais Todoist.
- Componentes visuais são próprios e o desenho das relações usa SVG.
- Ambiente inicial adota `America/Sao_Paulo`, `pt-BR`, semana segunda–sexta e política de deadline `ANTERIOR`.

## Métricas de aceite

- Correção determinística dos golden cases.
- Zero ciclo/autodependência/duplicidade persistida.
- Um Gantt por `(user_id, todoist_project_id)`.
- Build reprodutível, migrations MySQL e suíte automatizada verdes.
- Alvo nominal de 2.000 tarefas; stress de 5.000 deve degradar de forma controlada.

## Riscos e mitigação

- Mudanças/limites da API Todoist: adapter isolado e contract tests planejados.
- Falha parcial: operação lógica, itens, tentativas, reconciliação e auditoria.
- Densidade visual: janela temporal, estrutura preparada para virtualização e filtros.
- Timezone/UTC: datas civis no domínio e timestamps apenas na infraestrutura.
- Credenciais/infra ausentes: contratos implementados e validação real registrada como pendência.
