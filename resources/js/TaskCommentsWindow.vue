<script setup lang="ts">
import {
    computed,
    defineAsyncComponent,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
} from "vue";
import MarkdownContent from "./MarkdownContent.vue";
import type { Collaborator, Task, TaskComment } from "./types";

const RichMarkdownEditor = defineAsyncComponent(
    () => import("./RichMarkdownEditor.vue"),
);

const props = defineProps<{
    task: Task;
    projectId: string;
    people: Collaborator[];
    windowIndex: number;
    zIndex: number;
}>();
const emit = defineEmits<{
    close: [];
    focus: [];
    "comment-count-change": [count: number];
    notice: [message: string, kind: "success" | "error"];
}>();

const windowElement = ref<HTMLElement | null>(null);
const closeButton = ref<HTMLButtonElement | null>(null);
const layoutMenuElement = ref<HTMLElement | null>(null);
const comments = ref<TaskComment[]>([]);
const loading = ref(false);
const draft = ref("");
const editingId = ref<string | null>(null);
const editDraft = ref("");
const editBaseline = ref("");
const deleting = ref<TaskComment | null>(null);
const deletingInFlight = ref(false);
const closeConfirmation = ref(false);
const position = ref({ x: 0, y: 0 });
const size = ref({ width: 560, height: 680 });
const layoutMenu = ref(false);
const layoutPreset = ref<"left-half" | "right-half" | "right-third" | "maximized" | null>(null);
let drag: { pointerId: number; offsetX: number; offsetY: number } | null = null;
let resize: { pointerId: number; width: number; height: number; x: number; y: number } | null = null;

const isDirty = computed(
    () =>
        Boolean(draft.value.trim()) ||
        Boolean(editingId.value && editDraft.value !== editBaseline.value),
);
const windowStyle = computed(() => ({
    left: `${position.value.x}px`,
    top: `${position.value.y}px`,
    width: `${size.value.width}px`,
    height: `${size.value.height}px`,
    zIndex: props.zIndex,
}));
const countLabel = computed(
    () => `${comments.value.length} comentário${comments.value.length === 1 ? "" : "s"}`,
);

const csrfHeaders = (): Record<string, string> => {
    const token = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    )?.content;
    return token ? { "X-CSRF-TOKEN": token } : {};
};
async function responseError(response: Response, fallback: string): Promise<string> {
    const body = await response.json().catch(() => null);
    return body?.message ?? fallback;
}
function collaboratorName(comment: TaskComment) {
    return (
        comment.author_name ||
        props.people.find((person) => person.id === comment.author_id)?.name ||
        "Usuário"
    );
}
async function loadComments() {
    loading.value = true;
    try {
        const response = await fetch(
            `/api/v1/projects/${props.projectId}/tasks/${props.task.id}/context`,
            { headers: { Accept: "application/json" } },
        );
        if (!response.ok)
            throw new Error(
                await responseError(response, "Não foi possível carregar os comentários."),
            );
        const data = (await response.json()).data;
        comments.value = data.comments ?? [];
        emit("comment-count-change", comments.value.length);
    } catch (error) {
        emit(
            "notice",
            error instanceof Error
                ? error.message
                : "Não foi possível carregar os comentários.",
            "error",
        );
    } finally {
        loading.value = false;
    }
}
function beginEdit(comment: TaskComment) {
    if (
        editingId.value &&
        editDraft.value !== editBaseline.value &&
        editingId.value !== comment.id
    ) {
        emit("notice", "Salve ou cancele a edição atual antes de editar outro comentário.", "error");
        return;
    }
    editingId.value = comment.id;
    editDraft.value = comment.content;
    editBaseline.value = comment.content;
}
function cancelEdit() {
    editingId.value = null;
    editDraft.value = "";
    editBaseline.value = "";
}
async function publish() {
    const content = draft.value.trim();
    if (!content) return;
    const response = await fetch(
        `/api/v1/projects/${props.projectId}/tasks/${props.task.id}/comments`,
        {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                ...csrfHeaders(),
            },
            body: JSON.stringify({ content, commandId: crypto.randomUUID() }),
        },
    );
    if (!response.ok)
        throw new Error(
            await responseError(response, "Não foi possível publicar o comentário."),
        );
    draft.value = "";
    await loadComments();
}
async function saveEdit() {
    const commentId = editingId.value;
    const content = editDraft.value.trim();
    if (!commentId || !content) return;
    const response = await fetch(
        `/api/v1/projects/${props.projectId}/tasks/${props.task.id}/comments/${commentId}`,
        {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                ...csrfHeaders(),
            },
            body: JSON.stringify({ content, commandId: crypto.randomUUID() }),
        },
    );
    if (!response.ok)
        throw new Error(
            await responseError(response, "Não foi possível editar o comentário."),
        );
    cancelEdit();
    await loadComments();
}
async function deleteComment() {
    const comment = deleting.value;
    if (!comment || deletingInFlight.value) return;
    deletingInFlight.value = true;
    try {
        const response = await fetch(
            `/api/v1/projects/${props.projectId}/tasks/${props.task.id}/comments/${comment.id}`,
            { method: "DELETE", headers: { Accept: "application/json", ...csrfHeaders() } },
        );
        if (!response.ok)
            throw new Error(
                await responseError(response, "Não foi possível excluir o comentário."),
            );
        deleting.value = null;
        await loadComments();
    } finally {
        deletingInFlight.value = false;
    }
}
async function run(action: () => Promise<void>, successMessage?: string) {
    try {
        await action();
        if (successMessage) emit("notice", successMessage, "success");
    } catch (error) {
        emit(
            "notice",
            error instanceof Error ? error.message : "Não foi possível salvar o comentário.",
            "error",
        );
    }
}
function requestClose() {
    if (isDirty.value) closeConfirmation.value = true;
    else emit("close");
}
function clampRect(x: number, y: number, width: number, height: number) {
    const maxWidth = Math.max(1, window.innerWidth - 16);
    const maxHeight = Math.max(1, window.innerHeight - 16);
    const minWidth = Math.min(360, maxWidth);
    const minHeight = Math.min(420, maxHeight);
    const nextWidth = Math.min(Math.max(width, minWidth), maxWidth);
    const nextHeight = Math.min(Math.max(height, minHeight), maxHeight);
    return {
        x: Math.max(8, Math.min(x, window.innerWidth - nextWidth - 8)),
        y: Math.max(8, Math.min(y, window.innerHeight - nextHeight - 8)),
        width: nextWidth,
        height: nextHeight,
    };
}
function positionInitially() {
    const initial = clampRect(
        72 + props.windowIndex * 34,
        92 + props.windowIndex * 30,
        size.value.width,
        size.value.height,
    );
    position.value = { x: initial.x, y: initial.y };
    size.value = { width: initial.width, height: initial.height };
}
function applyLayout(preset: "left-half" | "right-half" | "right-third" | "maximized") {
    layoutPreset.value = preset;
    layoutMenu.value = false;
    const width = window.innerWidth;
    const height = window.innerHeight;
    if (preset === "maximized") {
        position.value = { x: 0, y: 0 };
        size.value = { width, height };
        return;
    }
    const fraction = preset === "right-third" ? 0.3 : 0.5;
    const paneWidth = Math.round(width * fraction);
    position.value = {
        x: preset === "left-half" ? 0 : width - paneWidth,
        y: 0,
    };
    size.value = { width: paneWidth, height };
}
function beginDrag(event: PointerEvent) {
    if (event.button !== 0 || window.innerWidth <= 720) return;
    emit("focus");
    layoutPreset.value = null;
    drag = {
        pointerId: event.pointerId,
        offsetX: event.clientX - position.value.x,
        offsetY: event.clientY - position.value.y,
    };
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
}
function moveDrag(event: PointerEvent) {
    if (!drag || event.pointerId !== drag.pointerId) return;
    const next = clampRect(
        event.clientX - drag.offsetX,
        event.clientY - drag.offsetY,
        size.value.width,
        size.value.height,
    );
    position.value = { x: next.x, y: next.y };
}
function endDrag(event: PointerEvent) {
    if (!drag || event.pointerId !== drag.pointerId) return;
    drag = null;
}
function beginResize(event: PointerEvent) {
    if (event.button !== 0 || window.innerWidth <= 720) return;
    emit("focus");
    layoutPreset.value = null;
    resize = {
        pointerId: event.pointerId,
        width: size.value.width,
        height: size.value.height,
        x: event.clientX,
        y: event.clientY,
    };
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
}
function moveResize(event: PointerEvent) {
    if (!resize || event.pointerId !== resize.pointerId) return;
    const next = clampRect(
        position.value.x,
        position.value.y,
        resize.width + event.clientX - resize.x,
        resize.height + event.clientY - resize.y,
    );
    position.value = { x: next.x, y: next.y };
    size.value = { width: next.width, height: next.height };
}
function endResize(event: PointerEvent) {
    if (!resize || event.pointerId !== resize.pointerId) return;
    resize = null;
}
function closeLayoutMenuOnOutside(event: PointerEvent) {
    if (
        layoutMenu.value &&
        event.target instanceof Node &&
        !layoutMenuElement.value?.contains(event.target)
    )
        layoutMenu.value = false;
}
function handleViewportResize() {
    if (layoutPreset.value) {
        applyLayout(layoutPreset.value);
        return;
    }
    const next = clampRect(
        position.value.x,
        position.value.y,
        size.value.width,
        size.value.height,
    );
    position.value = { x: next.x, y: next.y };
    size.value = { width: next.width, height: next.height };
}
function handleEscape() {
    if (layoutMenu.value) layoutMenu.value = false;
    else requestClose();
}

onMounted(() => {
    positionInitially();
    window.addEventListener("resize", handleViewportResize);
    document.addEventListener("pointerdown", closeLayoutMenuOnOutside);
    void loadComments();
    void nextTick(() => closeButton.value?.focus({ preventScroll: true }));
});
onBeforeUnmount(() => {
    window.removeEventListener("resize", handleViewportResize);
    document.removeEventListener("pointerdown", closeLayoutMenuOnOutside);
});
</script>

<template>
    <section
        ref="windowElement"
        class="comments-window"
        :style="windowStyle"
        role="dialog"
        aria-modal="false"
        :aria-labelledby="`comments-window-title-${task.id}`"
        tabindex="-1"
        @pointerdown="emit('focus')"
        @keydown.esc.prevent="handleEscape"
    >
        <header class="comments-window-header" @pointerdown="beginDrag" @pointermove="moveDrag" @pointerup="endDrag" @pointercancel="endDrag">
            <div class="comments-window-heading">
                <span class="comments-window-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 18.5 3.5 21l4.1-1.35A8.8 8.8 0 1 0 5 18.5Z" /><path d="M8 12h.01M12 12h.01M16 12h.01" /></svg></span>
                <div>
                    <span class="eyebrow">CONVERSA DA TAREFA</span>
                    <h2 :id="`comments-window-title-${task.id}`">{{ task.title }}</h2>
                </div>
            </div>
            <div ref="layoutMenuElement" class="comments-window-header-actions">
                <span class="comments-window-count" :title="countLabel">{{ comments.length }}</span>
                <button type="button" class="comments-window-layout-trigger" :aria-expanded="layoutMenu" aria-haspopup="menu" aria-label="Ajustar tamanho e posição" title="Ajustar tamanho e posição" @pointerdown.stop @click.stop="layoutMenu = !layoutMenu"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3H3v5m0-5h5M19 3h2v5m0-5h-5M5 21H3v-5m0 5h5m11 0h2v-5m0 5h-5" /></svg></button>
                <button ref="closeButton" type="button" aria-label="Fechar comentários" title="Fechar" @pointerdown.stop @click="requestClose">×</button>
                <div v-if="layoutMenu" class="comments-window-layout-menu" role="menu" aria-label="Ajustes rápidos da janela" @pointerdown.stop>
                    <button type="button" role="menuitem" @click="applyLayout('left-half')"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2" /><path class="layout-fill" d="M4 5h7v14H4z" /><path d="M12 4v16" /></svg><span>Metade esquerda</span></button>
                    <button type="button" role="menuitem" @click="applyLayout('right-half')"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2" /><path class="layout-fill" d="M13 5h7v14h-7z" /><path d="M12 4v16" /></svg><span>Metade direita</span></button>
                    <button type="button" role="menuitem" @click="applyLayout('right-third')"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2" /><path class="layout-fill" d="M16 5h4v14h-4z" /><path d="M15 4v16" /></svg><span>Terço direito</span></button>
                    <button type="button" role="menuitem" @click="applyLayout('maximized')"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3H3v5m0-5h5M16 3h5v5m0-5h-5M8 21H3v-5m0 5h5m8 0h5v-5m0 5h-5" /></svg><span>Maximizar</span></button>
                </div>
            </div>
        </header>

        <div class="comments-window-body">
            <p v-if="loading" class="comments-window-empty">Carregando comentários…</p>
            <p v-else-if="!comments.length" class="comments-window-empty">Ainda não há comentários nesta tarefa.</p>
            <article v-for="comment in comments" :key="comment.id" class="task-comment">
                <header>
                    <b>{{ collaboratorName(comment) }}</b>
                    <time v-if="comment.posted_at">{{ new Date(comment.posted_at).toLocaleString("pt-BR") }}</time>
                </header>
                <template v-if="editingId === comment.id">
                    <RichMarkdownEditor v-model="editDraft" ariaLabel="Editar comentário" placeholder="Edite o comentário…" />
                    <div class="comment-actions">
                        <button type="button" class="soft-btn" @click="cancelEdit">Cancelar</button>
                        <button type="button" class="primary" :disabled="!editDraft.trim()" @click="run(saveEdit, 'Comentário atualizado')">Salvar</button>
                    </div>
                </template>
                <template v-else>
                    <MarkdownContent :content="comment.content" />
                    <div v-if="comment.editable" class="comment-tools">
                        <button type="button" class="comment-icon-action" aria-label="Editar comentário" title="Editar comentário" @click="beginEdit(comment)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 16.5-.8 3.3 3.3-.8L18 8.5 15.5 6zM14.5 7l2.5 2.5" /></svg></button>
                        <button type="button" class="comment-icon-action danger" aria-label="Excluir comentário" title="Excluir comentário" @click="deleting = comment"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11h.01" /></svg></button>
                    </div>
                </template>
            </article>
        </div>

        <footer class="comments-window-composer">
            <label>Novo comentário<RichMarkdownEditor v-model="draft" ariaLabel="Novo comentário" placeholder="Escreva um comentário…" /></label>
            <button type="button" class="primary" :disabled="!draft.trim()" @click="run(publish, 'Comentário publicado')">Publicar comentário</button>
        </footer>

        <div v-if="closeConfirmation" class="comments-window-confirm">
            <section role="alertdialog" aria-modal="true" :aria-labelledby="`comments-close-title-${task.id}`">
                <b :id="`comments-close-title-${task.id}`">Descartar comentário não salvo?</b>
                <p>O texto em edição será perdido.</p>
                <div>
                    <button type="button" class="soft-btn" @click="closeConfirmation = false">Continuar editando</button>
                    <button type="button" class="danger-btn" @click="emit('close')">Descartar</button>
                </div>
            </section>
        </div>
        <div v-if="deleting" class="comments-window-confirm">
            <section role="alertdialog" aria-modal="true" :aria-labelledby="`comments-delete-title-${task.id}`">
                <b :id="`comments-delete-title-${task.id}`">Excluir comentário?</b>
                <p>Esta ação não poderá ser desfeita.</p>
                <div>
                    <button type="button" class="soft-btn" :disabled="deletingInFlight" @click="deleting = null">Cancelar</button>
                    <button type="button" class="danger-btn" :disabled="deletingInFlight" @click="run(deleteComment, 'Comentário excluído')">{{ deletingInFlight ? "Excluindo…" : "Excluir" }}</button>
                </div>
            </section>
        </div>
        <button type="button" class="comments-window-resizer" aria-label="Redimensionar janela" title="Arraste para redimensionar" @pointerdown.stop="beginResize" @pointermove="moveResize" @pointerup="endResize" @pointercancel="endResize"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 21 12-12M15 21l6-6M21 15l-6 6" /></svg></button>
    </section>
</template>
