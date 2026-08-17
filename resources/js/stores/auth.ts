import { defineStore } from 'pinia'
import { ref } from 'vue'

type User = { id: string; name: string | null; email: string }

function headers(): HeadersInit {
  const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
  return { 'Content-Type': 'application/json', Accept: 'application/json', ...(token ? { 'X-CSRF-TOKEN': token } : {}) }
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(true)
  const sending = ref(false)
  const sent = ref(false)
  const error = ref('')

  async function current(): Promise<boolean> {
    const response = await fetch('/api/v1/me', { headers: { Accept: 'application/json' } })
    if (!response.ok) return false
    user.value = (await response.json()).user
    return true
  }

  async function bootstrap() {
    try {
      const token = new URLSearchParams(window.location.search).get('token')
      if (token) {
        const response = await fetch('/auth/verify', { method: 'POST', headers: headers(), body: JSON.stringify({ token }) })
        if (response.ok) {
          user.value = (await response.json()).user
          window.history.replaceState({}, document.title, window.location.pathname)
        } else error.value = 'Este link de acesso é inválido ou expirou.'
      }
      if (!user.value) await current()
    } catch { error.value = 'Não foi possível verificar sua sessão.' } finally { loading.value = false }
  }

  async function requestLink(email: string) {
    sending.value = true; error.value = ''
    try {
      const response = await fetch('/auth/request-link', { method: 'POST', headers: headers(), body: JSON.stringify({ email }) })
      if (!response.ok) throw new Error('Não foi possível enviar o link. Tente novamente.')
      sent.value = true
    } catch (exception) { error.value = exception instanceof Error ? exception.message : 'Não foi possível enviar o link.' } finally { sending.value = false }
  }

  async function logout() {
    await fetch('/auth/logout', { method: 'POST', headers: headers() })
    user.value = null; sent.value = false
  }

  function resetSent() { sent.value = false }

  return { user, loading, sending, sent, error, bootstrap, requestLink, logout, resetSent }
})
