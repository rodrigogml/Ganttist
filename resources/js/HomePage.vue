<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AuthGate from './AuthGate.vue'
import ProjectDashboard from './ProjectDashboard.vue'
import { useAuthStore } from './stores/auth'
import { useWorkspaceStore } from './stores/workspace'

const auth = useAuthStore()
const workspace = useWorkspaceStore()
const router = useRouter()

async function openProject(projectId: string): Promise<void> {
  await workspace.load(projectId)
  if (workspace.workspace?.project.id === projectId) await router.push(`/projects/${projectId}/gantt`)
}

onMounted(() => { if (auth.loading) void auth.bootstrap() })
</script>

<template>
  <main v-if="auth.loading" class="loading">
    <div class="loader-logo"><img :src="'/brand/logo-square.png'" alt=""></div>
    <p>Verificando seu acesso…</p>
  </main>
  <AuthGate v-else-if="!auth.user" :auth="auth" />
  <ProjectDashboard
    v-else
    :opening="workspace.loading"
    :open-error="workspace.error"
    :user-id="auth.user.id"
    @open="openProject"
  />
</template>
