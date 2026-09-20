<script setup lang="ts">
import { ref, watch } from 'vue'
import { apiFetch, connectivity } from './lib/api'
import { purgeOfflineUser } from './offline/offline-store'

type Session = { id: string; current: boolean; user_agent: string | null; last_activity: number }

const props = defineProps<{ open: boolean; userId: string }>()
const emit = defineEmits<{ close: []; deleted: [] }>()
const sessions = ref<Session[]>([])
const loading = ref(false)
const actionError = ref('')
const deleting = ref(false)
const clearingOffline = ref(false)
const actionNotice = ref('')

function headers(): HeadersInit {
  const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content

  return { Accept: 'application/json', ...(token ? { 'X-CSRF-TOKEN': token } : {}) }
}

async function load(): Promise<void> {
  loading.value = true
  actionError.value = ''
  actionNotice.value = ''
  try {
    const response = await apiFetch('/api/v1/sessions', { headers: headers() })
    if (!response.ok) throw new Error('Não foi possível carregar suas sessões.')
    sessions.value = (await response.json()).data
  } catch (error) {
    actionError.value = error instanceof Error ? error.message : 'Não foi possível carregar suas sessões.'
  } finally {
    loading.value = false
  }
}

async function clearOfflineData(): Promise<void> {
  if (!window.confirm('Remover todos os projetos e documentos preparados para uso offline neste navegador?')) return
  clearingOffline.value = true
  actionError.value = ''
  actionNotice.value = ''
  try {
    await purgeOfflineUser(props.userId)
    actionNotice.value = 'Todos os dados offline deste usuário foram removidos.'
  } catch (error) {
    actionError.value = error instanceof Error ? error.message : 'Não foi possível remover os dados offline.'
  } finally {
    clearingOffline.value = false
  }
}

async function revoke(session: Session): Promise<void> {
  if (!connectivity.online.value) return
  actionError.value = ''
  try {
    const response = await apiFetch(`/api/v1/sessions/${encodeURIComponent(session.id)}`, { method: 'DELETE', headers: headers() })
    if (!response.ok) throw new Error('Não foi possível revogar a sessão.')
    if (session.current) emit('deleted')
    else await load()
  } catch (error) {
    actionError.value = error instanceof Error ? error.message : 'Não foi possível revogar a sessão.'
  }
}

async function deleteAccount(): Promise<void> {
  if (!connectivity.online.value) return
  if (!window.confirm('Excluir sua conta remove as sessões e dados associados. Esta ação não pode ser desfeita.')) return
  deleting.value = true
  actionError.value = ''
  try {
    const response = await apiFetch('/auth/account', { method: 'DELETE', headers: headers() })
    if (!response.ok) throw new Error('Não foi possível excluir a conta.')
    emit('deleted')
  } catch (error) {
    actionError.value = error instanceof Error ? error.message : 'Não foi possível excluir a conta.'
  } finally {
    deleting.value = false
  }
}

watch(() => props.open, open => { if (open) load() })
</script>

<template>
  <div v-if="open" class="account-scrim" @click.self="emit('close')">
    <aside class="account-panel" role="dialog" aria-modal="true" aria-labelledby="account-panel-title">
      <header><div><span class="eyebrow">CONTA</span><h2 id="account-panel-title">Sessões e privacidade</h2></div><button aria-label="Fechar painel de conta" @click="emit('close')">×</button></header>
      <section><p>Revogue somente dispositivos que você reconhece. A sessão atual encerra este acesso imediatamente.</p><p v-if="loading" class="account-muted">Carregando sessões…</p><ul v-else class="session-list"><li v-for="session in sessions" :key="session.id"><div><b>{{ session.current ? 'Este dispositivo' : (session.user_agent || 'Dispositivo desconhecido') }}</b><small>Ativa em {{ new Date(session.last_activity * 1000).toLocaleString('pt-BR') }}</small></div><button class="soft-btn" :disabled="!connectivity.online.value" @click="revoke(session)">{{ session.current ? 'Sair' : 'Revogar' }}</button></li></ul><p v-if="actionNotice" class="account-muted" role="status">{{ actionNotice }}</p><p v-if="actionError" class="account-error" role="alert">{{ actionError }}</p></section>
      <footer><button class="soft-btn" :disabled="clearingOffline" @click="clearOfflineData">{{ clearingOffline ? 'Removendo…' : 'Limpar dados offline' }}</button><button class="danger-btn" :disabled="!connectivity.online.value || deleting" @click="deleteAccount">{{ deleting ? 'Excluindo…' : 'Excluir minha conta' }}</button></footer>
    </aside>
  </div>
</template>
