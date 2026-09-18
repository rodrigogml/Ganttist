<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue";
import { mountTaskTable, type MountedTaskTable, type TaskTableDocument } from "./task-table/univer-adapter";

const props = withDefaults(defineProps<{ document: TaskTableDocument; readOnly?: boolean }>(), { readOnly: false });
const host = ref<HTMLElement | null>(null);
const loading = ref(true);
const error = ref("");
let table: MountedTaskTable | null = null;

onMounted(async () => {
    try {
        if (host.value) table = await mountTaskTable(host.value, props.document, props.readOnly);
    } catch (cause) {
        error.value = cause instanceof Error ? cause.message : "Não foi possível iniciar a tabela.";
    } finally {
        loading.value = false;
    }
});
onBeforeUnmount(() => table?.dispose());
defineExpose({ snapshot: () => table?.snapshot() ?? props.document });
</script>

<template>
    <div class="task-table-editor" :aria-busy="loading">
        <p v-if="!readOnly" class="task-table-editor-limits">Máximo: 200 linhas, 100 colunas e 500 KB. Uma aba por tabela.</p>
        <p v-if="loading" class="task-table-editor-status">Carregando editor de tabela…</p>
        <p v-else-if="error" role="alert" class="task-table-editor-status">{{ error }}</p>
        <div ref="host" class="task-table-editor-host" />
    </div>
</template>

<style scoped>
.task-table-editor { min-height: 360px; position: relative; }
.task-table-editor-host { height: 420px; min-height: 360px; overflow: hidden; border: 1px solid #d6dce5; border-radius: 8px; background: white; }
.task-table-editor-status { padding: 12px; color: #556070; }
.task-table-editor-limits { margin: 0 0 8px; color: #556070; font-size: .82rem; }
</style>
