# ADR 0001 — Core autoritativo e propriedade dos dados

Status: aceito. Fonte: Especificação 1.0.

O Todoist é autoridade dos campos nativos e tarefas. MySQL armazena identidade, integração, configuração, dependências, overrides, fila e histórico. O core PHP calcula calendário, datas derivadas, precedência, grupos e criticidade. O front-end nunca decide regra de negócio.

Consequências: não existe tabela autoritativa de tarefas; IDs externos são opacos; toda API Todoist atravessa um gateway; cálculos podem ser testados sem framework; falhas externas exigem operação lógica e reconciliação.
