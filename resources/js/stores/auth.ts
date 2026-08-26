import { defineStore } from 'pinia'
import { ref } from 'vue'

type User = { id: string; name: string | null; email: string }
const sessionMarker = 'ganttist.authenticated-session'

function rememberActiveSession(): void {
  if (typeof sessionStorage !== 'undefined') sessionStorage.setItem(sessionMarker, '1')
}

function forgetActiveSession(): void {
  if (typeof sessionStorage !== 'undefined') sessionStorage.removeItem(sessionMarker)
}

function hadActiveSession(): boolean {
  return typeof sessionStorage !== 'undefined' && sessionStorage.getItem(sessionMarker) === '1'
}

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
  const loginEmail = ref('')
  const remember = ref(false)

  function expireSession(): void {
    forgetActiveSession()
    user.value = null
    loading.value = false
    sent.value = false
    error.value = 'Sua sessão expirou. Entre novamente para continuar.'
  }

  function handleUnauthorized(response: Response): boolean {
    if (response.status !== 401 && response.status !== 419) return false
    expireSession()
    return true
  }

  async function current(): Promise<boolean> {
    const response = await fetch('/api/v1/me', { headers: { Accept: 'application/json' } })
    if (!response.ok) {
      if ((response.status === 401 || response.status === 419) && hadActiveSession()) expireSession()
      return false
    }
    user.value = (await response.json()).user
    rememberActiveSession()
    return true
  }

  async function bootstrap() {
    try {
      const token = new URLSearchParams(window.location.search).get('token')
      if (token) {
        const response = await fetch('/auth/verify', { method: 'POST', headers: headers(), body: JSON.stringify({ token }) })
        if (response.ok) {
          user.value = (await response.json()).user
          rememberActiveSession()
          window.history.replaceState({}, document.title, window.location.pathname)
        } else error.value = 'Este link de acesso é inválido ou expirou.'
      }
      if (!user.value) await current()
    } catch { error.value = 'Não foi possível verificar sua sessão.' } finally { loading.value = false }
  }

  async function requestLink(email: string, name = '') {
    sending.value = true; error.value = ''
    try {
      const response = await fetch('/auth/request-link', { method: 'POST', headers: headers(), body: JSON.stringify({ email, name, remember: remember.value }) })
      if (!response.ok) {
        const body = await response.json().catch(() => null)
        const error = new Error(typeof body?.message === 'string' ? body.message : 'Não foi possível enviar o link. Tente novamente.')
        Object.assign(error, { registrationRequired: Boolean(body?.registrationRequired) })
        throw error
      }
      loginEmail.value = email.trim().toLowerCase(); sent.value = true
    } catch (exception) {
      error.value = exception instanceof Error ? exception.message : 'Não foi possível enviar o link.'
      if ((exception as Error & { registrationRequired?: boolean }).registrationRequired) throw exception
    } finally { sending.value = false }
  }

  async function verifyPin(pin: string) {
    sending.value = true; error.value = ''
    try {
      const response = await fetch('/auth/verify', { method: 'POST', headers: headers(), body: JSON.stringify({ email: loginEmail.value, pin }) })
      if (!response.ok) throw new Error('Código inválido ou expirado.')
      user.value = (await response.json()).user; sent.value = false; rememberActiveSession()
    } catch (exception) { error.value = exception instanceof Error ? exception.message : 'Não foi possível validar o código.' } finally { sending.value = false }
  }

  async function logout() {
    await fetch('/auth/logout', { method: 'POST', headers: headers() })
    forgetActiveSession(); user.value = null; sent.value = false
  }

  function resetSent() { sent.value = false }

  return { user, loading, sending, sent, error, remember, bootstrap, current, requestLink, verifyPin, logout, resetSent, expireSession, handleUnauthorized }
})
