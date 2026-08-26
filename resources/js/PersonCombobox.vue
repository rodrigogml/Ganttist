<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import type { Collaborator } from './types'

const props = defineProps<{ modelValue: string | null | undefined; people: Collaborator[] }>()
const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>()
const query = ref('')
const open = ref(false)
const container = ref<HTMLElement | null>(null)
const options = computed(() => {
  const search = query.value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('pt-BR').trim()
  return !search ? props.people : props.people.filter((person) => person.name.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('pt-BR').includes(search))
})
function choose(id: string | null, name = '') { emit('update:modelValue', id); query.value = name; open.value = false }
function focus() { query.value = ''; open.value = true }
function search(event: Event) { query.value = (event.target as HTMLInputElement).value; open.value = true }
function closeWhenOutside(event: PointerEvent) { if (container.value && !container.value.contains(event.target as Node)) open.value = false }
function closeWhenFocusLeaves(event: FocusEvent) { if (!container.value?.contains(event.relatedTarget as Node | null)) open.value = false }
onMounted(() => document.addEventListener('pointerdown', closeWhenOutside))
onBeforeUnmount(() => document.removeEventListener('pointerdown', closeWhenOutside))
</script>

<template>
  <div ref="container" class="combo" @focusout="closeWhenFocusLeaves"><input :value="query || (modelValue ? people.find((person) => person.id === modelValue)?.name : 'Sem responsável')" role="combobox" aria-autocomplete="list" :aria-expanded="open" placeholder="Buscar responsável…" @focus="focus" @input="search" @blur="setTimeout(() => (open = false), 150)"><div v-if="open" class="combo-options" role="listbox"><button type="button" role="option" :aria-selected="modelValue === null" @mousedown.prevent="choose(null, 'Sem responsável')">Sem responsável</button><button v-for="person in options" :key="person.id" type="button" role="option" :aria-selected="modelValue === person.id" @mousedown.prevent="choose(person.id, person.name)">{{ person.name }}</button><p v-if="!options.length">Nenhuma pessoa encontrada.</p></div></div>
</template>

<style scoped>
.combo{position:relative}.combo input{width:100%}.combo-options{position:absolute;z-index:90;left:0;right:0;top:calc(100% + 4px);max-height:240px;overflow:auto;background:#fff;border:1px solid #dfe2e9;border-radius:8px;box-shadow:0 10px 25px #10152622;padding:4px}.combo-options button{display:block;width:100%;min-height:34px;padding:6px 8px;border:0;border-radius:6px;background:transparent;color:#30394c;text-align:left;font:inherit}.combo-options button:hover,.combo-options button[aria-selected=true]{background:#efedff;color:#5e50cf}.combo-options p{margin:8px;color:#7d8599;font-size:11px;text-align:center}
</style>
