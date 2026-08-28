<script setup lang="ts">
import { computed, onBeforeUnmount, watch } from "vue";
import { EditorContent, useEditor } from "@tiptap/vue-3";
import StarterKit from "@tiptap/starter-kit";
import Link from "@tiptap/extension-link";
import { Markdown } from "@tiptap/markdown";
import { UnderlineWithMarkdownInput } from "./extensions/UnderlineWithMarkdownInput";

const props = withDefaults(
    defineProps<{
        modelValue: string | null | undefined;
        placeholder?: string;
        ariaLabel: string;
        compact?: boolean;
    }>(),
    { placeholder: "Escreva em Markdown…", compact: false },
);
const emit = defineEmits<{ "update:modelValue": [value: string] }>();

const editor = useEditor({
    content: props.modelValue ?? "",
    contentType: "markdown",
    extensions: [
        StarterKit.configure({
            heading: { levels: [1, 2, 3, 4] },
            link: false,
            underline: false,
        }),
        Link.configure({ openOnClick: false, autolink: true, linkOnPaste: true }),
        UnderlineWithMarkdownInput,
        Markdown,
    ],
    editorProps: {
        attributes: {
            class: "rich-markdown-content",
            "data-placeholder": props.placeholder,
            "aria-label": props.ariaLabel,
        },
    },
    onUpdate: ({ editor: instance }) => {
        emit("update:modelValue", instance.getMarkdown());
    },
});

watch(
    () => props.modelValue,
    (value) => {
        const instance = editor.value;
        const markdown = value ?? "";
        if (instance && instance.getMarkdown() !== markdown)
            instance.commands.setContent(markdown, {
                contentType: "markdown",
                emitUpdate: false,
            });
    },
);
onBeforeUnmount(() => editor.value?.destroy());

const isHeading = (level: 1 | 2 | 3 | 4) =>
    editor.value?.isActive("heading", { level }) ?? false;
const canUndo = computed(() => editor.value?.can().undo() ?? false);
const canRedo = computed(() => editor.value?.can().redo() ?? false);

function command(action: () => void) {
    action();
    editor.value?.commands.focus();
}
function setLink() {
    const instance = editor.value;
    if (!instance) return;
    const previous = instance.getAttributes("link").href as string | undefined;
    const href = globalThis.prompt("Endereço do link", previous ?? "");
    if (href === null) return;
    command(() => {
        if (!href.trim()) instance.chain().focus().unsetLink().run();
        else instance.chain().focus().setLink({ href: href.trim() }).run();
    });
}
</script>

<template>
    <div class="rich-markdown" :class="{ compact }">
        <div class="rich-markdown-toolbar" role="toolbar" aria-label="Formatação Markdown">
            <button type="button" :class="{ active: editor?.isActive('paragraph') }" :aria-pressed="editor?.isActive('paragraph')" aria-label="Texto normal" title="Texto normal" @click="command(() => editor?.chain().focus().setParagraph().run())">¶</button>
            <button v-for="level in [1, 2, 3, 4] as const" :key="level" type="button" :class="{ active: isHeading(level) }" :aria-pressed="isHeading(level)" :aria-label="`Título ${level}`" :title="`Título ${level} — #${'#'.repeat(level - 1)} `" @click="command(() => editor?.chain().focus().toggleHeading({ level }).run())">H{{ level }}</button>
            <span class="rich-markdown-divider" aria-hidden="true"></span>
            <button type="button" :class="{ active: editor?.isActive('bold') }" :aria-pressed="editor?.isActive('bold')" aria-label="Negrito" title="Negrito — Ctrl+B / **texto**" @click="command(() => editor?.chain().focus().toggleBold().run())"><b>B</b></button>
            <button type="button" :class="{ active: editor?.isActive('italic') }" :aria-pressed="editor?.isActive('italic')" aria-label="Itálico" title="Itálico — Ctrl+I / *texto*" @click="command(() => editor?.chain().focus().toggleItalic().run())"><i>I</i></button>
            <button type="button" :class="{ active: editor?.isActive('underline') }" :aria-pressed="editor?.isActive('underline')" aria-label="Sublinhado" title="Sublinhado — Ctrl+U / ++texto++" @click="command(() => editor?.chain().focus().toggleUnderline().run())"><u>U</u></button>
            <button type="button" :class="{ active: editor?.isActive('strike') }" :aria-pressed="editor?.isActive('strike')" aria-label="Tachado" title="Tachado — ~~texto~~" @click="command(() => editor?.chain().focus().toggleStrike().run())"><s>S</s></button>
            <span class="rich-markdown-divider" aria-hidden="true"></span>
            <button type="button" :class="{ active: editor?.isActive('bulletList') }" :aria-pressed="editor?.isActive('bulletList')" aria-label="Lista com marcadores" title="Lista com marcadores — * " @click="command(() => editor?.chain().focus().toggleBulletList().run())">•</button>
            <button type="button" :class="{ active: editor?.isActive('orderedList') }" :aria-pressed="editor?.isActive('orderedList')" aria-label="Lista numerada" title="Lista numerada — 1. " @click="command(() => editor?.chain().focus().toggleOrderedList().run())">1.</button>
            <button type="button" :class="{ active: editor?.isActive('link') }" :aria-pressed="editor?.isActive('link')" aria-label="Inserir link" title="Inserir link" @click="setLink">↗</button>
            <span class="rich-markdown-divider" aria-hidden="true"></span>
            <button type="button" aria-label="Desfazer" title="Desfazer — Ctrl+Z" :disabled="!canUndo" @click="command(() => editor?.chain().focus().undo().run())">↶</button>
            <button type="button" aria-label="Refazer" title="Refazer — Ctrl+Shift+Z" :disabled="!canRedo" @click="command(() => editor?.chain().focus().redo().run())">↷</button>
        </div>
        <EditorContent :editor="editor" />
        <small class="rich-markdown-help">Markdown: # título · * lista · **negrito** · *itálico* · ++sublinhado++</small>
    </div>
</template>
