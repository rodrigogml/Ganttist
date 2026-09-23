# API Checklist: Busca Unificada e Views de Tarefas

**Purpose**: Validar qualidade dos contratos e requisitos da borda de persistência de views e histórico.
**Created**: 2026-09-22
**Feature**: [views-api.md](../contracts/views-api.md)

## Contratos e Falhas

- [x] CHK001 - Listagem, criação, atualização, remoção, importação e exportação possuem finalidade, autenticação e campos definidos? [Completude, Contract §List views–§Import/export] {auto}
- [x] CHK002 - Payloads usam convenção camelCase e a borda DB/DTO possui fonte de verdade e validação dos dois lados? [Consistência, Plan §Convenções de Borda] {auto}
- [x] CHK003 - Conflito de nome, arquivo inválido, acesso negado e view ausente possuem resultado distinguível e seguro? [Cobertura, Contract §Import view Error Responses; Spec FR-025–FR-027] {auto}

## Segurança e Evolução

- [x] CHK004 - A autorização de consulta e a propriedade de view são declaradas em cada operação mutável? [Cobertura, Contract §Create/Update/Delete/Import; Plan §Constitution Check IV] {auto}
- [x] CHK005 - O formato de arquivo possui identificador e versão, sem dados de origem que não sejam necessários à configuração? [Clareza, Contract §View representation; Data Model §Arquivo de View] {auto}

## Notes

- Itens `{auto}` foram resolvidos contra os artefatos citados.
- Rate limit específico, retry e idempotency-key não são requisitos adicionais desta feature; ela não chama dependências externas e usa as políticas transversais existentes.
