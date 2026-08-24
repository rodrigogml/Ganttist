<script setup lang="ts">
import { onMounted, ref } from 'vue'

type Project = { id: string; name: string }
const emit = defineEmits<{ ready: [] }>()
const connected = ref(false), integrationStatus = ref('disconnected'), projects = ref<Project[]>([]), selected = ref(''), loading = ref(true), saving = ref(false), error = ref('')

async function load() {
  const callbackStatus = new URLSearchParams(window.location.search).get('todoist')
  if (callbackStatus === 'authorization_failed') error.value = 'Não foi possível concluir a autorização do Todoist. Tente conectar novamente.'
  else if (callbackStatus === 'authorization_expired') error.value = 'A autorização do Todoist expirou ou já foi utilizada. Inicie uma nova conexão.'
  else if (callbackStatus === 'authorization_cancelled') error.value = 'A autorização do Todoist foi cancelada.'
  if (callbackStatus) window.history.replaceState({}, document.title, window.location.pathname)
  try {
    const response = await fetch('/api/v1/todoist/status', { headers: { Accept: 'application/json' } })
    if (!response.ok) throw new Error('Não foi possível carregar a configuração do Todoist.')
    const status = await response.json()
    connected.value = status.connected; integrationStatus.value = status.integration_status
    if (connected.value && !status.project) projects.value = (await fetch('/api/v1/todoist/projects', { headers: { Accept: 'application/json' } }).then(response => response.json())).data
    else if (connected.value && status.project) emit('ready')
  } catch { error.value = 'Não foi possível carregar a configuração do Todoist.' } finally { loading.value = false }
}
async function selectProject() {
  const project = projects.value.find(item => item.id === selected.value)
  if (!project) return
  saving.value = true; error.value = ''
  try {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
    const response = await fetch('/api/v1/todoist/project', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) }, body: JSON.stringify({ todoist_project_id: project.id, commandId: crypto.randomUUID() }) })
    if (!response.ok) throw new Error('Não foi possível selecionar o projeto.')
    emit('ready')
  } catch (exception) { error.value = exception instanceof Error ? exception.message : 'Não foi possível selecionar o projeto.' } finally { saving.value = false }
}
async function disconnect() {
  const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
  await fetch('/api/v1/todoist/integration', { method: 'DELETE', headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) }, body: JSON.stringify({ commandId: crypto.randomUUID() }) })
  connected.value = false; projects.value = []; selected.value = ''
}
onMounted(load)
</script>

<template>
  <main class="auth-page"><section class="auth-card">
    <div class="auth-mark">G</div><p class="eyebrow">CONFIGURAÇÃO INICIAL</p><h1>Conecte o Todoist</h1>
    <p v-if="loading" class="auth-copy">Carregando sua configuração…</p>
    <template v-else-if="!connected"><p class="auth-copy">{{integrationStatus==='reauthorization_required'?'O acesso ao Todoist expirou ou foi revogado. Conecte novamente para retomar a sincronização.':'O Ganttist usa o Todoist como fonte das tarefas. Autorize o acesso para começar.'}}</p><a class="auth-submit auth-link" href="/oauth/todoist/redirect">{{integrationStatus==='reauthorization_required'?'Reconectar minha conta Todoist':'Conectar minha conta Todoist'}}</a></template>
    <template v-else><p class="auth-copy">Escolha o projeto que será exibido no seu primeiro gráfico Gantt.</p><label class="auth-label">Projeto<select v-model="selected"><option value="" disabled>Selecione um projeto</option><option v-for="project in projects" :key="project.id" :value="project.id">{{project.name}}</option></select></label><button class="auth-submit" :disabled="saving||!selected" @click="selectProject">{{saving?'Salvando…':'Usar este projeto'}}</button><button class="auth-secondary" @click="disconnect">Desconectar Todoist</button></template>
    <p v-if="error" class="auth-error">{{error}}</p>
  </section></main>
</template>
