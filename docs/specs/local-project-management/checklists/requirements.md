# Requirements Checklist: Gerenciamento Local de Projetos

**Purpose**: Validar completude, consistência e rastreabilidade antes da execução.
**Created**: 2026-08-25
**Feature**: [spec.md](../spec.md)

## Fundação e domínio

- [x] CHK001 - A autoridade local, a remoção da obrigatoriedade Todoist e a ruptura de dados estão explicitamente definidas? [Completude, Spec §FR-002/018; Plan §Summary] {auto}
- [x] CHK002 - A hierarquia separa seção recursiva de tarefa não aninhável de forma testável? [Clareza, Spec §FR-004/005; Data Model §Seção/Tarefa] {auto}
- [x] CHK003 - A precedência entre status é consistente com datas, conclusão e dependências? [Consistência, Spec §FR-008–012] {auto}
- [x] CHK004 - O indicador de progresso possui fórmula verificável? [Mensurabilidade, Spec §FR-019] {auto}

## Acesso e segurança

- [x] CHK005 - Papéis, permissões, convite e ausência de acesso implícito do responsável estão definidos? [Completude, Spec §FR-015–017/021] {auto}
- [x] CHK006 - A consequência de excluir seção e pessoa está definida? [Cobertura, Spec §FR-020] {auto}
- [ ] CHK007 - A política de backup diário, retenção e restauração é aceitável para a operação do produto? [Risco, Spec §FR-022] {humano}

## Interface e qualidade

- [x] CHK008 - Dashboard, criação, workspace, pessoas/acesso e remoção do gate Todoist têm estados e rastreabilidade? [Completude, Interface §INT-WEB-001–005] {auto}
- [x] CHK009 - A preservação incremental do Gantt é uma restrição explícita de interface? [Consistência, Interface §INT-WEB-003] {auto}
- [x] CHK010 - Os cenários de validação abrangem criação, status, convite e roundtrip? [Cobertura, Quickstart] {auto}

## Notes

- CHK007 é decisão operacional e não bloqueia o início local; entra como tarefa de configuração antes de produção.
