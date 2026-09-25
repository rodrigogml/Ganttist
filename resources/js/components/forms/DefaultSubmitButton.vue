<script setup lang="ts">
withDefaults(defineProps<{ type?: "button" | "submit" | "reset"; defaultAction?: boolean }>(), { type: "button", defaultAction: true });
</script>

<template>
    <button v-if="defaultAction" :type="type" class="primary form-default-button" data-default-submit aria-keyshortcuts="Control+Enter Meta+Enter" title="Confirmar com Ctrl+Enter"><slot /></button>
    <button v-else :type="type" class="primary form-default-button"><slot /></button>
</template>
