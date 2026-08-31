<script setup lang="ts">
import { computed } from 'vue'
import AppCombobox from './AppCombobox.vue'
import type { Collaborator } from './types'

const props = defineProps<{ modelValue: string | null | undefined; people: Collaborator[] }>()
const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>()
const options = computed(() => props.people.map((person) => ({ id: person.id, label: person.name, description: person.email })))
</script>

<template>
  <AppCombobox :model-value="modelValue" :options="options" placeholder="Buscar responsável…" empty-label="Sem responsável" :allow-empty="true" empty-message="Nenhuma pessoa encontrada." @update:model-value="emit('update:modelValue', $event)" />
</template>
