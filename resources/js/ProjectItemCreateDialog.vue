<script setup lang="ts">
import { computed, ref } from 'vue'
import type { Collaborator, Task } from './types'

const props = defineProps<{ projectId: string; kind: 'task' | 'section'; items: Task[]; people: Collaborator[] }>()
const emit = defineEmits<{ close: []; created: [kind: 'task' | 'section'] }>()

const name = ref('')
const description = ref('')
const parentId = ref<string | null>(null)
const assigneePersonId = ref<string | null>(null)
const plannedStart = ref('')
const plannedFinish = ref('')
const actualCompletionDate = ref('')
const hierarchyQuery = ref('')
const saving = ref(false)
const error = ref('')

const csrfHeaders = (): Record<string, string> => {
  const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
  return token ? { 'X-CSRF-TOKEN': token } : {}
}
const normalizedQuery = computed(() => hierarchyQuery.value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('pt-BR').trim())
const sections = computed(() => props.items.filter((item) => item.kind === 'section'))
const sectionById = computed(() => new Map(sections.value.map((section) => [section.id, section])))
const matchingSectionIds = computed(() => {
  if (!normalizedQuery.value) return new Set(sections.value.map((section) => section.id))
  const visible = new Set<string>()
  for (const section of sections.value) {
    const normalizedTitle = section.title.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('pt-BR')
    if (!normalizedTitle.includes(normalizedQuery.value)) continue
    let current: Task | undefined = section
    while (current) {
      visible.add(current.id)
      current = current.parent_id ? sectionById.value.get(current.parent_id) : undefined
    }
  }
  return visible
})
const hierarchyOptions = computed(() => {
  const children = new Map<string | null, Task[]>()
  for (const section of sections.value) {
    const key = section.parent_id ?? null
    children.set(key, [...(children.get(key) ?? []), section])
  }
  const result: Task[] = []
  const visit = (parentId: string | null) => {
    for (const section of children.get(parentId) ?? []) {
      if (!matchingSectionIds.value.has(section.id)) continue
      result.push(section)
      visit(section.id)
    }
  }
  visit(null)
  return result
})
function highlightParts(title: string): string[] {
  const query = normalizedQuery.value
  if (!query) return [title]

  const characters = Array.from(title)
  const normalizedTitle = characters
    .map((character) => character.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('pt-BR'))
    .join('')
  const index = normalizedTitle.indexOf(query)

  if (index < 0) return [title]
  return [
    characters.slice(0, index).join(''),
    characters.slice(index, index + Array.from(query).length).join(''),
    characters.slice(index + Array.from(query).length).join(''),
  ]
}
async function submit() {
  if (!name.value.trim() || saving.value) return
  saving.value = true
  error.value = ''
  try {
    const task = props.kind === 'task'
    const response = await fetch(`/api/v1/projects/${props.projectId}/${task ? 'tasks' : 'sections'}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() },
      body: JSON.stringify(task ? {
        title: name.value.trim(), description: description.value.trim() || null, sectionId: parentId.value,
        assigneePersonId: assigneePersonId.value, plannedStart: plannedStart.value || null,
        plannedFinish: plannedFinish.value || null, actualCompletionDate: actualCompletionDate.value || null,
      } : { name: name.value.trim(), parentSectionId: parentId.value }),
    })
    if (!response.ok) {
      const body = await response.json().catch(() => null)
      throw new Error(typeof body?.message === 'string' ? body.message : `Não foi possível criar ${task ? 'a tarefa' : 'a seção'}.`)
    }
    emit('created', props.kind)
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Erro inesperado'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div class="item-create-scrim" @click.self="emit('close')">
      <form class="item-create-dialog" aria-modal="true" role="dialog" :aria-label="kind === 'task' ? 'Nova tarefa' : 'Nova seção'" @submit.prevent="submit">
        <header><div><p class="eyebrow">{{ kind === 'task' ? 'NOVA TAREFA' : 'NOVA SEÇÃO' }}</p><h2>{{ kind === 'task' ? 'Adicionar tarefa' : 'Adicionar seção' }}</h2></div><button class="drawer-close" type="button" aria-label="Fechar" @click="emit('close')">×</button></header>
        <main>
          <label>{{ kind === 'task' ? 'Título' : 'Nome da seção' }}<input v-model="name" autofocus :placeholder="kind === 'task' ? 'Ex.: Preparar entrega' : 'Ex.: Planejamento'"></label>
          <section class="hierarchy-field"><header><div><b>Posição na hierarquia</b><small>{{ parentId ? 'Dentro de uma seção' : 'Na raiz do projeto' }}</small></div><button v-if="parentId" type="button" @click="parentId = null">Limpar</button></header><input v-model="hierarchyQuery" class="hierarchy-search" placeholder="Filtrar seções…"><div class="hierarchy-options"><button type="button" class="hierarchy-option root" :class="{ selected: parentId === null }" @click="parentId = null"><span class="root-icon">⌂</span><span>Raiz do projeto</span><i v-if="parentId === null">✓</i></button><p v-if="!hierarchyOptions.length && hierarchyQuery" class="hierarchy-empty">Nenhuma seção encontrada.</p><button v-for="section in hierarchyOptions" :key="section.id" type="button" class="hierarchy-option" :class="{ selected: parentId === section.id }" :style="{ '--depth': section.level }" @click="parentId = section.id"><span class="tree-line">{{ section.level ? '↳' : '◦' }}</span><span><template v-for="(part, index) in highlightParts(section.title)" :key="index"><mark v-if="index === 1">{{ part }}</mark><template v-else>{{ part }}</template></template></span><i v-if="parentId === section.id">✓</i></button></div></section>
          <template v-if="kind === 'task'"><label>Descrição<textarea v-model="description" rows="3" placeholder="Contexto, orientações ou resultado esperado"></textarea></label><label>Responsável<select v-model="assigneePersonId"><option :value="null">Sem responsável</option><option v-for="person in people" :key="person.id" :value="person.id">{{ person.name }}</option></select></label><div class="date-grid"><label>Início planejado<input v-model="plannedStart" type="date"></label><label>Fim planejado<input v-model="plannedFinish" type="date"></label></div><label>Conclusão real <small>Preencha somente se ela já foi concluída.</small><input v-model="actualCompletionDate" type="date"></label></template>
          <p v-if="error" class="item-create-error">{{ error }}</p>
        </main>
        <footer><button class="soft-btn" type="button" @click="emit('close')">Cancelar</button><button class="primary" :disabled="saving || !name.trim()">{{ saving ? 'Criando…' : kind === 'task' ? 'Criar tarefa' : 'Criar seção' }}</button></footer>
      </form>
    </div>
  </Teleport>
</template>

<style scoped>
.item-create-scrim{position:fixed;inset:0;z-index:100;background:#10152866;backdrop-filter:blur(4px);display:grid;place-items:center;padding:24px}.item-create-dialog{width:min(100%,620px);max-height:min(760px,calc(100vh - 48px));overflow:hidden;border:1px solid #e5e6ef;border-radius:16px;background:#fff;box-shadow:0 28px 70px #11182745;display:flex;flex-direction:column}.item-create-dialog>header,.item-create-dialog>footer{display:flex;align-items:center;padding:20px 22px}.item-create-dialog>header{justify-content:space-between;border-bottom:1px solid #ececf2}.item-create-dialog h2{margin:0;font-size:20px}.item-create-dialog main{overflow:auto;padding:20px 22px;display:flex;flex-direction:column;gap:16px}.item-create-dialog label{display:flex;flex-direction:column;gap:7px;color:#586174;font-size:11px;font-weight:800}.item-create-dialog input,.item-create-dialog select,.item-create-dialog textarea{width:100%;border:1px solid #dfe2eb;border-radius:9px;background:#fff;color:#20283a;font:inherit;font-size:13px;padding:10px}.item-create-dialog input,.item-create-dialog select{height:40px}.item-create-dialog textarea{resize:vertical}.item-create-dialog label small{color:#9299a8;font-weight:500}.date-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.hierarchy-field{border:1px solid #e1def6;border-radius:12px;background:#fbfaff;overflow:hidden}.hierarchy-field>header{display:flex;justify-content:space-between;align-items:center;padding:13px 14px 10px}.hierarchy-field b,.hierarchy-field small{display:block}.hierarchy-field b{font-size:11px}.hierarchy-field small{margin-top:3px;color:#8c92a2;font-size:10px}.hierarchy-field header button{border:0;background:none;color:#6658cf;font-size:10px;font-weight:800}.hierarchy-search{margin:0 12px 10px;width:calc(100% - 24px)!important;background:#fff!important}.hierarchy-options{max-height:210px;overflow:auto;border-top:1px solid #eceafa;padding:6px}.hierarchy-option{width:100%;min-height:34px;border:0;border-radius:7px;background:transparent;color:#454d60;display:flex;align-items:center;gap:8px;padding:6px 8px;padding-left:calc(8px + var(--depth, 0) * 18px);text-align:left;font-size:11px}.hierarchy-option:hover{background:#f0eeff}.hierarchy-option.selected{background:#e9e5ff;color:#5646c5;font-weight:800}.hierarchy-option i{margin-left:auto;font-style:normal}.root-icon,.tree-line{width:14px;color:#8073d6;font-style:normal}.hierarchy-option mark{background:#ffe5a6;color:inherit;border-radius:2px;padding:0}.hierarchy-empty{padding:10px;color:#8a91a0;font-size:11px;text-align:center}.item-create-error{margin:0;color:#b54545;font-size:11px}.item-create-dialog>footer{justify-content:flex-end;gap:8px;border-top:1px solid #ececf2}@media(max-width:600px){.item-create-scrim{padding:12px}.item-create-dialog{max-height:calc(100vh - 24px)}.date-grid{grid-template-columns:1fr}}
</style>
