# Briefing do Projeto: Identidade obrigatória e editor unificado

**Data**: 2026-08-26
**Status**: Validado

## Visão e Propósito

Evitar contas sem nome e tornar o gerenciamento de tarefas e seções consistente em uma única barra lateral.

## Usuários e Stakeholders

- Proprietário, editor e leitor de projeto.
- Pessoa responsável pode não ter conta nem acesso ao projeto.
- Usuário autenticado cria ou acessa sua própria conta.

## Escopo

- Contas exigem nome obrigatório; o único registro atual sem nome recebe `Rodrigo Leitão`.
- Ao informar e-mail ainda não cadastrado, a interface solicita o nome e a conta só é criada após a confirmação do e-mail.
- Convites usam e-mail exclusivamente para acesso. O combo de responsável mostra o nome registrado na Pessoa do projeto.
- Criação e edição de tarefas e seções usam a mesma barra lateral; o modal de criação deixa de existir.

## Restrições e Decisões

- Pessoas são distintas de membros e podem não ter e-mail, conta ou acesso.
- Quando um convite é aceito, uma Pessoa preexistente com o mesmo e-mail mantém seu nome e recebe o vínculo com a conta.
- Para um membro sem Pessoa preexistente, a Pessoa é criada com o nome obrigatório da conta.
- O formulário de tarefa reúne os campos existentes no modal e no editor. O formulário de seção exibe somente campos pertinentes à seção.

## Stack Técnica

Laravel/PHP, MySQL, Vue 3, TypeScript e Pinia existentes.

## Qualidade e Padrões

- Validação no servidor para nome obrigatório e fluxo de cadastro.
- Migration preserva pessoas cadastradas e dados de projeto.
- Build da SPA e verificação de migrations antes da entrega.
