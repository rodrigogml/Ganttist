<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthGate from '../AuthGate.vue'
import { connectivity } from '../lib/api'
import { useAuthStore } from '../stores/auth'
import { useWorkspaceStore } from '../stores/workspace'
import DocumentsWorkspace from './DocumentsWorkspace.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const workspace = useWorkspaceStore()
const projectId = computed(() => typeof route.params.id === 'string' ? route.params.id : '')

async function initialize(): Promise<void> {
  if (auth.loading) await auth.bootstrap()
  if (!auth.user || !projectId.value) return
  if (workspace.workspace?.project.id !== projectId.value) await workspace.load(projectId.value)
}

async function logout(): Promise<void> {
  await auth.logout()
  workspace.clearWorkspace()
  await router.replace('/')
}

watch(projectId, () => { void initialize() })
watch(() => auth.user?.id, () => { void initialize() })
onMounted(initialize)
</script>

<template>
  <main v-if="auth.loading" class="documents-page-state">Verificando seu acesso…</main>
  <AuthGate v-else-if="!auth.user" :auth="auth" />
  <main v-else-if="workspace.loading && workspace.workspace?.project.id !== projectId" class="documents-page-state">Carregando projeto…</main>
  <main v-else-if="workspace.error || !workspace.workspace" class="documents-page-state">
    <p>{{ workspace.error || 'Projeto indisponível.' }}</p>
    <button class="soft-btn" @click="router.push('/')">Voltar aos projetos</button>
  </main>
  <div v-else class="documents-page-shell">
    <header class="documents-project-bar">
      <button class="back" aria-label="Voltar aos projetos" @click="workspace.clearWorkspace(); router.push('/')">←</button>
      <div><small>PROJETO</small><b>{{ workspace.workspace.project.name }}</b></div>
      <nav aria-label="Áreas do projeto">
        <button @click="router.push(`/projects/${projectId}/gantt`)">Planejamento</button>
        <button class="active" aria-current="page">Documentos</button>
      </nav>
      <span v-if="!connectivity.online.value" class="offline-status">OFFLINE</span>
      <button class="avatar" :title="auth.user.email" @click="logout">{{ (auth.user.name || auth.user.email).slice(0, 2).toUpperCase() }}</button>
    </header>
    <DocumentsWorkspace :project-id="projectId" :user-id="auth.user.id" :role="workspace.workspace.project.role" />
  </div>
</template>

<style scoped>
.documents-page-shell{min-height:100vh;background:#f7f8fb}.documents-project-bar{height:64px;display:flex;align-items:center;gap:16px;padding:0 24px;border-bottom:1px solid #e1e4eb;background:#fff}.documents-project-bar .back{width:34px;height:34px;border:0;border-radius:8px;background:#eef0f4}.documents-project-bar>div{display:grid;min-width:180px}.documents-project-bar small{font-size:9px;color:#8a92a2;font-weight:800}.documents-project-bar b{font-size:13px}.documents-project-bar nav{display:flex;align-self:stretch;margin-left:18px}.documents-project-bar nav button{padding:0 18px;border:0;border-bottom:2px solid transparent;background:none;color:#6d7483;font-weight:700}.documents-project-bar nav button.active{border-color:#7566e8;color:#4033aa}.offline-status{margin-left:auto;padding:5px 8px;border-radius:99px;background:#fff2cc;color:#765817;font-size:10px;font-weight:800}.documents-project-bar .avatar{margin-left:auto;width:34px;height:34px;border:0;border-radius:50%;background:#7566e8;color:#fff;font-weight:800}.offline-status+.avatar{margin-left:0}.documents-page-state{min-height:100vh;display:grid;place-content:center;gap:12px;text-align:center}@media(max-width:700px){.documents-project-bar{padding:0 12px}.documents-project-bar>div{min-width:0}.documents-project-bar nav{margin-left:auto}.documents-project-bar nav button{padding:0 8px}.offline-status{display:none}}
</style>
