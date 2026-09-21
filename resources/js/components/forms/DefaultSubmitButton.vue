<script setup lang="ts">
withDefaults(defineProps<{ type?: "button" | "submit" | "reset" }>(), { type: "button" });
</script>

<template>
    <button :type="type" class="form-default-button" data-default-submit aria-keyshortcuts="Control+Enter Meta+Enter" title="Confirmar com Ctrl+Enter"><slot /></button>
</template>
