# Security Checklist: Busca Unificada e Views de Tarefas

**Purpose**: Validar que os requisitos preservam isolamento, autorização e minimização de dados durante consulta e portabilidade.
**Created**: 2026-09-22
**Feature**: [spec.md](../spec.md)

## Isolamento e Dados

- [x] CHK001 - Views e histórico são explicitamente escopados por usuário e projeto, impedindo leitura ou escrita de coleção alheia? [Cobertura, Spec FR-017/FR-020/FR-027; Data Model §View/§Consulta Recente] {auto}
- [x] CHK002 - Referências textuais de responsáveis e seções não concedem acesso e valores importados desconhecidos não causam busca fora do projeto? [Consistência, Spec FR-011/FR-026/FR-027; Plan §Constitution Check IV] {auto}
- [x] CHK003 - Arquivo exportado exclui identificadores de origem, tarefas, pessoas, membros e permissões? [Minimização de dados, Spec FR-024; Data Model §Arquivo de View] {auto}

## Entrada e Observabilidade

- [x] CHK004 - Query, nome, estado visual e arquivo importado possuem validação antes da escrita e falha sem efeito parcial? [Cobertura, Spec FR-010/FR-025; Contract §Import view] {auto}
- [x] CHK005 - Telemetria e feedback excluem texto integral da query, nomes, IDs e títulos potencialmente pessoais? [Proteção de dados, Interface §INT-WEB-001–003 Telemetry] {auto}

## Notes

- Itens `{auto}` foram resolvidos contra os artefatos citados.
- Políticas transversais de retenção, TLS e rotação de segredo permanecem governadas pela constituição e não são ampliadas por esta feature.
