<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthGate from '../AuthGate.vue'
import AccountPanel from '../AccountPanel.vue'
import ProjectMembersPanel from '../ProjectMembersPanel.vue'
import ProjectTopBar from '../ProjectTopBar.vue'
import { useAuthStore } from '../stores/auth'
import { useWorkspaceStore } from '../stores/workspace'
import DocumentsWorkspace from './DocumentsWorkspace.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const workspace = useWorkspaceStore()
const account = ref(false)
const responsiblePanel = ref(false)
const projectId = computed(() => typeof route.params.id === 'string' ? route.params.id : '')

async function initialize(): Promise<void> {
  if (auth.loading) await auth.bootstrap()
  if (!auth.user || !projectId.value) return
  if (workspace.workspace?.project.id !== projectId.value) await workspace.load(projectId.value)
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
    <ProjectTopBar active-area="documents" @back="workspace.clearWorkspace(); router.push('/')" @account="account = true" @manage-members="responsiblePanel = true" />
    <DocumentsWorkspace :project-id="projectId" :user-id="auth.user.id" :role="workspace.workspace.project.role" />
    <AccountPanel :open="account" :user-id="auth.user.id" @close="account = false" @deleted="account = false; auth.logout(); workspace.clearWorkspace(); router.replace('/')" />
    <ProjectMembersPanel v-if="responsiblePanel" :project-id="workspace.workspace.project.id" :role="workspace.workspace.project.role ?? 'reader'" @close="responsiblePanel = false" @people-changed="workspace.load()" />
  </div>
</template>

<style scoped>
.documents-page-shell{min-height:100vh;background:#f7f8fb}.documents-page-state{min-height:100vh;display:grid;place-content:center;gap:12px;text-align:center}
</style>
