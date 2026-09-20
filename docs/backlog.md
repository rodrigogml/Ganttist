# Backlog consolidado pós-vertical-slice

## P0 — completar MVP

- Homologar OAuth, webhook e sincronização contra uma conta Todoist real.
- Validar operação lógica de cascata com falhas reais, SSE e fila quando o volume justificar.
- Benchmarkar virtualização e touch em dispositivos reais.
- Spike/benchmark de 2.000 e stress de 5.000 tarefas com virtualização.
- Expandir golden cases de SS/FF/SF, grupos, caminho crítico e calendários alterados.
- E2E Playwright, acessibilidade e matriz de navegadores/dispositivos.

## P1 — hardening

- **BUG — consistência visual global:** auditar todos os modos e superfícies autenticadas para que as preferências de aparência da top bar (tamanho de texto e espaçamento) sejam aplicadas de forma uniforme; eliminar estilos ou layouts que ainda ignorem essas definições globais.
- Concorrência por campo, múltiplas abas e deduplicação de eco.
- Inserção/remoção com continuidade de rota e recuperação de falha parcial.
- Observabilidade completa, retenção de auditoria e exportação diagnóstica.
- Deploy Apache/PHP-FPM, rollback, alertas e teste de restauração.

## Futuro fora do MVP

- Lag/lead, feriados recorrentes/importados, compartilhamento, papéis avançados, PWA/offline, filtros salvos, minimapa e análise avançada de folga.
