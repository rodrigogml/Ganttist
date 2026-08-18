<script setup lang="ts">
import { ref, watch } from 'vue'

type Session = { id: string; current: boolean; user_agent: string | null; last_activity: number }

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: []; deleted: [] }>()
const sessions = ref<Session[]>([])
const loading = ref(false)
const actionError = ref('')
const deleting = ref(false)

function headers(): HeadersInit {
  const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content

  return { Accept: 'application/json', ...(token ? { 'X-CSRF-TOKEN': token } : {}) }
}

async function load(): Promise<void> {
  loading.value = true
  actionError.value = ''
  try {
    const response = await fetch('/api/v1/sessions', { headers: headers() })
    if (!response.ok) throw new Error('Não foi possível carregar suas sessões.')
    sessions.value = (await response.json()).data
  } catch (error) {
    actionError.value = error instanceof Error ? error.message : 'Não foi possível carregar suas sessões.'
  } finally {
    loading.value = false
  }
}

async function revoke(session: Session): Promise<void> {
  actionError.value = ''
  try {
    const response = await fetch(`/api/v1/sessions/${encodeURIComponent(session.id)}`, { method: 'DELETE', headers: headers() })
    if (!response.ok) throw new Error('Não foi possível revogar a sessão.')
    if (session.current) emit('deleted')
    else await load()
  } catch (error) {
    actionError.value = error instanceof Error ? error.message : 'Não foi possível revogar a sessão.'
  }
}

async function deleteAccount(): Promise<void> {
  if (!window.confirm('Excluir sua conta remove as sessões e dados associados. Esta ação não pode ser desfeita.')) return
  deleting.value = true
  actionError.value = ''
  try {
    const response = await fetch('/auth/account', { method: 'DELETE', headers: headers() })
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
      <section><p>Revogue somente dispositivos que você reconhece. A sessão atual encerra este acesso imediatamente.</p><p v-if="loading" class="account-muted">Carregando sessões…</p><ul v-else class="session-list"><li v-for="session in sessions" :key="session.id"><div><b>{{ session.current ? 'Este dispositivo' : (session.user_agent || 'Dispositivo desconhecido') }}</b><small>Ativa em {{ new Date(session.last_activity * 1000).toLocaleString('pt-BR') }}</small></div><button class="soft-btn" @click="revoke(session)">{{ session.current ? 'Sair' : 'Revogar' }}</button></li></ul><p v-if="actionError" class="account-error" role="alert">{{ actionError }}</p></section>
      <footer><button class="danger-btn" :disabled="deleting" @click="deleteAccount">{{ deleting ? 'Excluindo…' : 'Excluir minha conta' }}</button></footer>
    </aside>
  </div>
</template>
