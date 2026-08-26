<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import type { Task } from './types'

const props = defineProps<{ modelValue: string | null | undefined; items: Task[]; excludeId?: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>()
const query = ref('')
const open = ref(false)
const container = ref<HTMLElement | null>(null)
const normalize = (value: string) => value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('pt-BR')
const options = computed(() => {
  const sections = props.items.filter((item) => item.kind === 'section' && item.id !== props.excludeId)
  const byId = new Map(sections.map((section) => [section.id, section]))
  const search = normalize(query.value.trim())
  if (!search) return sections
  const visible = new Set(sections.filter((section) => normalize(section.title).includes(search)).map((section) => section.id))
  for (const id of [...visible]) {
    let current = byId.get(id)
    while (current?.parent_id) {
      visible.add(current.parent_id)
      current = byId.get(current.parent_id)
    }
  }
  return sections.filter((section) => visible.has(section.id))
})
function choose(id: string | null, title = '') { emit('update:modelValue', id); query.value = title; open.value = false }
function focus() { query.value = ''; open.value = true }
function search(event: Event) { query.value = (event.target as HTMLInputElement).value; open.value = true }
function closeWhenOutside(event: PointerEvent) { if (container.value && !container.value.contains(event.target as Node)) open.value = false }
function closeWhenFocusLeaves(event: FocusEvent) { if (!container.value?.contains(event.relatedTarget as Node | null)) open.value = false }
onMounted(() => document.addEventListener('pointerdown', closeWhenOutside))
onBeforeUnmount(() => document.removeEventListener('pointerdown', closeWhenOutside))
</script>

<template>
  <div ref="container" class="combo" @focusout="closeWhenFocusLeaves">
    <input :value="query || (modelValue ? items.find((item) => item.id === modelValue)?.title : 'Raiz do projeto')" role="combobox" aria-autocomplete="list" :aria-expanded="open" placeholder="Buscar seção…" @focus="focus" @input="search" @blur="setTimeout(() => (open = false), 150)">
    <div v-if="open" class="combo-options" role="listbox"><button type="button" role="option" :aria-selected="modelValue === null" @mousedown.prevent="choose(null, 'Raiz do projeto')">⌂ Raiz do projeto</button><button v-for="section in options" :key="section.id" type="button" role="option" :aria-selected="modelValue === section.id" :style="{ '--depth': section.level }" @mousedown.prevent="choose(section.id, section.title)"><span>{{ section.level ? '↳' : '◦' }}</span>{{ section.title }}</button><p v-if="!options.length">Nenhuma seção encontrada.</p></div>
  </div>
</template>

<style scoped>
.combo{position:relative}.combo input{width:100%}.combo-options{position:absolute;z-index:90;left:0;right:0;top:calc(100% + 4px);max-height:240px;overflow:auto;background:#fff;border:1px solid #dfe2e9;border-radius:8px;box-shadow:0 10px 25px #10152622;padding:4px}.combo-options button{display:flex;width:100%;min-height:34px;align-items:center;gap:7px;padding:6px 8px;padding-left:calc(8px + var(--depth, 0) * 16px);border:0;border-radius:6px;background:transparent;color:#30394c;text-align:left;font:inherit}.combo-options button:hover,.combo-options button[aria-selected=true]{background:#efedff;color:#5e50cf}.combo-options p{margin:8px;color:#7d8599;font-size:11px;text-align:center}
</style>
