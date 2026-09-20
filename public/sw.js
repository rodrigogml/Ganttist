const SHELL_CACHE = 'ganttist-shell-v7'
const ASSET_CACHE = 'ganttist-assets-v7'
const STATIC_PATHS = ['/offline.html', '/manifest.webmanifest?v=7', '/brand/logo-square.png', '/brand/logo-square-300.png', '/brand/icon-192.png', '/brand/icon-512.png']
const PRECACHE_ENTRIES = ['resources/css/app.css', 'resources/css/gantt-workspace.css', 'resources/js/app.ts', 'resources/js/documents/ProjectDocumentsPage.vue']

async function precacheShell() {
  const shell = await caches.open(SHELL_CACHE)
  await shell.addAll(STATIC_PATHS)
  const response = await fetch('/build/manifest.json', { cache: 'no-store' })
  if (!response.ok) throw new Error('Vite manifest unavailable')
  const manifest = await response.json()
  const paths = new Set(['/build/manifest.json'])
  const visited = new Set()
  const addEntry = key => {
    if (visited.has(key)) return
    visited.add(key)
    const entry = manifest[key]
    if (!entry) return
    if (entry.file) paths.add(`/build/${entry.file}`)
    for (const file of [...(entry.css || []), ...(entry.assets || [])]) paths.add(`/build/${file}`)
    for (const imported of entry.imports || []) addEntry(imported)
  }
  for (const entry of PRECACHE_ENTRIES) addEntry(entry)
  const assets = await caches.open(ASSET_CACHE)
  await assets.addAll([...paths])
}

self.addEventListener('message', event => { if (event.data === 'SKIP_WAITING') self.skipWaiting() })
self.addEventListener('install', event => event.waitUntil(precacheShell().then(() => self.skipWaiting())))
self.addEventListener('activate', event => event.waitUntil((async () => {
  for (const name of await caches.keys()) {
    if (name.startsWith('ganttist-static-') || (name.startsWith('ganttist-shell-') && name !== SHELL_CACHE) || (name.startsWith('ganttist-assets-') && name !== ASSET_CACHE)) await caches.delete(name)
  }
  await self.clients.claim()
})()))

async function cachedDocumentResponse(request) {
  const cached = await caches.match(request.url)
  if (!cached) return new Response('Documento não armazenado offline.', { status: 503 })
  if (request.method === 'HEAD') return new Response(null, { status: 200, headers: cached.headers })
  const range = request.headers.get('Range')
  if (!range) return cached
  const match = /^bytes=(\d*)-(\d*)$/.exec(range)
  const total = Number(cached.headers.get('Content-Length'))
  if (!match || !Number.isSafeInteger(total) || total < 1) return new Response(null, { status: 416, headers: { 'Content-Range': `bytes */${Number.isSafeInteger(total) ? total : '*'}` } })
  const start = match[1] ? Number(match[1]) : Math.max(0, total - Number(match[2]))
  const end = match[2] && match[1] ? Math.min(Number(match[2]), total - 1) : total - 1
  if (!Number.isSafeInteger(start) || !Number.isSafeInteger(end) || start < 0 || end < start || start >= total || !cached.body) {
    return new Response(null, { status: 416, headers: { 'Content-Range': `bytes */${total}` } })
  }
  const headers = new Headers(cached.headers)
  headers.set('Accept-Ranges', 'bytes')
  headers.set('Content-Range', `bytes ${start}-${end}/${total}`)
  headers.set('Content-Length', String(end - start + 1))
  const source = cached.body.getReader()
  const body = new ReadableStream({
    async start(controller) {
      let offset = 0
      try {
        for (;;) {
          const { done, value } = await source.read()
          if (done) break
          const chunkStart = offset
          const chunkEnd = offset + value.byteLength - 1
          if (chunkEnd >= start && chunkStart <= end) {
            controller.enqueue(value.subarray(Math.max(0, start - chunkStart), Math.min(value.byteLength, end - chunkStart + 1)))
          }
          offset += value.byteLength
          if (offset > end) { await source.cancel(); break }
        }
        controller.close()
      } catch (error) {
        controller.error(error)
      }
    },
    cancel(reason) { return source.cancel(reason) },
  })
  return new Response(body, { status: 206, headers })
}

self.addEventListener('fetch', event => {
  const request = event.request
  if (!['GET', 'HEAD'].includes(request.method)) return
  const url = new URL(request.url)
  if (url.origin !== self.location.origin) return
  if (url.pathname.includes('/revisions/') && url.pathname.endsWith('/content')) {
    event.respondWith(fetch(request).catch(() => cachedDocumentResponse(request)))
    return
  }
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).then(async response => {
      if (response.ok) (await caches.open(SHELL_CACHE)).put('/__app_shell__', response.clone())
      return response
    }).catch(async () => (await caches.match('/__app_shell__')) || (await caches.match('/offline.html'))))
    return
  }
  if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/brand/') || url.pathname.startsWith('/icon') || url.pathname === '/manifest.webmanifest') {
    event.respondWith(caches.open(ASSET_CACHE).then(async cache => (await caches.match(request)) || fetch(request).then(response => { if (response.ok) cache.put(request, response.clone()); return response })))
  }
})
