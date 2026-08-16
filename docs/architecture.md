# Arquitetura

```text
Browser / Vue SPA
  ├─ comandos HTTP JSON ──> Laravel /api/v1 ──> Application/Core
  ├─ eventos SSE <─────────┘                    ├─ Scheduling domain
  │                                            ├─ Audit / logical operations
  │                                            ├─ Todoist Gateway ──> Todoist API
  │                                            └─ Queue / scheduler
  └─ sessão server-side ────────────────────────────────> MySQL 9.7
Todoist webhook ──HMAC──> receiver ──dedupe──> persistent event/queue
```

O monólito é modular. O domínio de scheduling não depende de HTTP, ORM ou Todoist. O adapter é a única fronteira autorizada a falar com Todoist. Escritas externas usam operação lógica: snapshot local curto, chamada externa sem transação aberta, confirmação/retry e reconciliação.

## Autoridade dos dados

| Informação | Autoridade |
|---|---|
| título, prioridade, projeto, seção, hierarquia, datas, deadline, conclusão | Todoist |
| dependências e tipo | MySQL/Ganttist |
| calendário e configuração do Gantt | MySQL/Ganttist |
| completion date override | MySQL/Ganttist |
| estado, grupos, duração, scheduling e criticidade | Core, calculados das fontes acima |
| histórico operacional e fila | MySQL/Ganttist |

## Módulos entregues

- `Domain/Scheduling`: calendário canônico, restrições, ciclo, topologia, cascata, duração e folga.
- `Infrastructure/Todoist`: gateway HTTP configurável.
- API: workspace, simulação, health e webhook.
- Auth: desafio passwordless one-time, sessão e logout.
- SPA: tabela/hierarquia, timeline, barras-resumo, relações SVG, criticidade, busca, filtros, zoom, seleção e painel.

## Evolução contratada

SSE, workers e scheduler usam primitivas Laravel/MySQL já provisionadas. A camada de desenho poderá migrar de SVG para Canvas sem mover regras para o front-end.
