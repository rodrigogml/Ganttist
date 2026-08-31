<script setup lang="ts">
import { computed } from 'vue'
import AppCombobox from './AppCombobox.vue'
import type { Task } from './types'

const props = defineProps<{ modelValue: string | null | undefined; items: Task[]; excludeId?: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>()
const options = computed(() => {
  const sections = props.items.filter((item) => item.kind === 'section' && item.id !== props.excludeId)
  return sections.map((section) => ({
    id: section.id,
    label: section.title,
    depth: section.level,
  }))
})
</script>

<template>
  <AppCombobox :model-value="modelValue" :options="options" placeholder="Buscar seção…" empty-label="Raiz do projeto" :allow-empty="true" empty-message="Nenhuma seção encontrada." @update:model-value="emit('update:modelValue', $event)" />
</template>
