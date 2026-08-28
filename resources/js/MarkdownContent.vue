<script setup lang="ts">
import { computed } from "vue";
import DOMPurify from "dompurify";
import { marked } from "marked";

const props = defineProps<{ content: string | null | undefined }>();
const html = computed(() =>
    DOMPurify.sanitize(marked.parse(props.content ?? "", { async: false }) as string),
);
</script>

<template><div class="markdown-content" v-html="html"></div></template>
