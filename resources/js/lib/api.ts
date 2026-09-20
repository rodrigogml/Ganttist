import { readonly, ref } from 'vue'

export class OfflineMutationError extends Error {
  constructor() { super('Você está offline. Alterações exigem conexão com o servidor.'); this.name = 'OfflineMutationError' }
}

const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine)
let responseHandler: ((response: Response) => void | Promise<void>) | null = null
if (typeof window !== 'undefined') {
  window.addEventListener('online', () => { online.value = true })
  window.addEventListener('offline', () => { online.value = false })
}

export const connectivity = { online: readonly(online), markOnline: () => { online.value = true }, markOffline: () => { online.value = false } }

export function setApiResponseHandler(handler: ((response: Response) => void | Promise<void>) | null): void {
  responseHandler = handler
}

export function csrfHeaders(): Record<string, string> {
  const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
  return token ? { 'X-CSRF-TOKEN': token } : {}
}

export function assertOnlineMutation(): void {
  if (!online.value) throw new OfflineMutationError()
}

export async function apiFetch(input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> {
  const method = (init.method ?? (input instanceof Request ? input.method : 'GET')).toUpperCase()
  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) assertOnlineMutation()
  try {
    const response = Object.keys(init).length === 0 ? await fetch(input) : await fetch(input, init)
    online.value = true
    await responseHandler?.(response)
    return response
  } catch (error) {
    online.value = false
    if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) throw new OfflineMutationError()
    throw error
  }
}

export async function apiUpload(
  url: string,
  body: FormData,
  onProgress?: (percent: number) => void,
): Promise<Response> {
  assertOnlineMutation()

  return new Promise<Response>((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open('POST', url)
    xhr.setRequestHeader('Accept', 'application/json')
    for (const [name, value] of Object.entries(csrfHeaders())) xhr.setRequestHeader(name, value)
    xhr.upload.onprogress = event => {
      if (event.lengthComputable) onProgress?.(Math.round(event.loaded / event.total * 100))
    }
    xhr.onload = async () => {
      online.value = true
      const headers = new Headers()
      for (const line of xhr.getAllResponseHeaders().trim().split(/[\r\n]+/)) {
        const separator = line.indexOf(':')
        if (separator > 0) headers.append(line.slice(0, separator).trim(), line.slice(separator + 1).trim())
      }
      const response = new Response(xhr.responseText, { status: xhr.status, statusText: xhr.statusText, headers })
      await responseHandler?.(response)
      resolve(response)
    }
    xhr.onerror = () => {
      online.value = false
      reject(new OfflineMutationError())
    }
    xhr.onabort = () => reject(new Error('O upload foi cancelado.'))
    xhr.send(body)
  })
}

export async function responseMessage(response: Response, fallback: string): Promise<string> {
  const body = await response.clone().json().catch(() => null)
  return typeof body?.message === 'string' ? body.message : fallback
}
