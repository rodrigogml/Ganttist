<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { features } from './lib/features'
import { apiFetch } from './lib/api'
import { connectivity } from './lib/api'
import { initializeAppearancePreferences, spacing, textScale, updateSpacing, updateTextScale, type Spacing, type TextScale } from './composables/useAppearancePreferences'
import { useAuthStore } from './stores/auth'
import { useWorkspaceStore } from './stores/workspace'

type ProjectArea = 'planning' | 'documents'
type PlanningView = 'tasks' | 'gantt'
type Project = { id: string; name: string }

const props = withDefaults(defineProps<{
  activeArea: ProjectArea
  planningView?: PlanningView
}>(), { planningView: 'gantt' })

const emit = defineEmits<{
  back: []
  account: []
  manageMembers: []
  notice: [message: string, kind: 'success' | 'error']
}>()

const auth = useAuthStore()
const workspace = useWorkspaceStore()
const router = useRouter()
const projectMenu = ref(false)
const projectLoading = ref(false)
const projects = ref<Project[]>([])
const projectSwitcher = ref<HTMLElement | null>(null)
const appearance = ref(false)
const settingsMenu = ref(false)
const appearanceWrap = ref<HTMLElement | null>(null)
const settingsWrap = ref<HTMLElement | null>(null)

function closeProjectMenuOnOutside(event: PointerEvent): void {
  const target = event.target as Node
  if (projectMenu.value && !projectSwitcher.value?.contains(target)) projectMenu.value = false
  if (appearance.value && !appearanceWrap.value?.contains(target)) appearance.value = false
  if (settingsMenu.value && !settingsWrap.value?.contains(target)) settingsMenu.value = false
}

async function toggleProjectMenu(): Promise<void> {
  projectMenu.value = !projectMenu.value
  if (!projectMenu.value || projects.value.length) return

  projectLoading.value = true
  try {
    const response = await apiFetch('/api/v1/projects', { headers: { Accept: 'application/json' } })
    if (!response.ok) throw new Error('Não foi possível carregar os projetos.')
    projects.value = (await response.json()).data
  } catch (error) {
    projectMenu.value = false
    emit('notice', error instanceof Error ? error.message : 'Não foi possível carregar projetos.', 'error')
  } finally {
    projectLoading.value = false
  }
}

async function switchProject(project: Project): Promise<void> {
  projectMenu.value = false
  try {
    await workspace.load(project.id)
    const view = props.activeArea === 'documents' ? 'documents' : props.planningView
    await router.push(`/projects/${project.id}/${view}`)
    emit('notice', `Projeto ${project.name} selecionado`, 'success')
  } catch (error) {
    emit('notice', error instanceof Error ? error.message : 'Não foi possível trocar o projeto.', 'error')
  }
}

function navigate(area: ProjectArea): void {
  const projectId = workspace.workspace?.project.id
  if (!projectId) return
  void router.push(`/projects/${projectId}/${area === 'documents' ? 'documents' : props.planningView}`)
}

onMounted(() => {
  initializeAppearancePreferences()
  document.addEventListener('pointerdown', closeProjectMenuOnOutside)
})
onBeforeUnmount(() => document.removeEventListener('pointerdown', closeProjectMenuOnOutside))
</script>

<template>
  <!-- Invariante de produto: esta é a única top bar autenticada. Não crie variantes por modo; apenas o conteúdo abaixo dela pode variar. -->
  <header class="topbar">
    <div class="brand">
      <span class="brand-mark"><img :src="'/brand/logo-square.png'" alt="" /></span><strong>Ganttist</strong>
    </div>
    <button type="button" class="project-dashboard-back" aria-label="Voltar para seus projetos" title="Voltar para seus projetos" @click="emit('back')">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m6-6-6 6 6 6" /></svg>
    </button>
    <div ref="projectSwitcher" class="project-switcher">
      <span class="eyebrow">PROJETO</span>
      <button :aria-expanded="projectMenu" aria-haspopup="listbox" @click="toggleProjectMenu">
        <span class="project-dot"></span>{{ workspace.workspace?.project.name || 'Carregando…' }}<span class="chevron">⌄</span>
      </button>
      <div v-if="projectMenu" class="project-menu" role="listbox" aria-label="Projetos">
        <span v-if="projectLoading">Carregando projetos…</span>
        <button v-for="project in projects" :key="project.id" role="option" :aria-selected="project.id === workspace.workspace?.project.id" @click="switchProject(project)">{{ project.name }}</button>
      </div>
    </div>
    <nav class="project-view-nav" aria-label="Áreas do projeto">
      <button :class="{ active: activeArea === 'planning' }" @click="navigate('planning')">Planejamento</button>
      <button v-if="features.documents" :class="{ active: activeArea === 'documents' }" @click="navigate('documents')">Documentos</button>
    </nav>
    <div class="top-actions">
      <div ref="appearanceWrap" class="appearance-wrap">
        <button class="icon-btn appearance-btn" aria-label="Aparência" title="Aparência" @click="appearance = !appearance">A<span>a</span></button>
        <div v-if="appearance" class="appearance-menu">
          <b>Aparência</b>
          <label>Tamanho do texto<select :value="textScale" @change="updateTextScale(($event.target as HTMLSelectElement).value as TextScale)"><option value="compact">Menor</option><option value="comfortable">Confortável</option><option value="large">Maior</option></select></label>
          <label>Espaçamento<select :value="spacing" @change="updateSpacing(($event.target as HTMLSelectElement).value as Spacing)"><option value="compact">Compacto</option><option value="comfortable">Confortável</option><option value="spacious">Espaçoso</option></select></label>
        </div>
      </div>
      <div ref="settingsWrap" class="settings-wrap">
        <button class="icon-btn settings-trigger" aria-label="Abrir configurações do projeto" title="Configurações" aria-haspopup="menu" :aria-expanded="settingsMenu" @click="settingsMenu = !settingsMenu"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15.25a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5Z" /><path d="M19.4 13.15c.05-.38.05-.77 0-1.15l1.74-1.35-1.8-3.12-2.04.82a8.2 8.2 0 0 0-1-.58L16 5.6h-3.6l-.3 2.17c-.35.16-.68.35-1 .58l-2.04-.82-1.8 3.12L9 12c-.05.38-.05.77 0 1.15L7.26 14.5l1.8 3.12 2.04-.82c.32.23.65.42 1 .58l.3 2.17H16l.3-2.17c.35-.16.68-.35 1-.58l2.04.82 1.8-3.12-1.74-1.35Z" /></svg></button>
        <div v-if="settingsMenu" class="settings-menu" role="menu" aria-label="Configurações do projeto"><button role="menuitem" :disabled="!connectivity.online.value" @click="settingsMenu = false; emit('manageMembers')"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" /></svg><span>Responsáveis</span></button></div>
      </div>
      <button class="avatar" aria-label="Abrir sessões e configurações da conta" @click="emit('account')">{{ (auth.user?.name || auth.user?.email || '').slice(0, 2).toUpperCase() }}</button>
    </div>
  </header>
</template>
