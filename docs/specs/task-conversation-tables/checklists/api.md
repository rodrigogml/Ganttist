# API Checklist: Tabelas na conversa da tarefa

**Purpose**: Validar contratos, autorização, concorrência e tratamento de erro da borda HTTP.
**Created**: 2026-09-11
**Feature**: [spec.md](../spec.md)

## Contratos e autorização

- [x] CHK001 - Os endpoints de contexto, criação, aquisição, renovação, liberação, salvamento e exclusão têm método, autorização, request, resposta e erros definidos? [Completude, Contracts §tables-api.md] {auto}
- [x] CHK002 - O contrato declara consistentemente `snake_case` na borda, incluindo documento, versão e token, e o plano identifica o mapeador de DTO? [Consistência, Contracts introdução; Plan §Convenções de Borda] {auto}
- [x] CHK003 - Cada operação de escrita exige proprietário/editor e cada recurso é escopado por projeto e tarefa? [Autorização, Spec FR-005, FR-021; Contracts §Publicar/Salvar/Excluir] {auto}
- [x] CHK004 - O token opaco não é retornado em contexto de leitura e seu uso é limitado à reserva do participante? [Proteção de dados, Plan §Persistence Design; Contracts §Adquirir reserva] {auto}

## Concorrência e falhas

- [x] CHK005 - A semântica de 10 segundos, renovação de 2–3 segundos, retomada sem tomada e tomada por terceiro está especificada sem contradição? [Clareza, Spec FR-013–016; Contracts §Renovar] {auto}
- [x] CHK006 - O contrato distingue reserva ativa de terceiro (`TABLE_LOCKED`) de reserva que foi perdida (`TABLE_LOCK_LOST`) e mapeia ambos para 409? [Error handling, Contracts §Adquirir e §Renovar; Interface INT-WEB-003] {auto}
- [x] CHK007 - Salvar, liberar e excluir exigem a mesma capacidade vigente e a exigência é aplicada entre instâncias concorrentes? [Consistência, Spec FR-020–022; Plan §Architecture and Data Flow] {auto}
- [x] CHK008 - Os limites estruturais são validados em criação e salvamento e devolvem erro que preserve o rascunho no cliente? [Input validation, Spec FR-004; Contracts §Publicar/Salvar; Interface INT-WEB-002] {auto}

## Operação e gaps encaminhados

- [x] CHK009 - Há limite de frequência quantificado para aquisição e renovação que não bloqueie a renovação normal a cada 2–3 segundos? [Clareza, Plan §API and Validation Design: 90/min por usuário/tabela] {auto}
- [x] CHK010 - A feature declara que não necessita scheduler, credenciais externas, rotação de chaves ou backup adicional, e isola a decisão cross-instance da reserva? [Infraestrutura, Spec §Key Entities; Plan §Constitution Check] {auto}
- [x] CHK011 - A telemetria requerida cobre conflitos e falhas sem incluir documento, fórmulas ou token? [Observabilidade, Interface §INT-WEB-003 Telemetry] {auto}

## Notes

- Itens `{auto}` foram resolvidos contra os artefatos citados.
- **CHK009** foi resolvido com 90 requisições/minuto por usuário/tabela e continua coberto pela tarefa 1.2.
