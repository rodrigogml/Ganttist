# Convenções de interface

## Ação padrão de formulários

Todo formulário novo de criação ou edição deve definir uma única ação de confirmação padrão:

```vue
<form v-default-form @submit.prevent="save">
  <!-- campos -->
  <DefaultSubmitButton type="submit">Salvar</DefaultSubmitButton>
</form>
```

Para um painel que não pode usar um elemento `form`, aplique `v-default-form` ao contêiner do painel e use `DefaultSubmitButton` com o `@click` da ação. A diretiva centraliza `Ctrl+Enter` (e `Cmd+Enter` no macOS), respeita botão desabilitado, composição de texto e eventos já tratados.

Use esse padrão apenas para persistir ou publicar o rascunho do escopo que contém o foco. Não o use em navegação, abertura de menus, ações destrutivas ou diálogos de confirmação. Editores com atalho próprio podem declarar `data-default-form-shortcut-ignore` em sua raiz; documente o conflito junto ao componente.

O estilo da ação padrão é `DefaultSubmitButton`. A classe `.primary` continua reservada para comandos primários que não confirmam um formulário. Ambos devem usar `--button-primary-gradient` e `--button-primary-shadow`; não introduza outro roxo para uma ação primária.

## Tokens de botões

As cores de controles interativos são centralizadas em `resources/css/app.css`: `--button-primary-*` para confirmação/criação, `--button-secondary-*` para ações neutras, `--button-danger-*` para remoção e `--button-accent-*` para estados selecionados, ferramentas e ações auxiliares. Não use hexadecimais roxos em regras de botão; escolha o papel semântico correspondente. `--action-primary-*` permanece apenas como alias de compatibilidade.
