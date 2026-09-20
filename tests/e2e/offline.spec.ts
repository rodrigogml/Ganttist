import { expect, test, type BrowserContext, type Page } from '@playwright/test'

const user = { id: 'offline-user', name: 'Ana', email: 'ana@example.test' }
const projectId = 'offline-project'
const contentUrl = `/api/v1/projects/${projectId}/documents/offline-document/revisions/offline-revision/content`
const derivedContentUrl = `/api/v1/projects/${projectId}/documents/offline-derived/revisions/offline-derived-revision/content`
const pdfBase64 = 'JVBERi0xLjMKJeLjz9MKMSAwIG9iago8PAovUHJvZHVjZXIgKHB5cGRmKQo+PgplbmRvYmoKMiAwIG9iago8PAovVHlwZSAvUGFnZXMKL0NvdW50IDEKL0tpZHMgWyA0IDAgUiBdCj4+CmVuZG9iagozIDAgb2JqCjw8Ci9UeXBlIC9DYXRhbG9nCi9QYWdlcyAyIDAgUgo+PgplbmRvYmoKNCAwIG9iago8PAovVHlwZSAvUGFnZQovUmVzb3VyY2VzIDw8Cj4+Ci9NZWRpYUJveCBbIDAuMCAwLjAgMjAwIDIwMCBdCi9QYXJlbnQgMiAwIFIKPj4KZW5kb2JqCnhyZWYKMCA1CjAwMDAwMDAwMDAgNjU1MzUgZiAKMDAwMDAwMDAxNSAwMDAwMCBuIAowMDAwMDAwMDU0IDAwMDAwIG4gCjAwMDAwMDAxMTMgMDAwMDAgbiAKMDAwMDAwMDE2MiAwMDAwMCBuIAp0cmFpbGVyCjw8Ci9TaXplIDUKL1Jvb3QgMyAwIFIKL0luZm8gMSAwIFIKPj4Kc3RhcnR4cmVmCjI1NgolJUVPRgo='
const pdfSha256 = '3de2b3788e3232e4fcc07525e218a18d7d81a158d452654317a044163dfb710b'

const workspace = {
  project: { id: projectId, name: 'Projeto preparado', source: 'local', sync_status: 'ready', updated_at: '2026-09-19T12:00:00Z', role: 'owner' },
  tasks: [{ id: 'offline-task', title: 'Vistoriar pavimento', description: 'Tarefa armazenada no snapshot.', kind: 'task', level: 0, parent_id: null, section_id: null, priority: 2, start: '2026-09-19', finish: '2026-09-20', considered_start: '2026-09-19', considered_deadline: '2026-09-20', completed: false, progress: 0, status: 'opened', critical: false }], dependencies: [], people: [],
  stats: { progress: 50, completed: 1, total: 3, critical: 0, late: 0 },
}

const revision = {
  id: 'offline-revision', label: 'R01', sequence: 1, mimeType: 'application/pdf', originalFilename: 'plano.pdf',
  sizeBytes: 431, sha256: pdfSha256, contentUrl, createdAt: '2026-09-19T12:00:00Z',
}

const document = {
  id: 'offline-document', projectId, name: 'Plano offline', kind: 'manual', archivedAt: null,
  currentRevision: revision, tags: [], outdated: false, processing: null,
}

const derivedRevision = { ...revision, id: 'offline-derived-revision', contentUrl: derivedContentUrl }
const derivedDocument = {
  ...document, id: 'offline-derived', name: 'Pavimento offline', kind: 'derived', currentRevision: derivedRevision,
  derivation: { id: 'offline-derivation', sourceDocumentId: document.id, sourceDocumentName: document.name },
}

function manifest(version = 'snapshot-1', withDocument = false, withDerived = false) {
  const documents = [...(withDocument ? [document] : []), ...(withDerived ? [derivedDocument] : [])]
  return {
    schemaVersion: 1, version, generatedAt: '2026-09-19T12:00:00Z', userId: user.id, projectId,
    documents, tags: [],
    files: documents.map(item => ({ revisionId: item.currentRevision.id, url: item.currentRevision.contentUrl, sizeBytes: 431, sha256: pdfSha256, mimeType: 'application/pdf' })),
    totalBytes: documents.length * 431, workspace,
  }
}

async function waitForControlledPage(page: Page) {
  await page.goto('/')
  await page.evaluate(() => navigator.serviceWorker.ready)
  await page.reload()
  await expect.poll(() => page.evaluate(() => Boolean(navigator.serviceWorker.controller))).toBe(true)
  await expect(page.getByRole('heading', { name: 'Entre no Ganttist' })).toBeVisible()
}

async function seedPreparedProject(page: Page, withDocument = false, withDerived = false) {
  await page.evaluate(async ({ activeUser, snapshot, encodedPdf }) => {
    const db = await new Promise<IDBDatabase>((resolve, reject) => {
      const request = indexedDB.open('ganttist-offline-v1', 1)
      request.onupgradeneeded = () => {
        if (!request.result.objectStoreNames.contains('manifests')) request.result.createObjectStore('manifests', { keyPath: 'key' })
        if (!request.result.objectStoreNames.contains('identity')) request.result.createObjectStore('identity', { keyPath: 'key' })
      }
      request.onerror = () => reject(request.error)
      request.onsuccess = () => resolve(request.result)
    })
    await new Promise<void>((resolve, reject) => {
      const transaction = db.transaction(['identity', 'manifests'], 'readwrite')
      transaction.objectStore('identity').put({ key: 'active', user: activeUser })
      transaction.objectStore('manifests').put({ key: `${activeUser.id}:${snapshot.projectId}`, active: true, manifest: snapshot })
      transaction.oncomplete = () => resolve()
      transaction.onerror = () => reject(transaction.error)
    })
    db.close()

    if (snapshot.files.length) {
      const bytes = Uint8Array.from(atob(encodedPdf), character => character.charCodeAt(0))
      const cache = await caches.open(`ganttist-documents:${activeUser.id}:${snapshot.projectId}:${snapshot.version}`)
      for (const file of snapshot.files) {
        await cache.put(`${file.url}?offlineUser=${encodeURIComponent(activeUser.id)}`, new Response(bytes, {
          headers: { 'Content-Type': file.mimeType, 'Content-Length': String(bytes.byteLength), ETag: `"${file.sha256}"` },
        }))
      }
    }
  }, { activeUser: user, snapshot: manifest('snapshot-1', withDocument, withDerived), encodedPdf: pdfBase64 })
}

async function offlineState(page: Page, expectedUserId = user.id) {
  return page.evaluate(async ({ currentUserId }) => {
    const db = await new Promise<IDBDatabase>((resolve, reject) => {
      const request = indexedDB.open('ganttist-offline-v1', 1)
      request.onerror = () => reject(request.error)
      request.onsuccess = () => resolve(request.result)
    })
    const records = await new Promise<Array<{ manifest?: { version?: string } }>>((resolve, reject) => {
      const transaction = db.transaction('manifests', 'readonly')
      const request = transaction.objectStore('manifests').getAll()
      request.onsuccess = () => resolve(request.result)
      request.onerror = () => reject(request.error)
    })
    const identity = await new Promise<{ user?: { id?: string } } | undefined>((resolve, reject) => {
      const transaction = db.transaction('identity', 'readonly')
      const request = transaction.objectStore('identity').get('active')
      request.onsuccess = () => resolve(request.result)
      request.onerror = () => reject(request.error)
    })
    const stores = Array.from(db.objectStoreNames)
    db.close()
    return {
      caches: (await caches.keys()).filter(name => name.includes(`:${currentUserId}:`)),
      identityUserId: identity?.user?.id ?? null,
      records: records.length,
      versions: records.map(record => record.manifest?.version ?? null),
      stores,
    }
  }, { currentUserId: expectedUserId })
}

async function setOffline(context: BrowserContext, value: boolean) {
  await context.setOffline(value)
}

test('installs the PWA and boots without a prepared project', async ({ page, context }) => {
  await waitForControlledPage(page)
  await setOffline(context, true)
  await page.reload()
  await expect(page.getByText(/SOMENTE LEITURA/)).toBeVisible()
  await expect(page.getByText(/Disponibilizar offline/)).toBeVisible()
})

test('lists a deliberately prepared snapshot in read-only mode', async ({ page, context }) => {
  await waitForControlledPage(page)
  await seedPreparedProject(page)
  await setOffline(context, true)
  await page.reload()
  await expect(page.getByText('Projeto preparado')).toBeVisible()
  await expect(page.getByRole('button', { name: /Novo Projeto/ }).first()).toBeDisabled()
})

test('opens a cached task with every editor control read-only', async ({ page, context }) => {
  await waitForControlledPage(page)
  await seedPreparedProject(page)
  await setOffline(context, true)
  await page.goto(`/projects/${projectId}/tasks`)
  const task = page.locator('.task-list-card').filter({ hasText: 'Vistoriar pavimento' })
  await expect(task).toBeVisible()
  await task.click()
  await expect(page.getByRole('heading', { name: 'Vistoriar pavimento' })).toBeVisible()
  await expect(page.locator('.drawer-edit-fields input').first()).toBeDisabled()
  await expect(page.getByRole('button', { name: 'Salvar alterações' })).toBeDisabled()
})

test('opens a deliberately cached PDF and serves byte ranges without a network', async ({ page, context }) => {
  await waitForControlledPage(page)
  await seedPreparedProject(page, true)
  await setOffline(context, true)
  await page.goto(`/projects/${projectId}/documents`)
  await expect(page.getByText('Plano offline')).toBeVisible()

  const responses = await page.evaluate(async ({ url, activeUserId }) => {
    const offlineUrl = `${url}?offlineUser=${encodeURIComponent(activeUserId)}`
    const full = await fetch(offlineUrl)
    const range = await fetch(offlineUrl, { headers: { Range: 'bytes=0-3' } })
    return { fullStatus: full.status, fullSize: (await full.arrayBuffer()).byteLength, rangeStatus: range.status, rangeText: await range.text(), contentRange: range.headers.get('Content-Range') }
  }, { url: contentUrl, activeUserId: user.id })
  expect(responses).toEqual({ fullStatus: 200, fullSize: 431, rangeStatus: 206, rangeText: '%PDF', contentRange: 'bytes 0-3/431' })

  await page.getByRole('row', { name: /Plano offline/ }).click()
  await expect(page.getByText(/gina 1 de 1/)).toBeVisible()
})

test('opens a derived document from the same offline snapshot', async ({ page, context }) => {
  await waitForControlledPage(page)
  await seedPreparedProject(page, true, true)
  await setOffline(context, true)
  await page.goto(`/projects/${projectId}/documents`)
  const derived = page.getByRole('row', { name: /Pavimento offline/ })
  await expect(derived).toBeVisible()
  await derived.click()
  await expect(page.getByText(/Origem: Plano offline/)).toBeVisible()
  await expect(page.getByText(/gina 1 de 1/)).toBeVisible()
})

test('reports insufficient quota without replacing the active snapshot', async ({ browser }) => {
  const context = await browser.newContext({ serviceWorkers: 'block' })
  const page = await context.newPage()
  await context.route('**/api/v1/**', async route => {
    const pathname = new URL(route.request().url()).pathname
    if (pathname === '/api/v1/me') return route.fulfill({ json: { user } })
    if (pathname.endsWith('/workspace')) return route.fulfill({ json: { data: workspace } })
    if (pathname.endsWith('/offline-manifest')) return route.fulfill({ json: { data: { ...manifest('snapshot-too-large', true), totalBytes: 1_000 } } })
    if (pathname.endsWith('/tags')) return route.fulfill({ json: { data: [] } })
    if (pathname.endsWith('/documents')) return route.fulfill({ json: { data: [], meta: { currentPage: 1, lastPage: 1, perPage: 30, total: 0 } } })
    return route.fulfill({ status: 404, json: {} })
  })
  await page.goto(`/projects/${projectId}/documents`)
  await seedPreparedProject(page)
  await page.evaluate(() => {
    Object.defineProperty(navigator.storage, 'estimate', { configurable: true, value: async () => ({ quota: 100, usage: 90 }) })
    Object.defineProperty(navigator.storage, 'persist', { configurable: true, value: async () => true })
  })
  await expect.poll(() => page.evaluate(() => navigator.storage.estimate())).toEqual({ quota: 100, usage: 90 })
  page.once('dialog', dialog => dialog.accept())
  await page.getByRole('button', { name: /Disponibilizar offline|Atualizar offline|Atualização offline disponível/ }).click()
  await expect(page.locator('.document-error')).toContainText(/espa.o suficiente no dispositivo/)
  expect(await offlineState(page)).toMatchObject({ records: 1, versions: ['snapshot-1'] })
  await context.close()
})

test('detects an available offline update only after reconnecting', async ({ page, context }) => {
  await waitForControlledPage(page)
  await seedPreparedProject(page)
  await setOffline(context, true)
  await page.goto(`/projects/${projectId}/documents`)
  await expect(page.getByRole('button', { name: 'Atualizar offline' })).toBeDisabled()
  await page.route('**/offline-manifest', route => route.fulfill({ json: { data: manifest('snapshot-2') } }))
  const manifestResponse = page.waitForResponse(response => new URL(response.url()).pathname.endsWith('/offline-manifest'))
  await setOffline(context, false)
  await page.evaluate(() => window.dispatchEvent(new Event('online')))
  await manifestResponse
  await expect(page.getByRole('button', { name: 'Atualização offline disponível' })).toBeVisible()
  expect(await offlineState(page)).toMatchObject({ records: 1, versions: ['snapshot-1'] })
})

test('atomically replaces the old document cache only after a verified update', async ({ page, context }) => {
  await waitForControlledPage(page)
  await seedPreparedProject(page, true)
  await setOffline(context, true)
  await page.goto(`/projects/${projectId}/documents`)
  await expect(page.getByText('Plano offline')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Atualizar offline' })).toBeDisabled()
  const nextRevision = { ...revision, id: 'offline-revision-2', label: 'R02', sequence: 2, contentUrl: `${contentUrl}-2` }
  const nextManifest = {
    ...manifest('snapshot-2', true),
    generatedAt: '2026-09-20T12:00:00Z',
    documents: [{ ...document, currentRevision: nextRevision }],
    files: [{ revisionId: nextRevision.id, url: nextRevision.contentUrl, sizeBytes: 431, sha256: pdfSha256, mimeType: 'application/pdf' }],
  }
  await page.route('**/offline-manifest', route => route.fulfill({ json: { data: nextManifest } }))
  await page.route(`**${nextRevision.contentUrl}`, route => route.fulfill({
    status: 200,
    body: Buffer.from(pdfBase64, 'base64'),
    headers: { 'Content-Type': 'application/pdf', 'Content-Length': '431', ETag: `"${pdfSha256}"` },
  }))
  const manifestResponse = page.waitForResponse(response => new URL(response.url()).pathname.endsWith('/offline-manifest'))
  await setOffline(context, false)
  await page.evaluate(() => window.dispatchEvent(new Event('online')))
  await manifestResponse
  const update = page.getByRole('button', { name: /Atualizar offline|Atualização offline disponível/ })
  await expect(update).toBeVisible()
  page.once('dialog', dialog => dialog.accept())
  await update.click()
  await expect.poll(() => offlineState(page)).toMatchObject({
    caches: [`ganttist-documents:${user.id}:${projectId}:snapshot-2`],
    records: 1,
    versions: ['snapshot-2'],
  })
})

test('logout removes that user identity, manifests and document caches', async ({ page, context }) => {
  await waitForControlledPage(page)
  await seedPreparedProject(page, true)
  await page.route('**/api/v1/**', async route => {
    const pathname = new URL(route.request().url()).pathname
    if (pathname === '/api/v1/me') return route.fulfill({ json: { user } })
    if (pathname.endsWith('/workspace')) return route.fulfill({ json: { data: workspace } })
    if (pathname.endsWith('/offline-manifest')) return route.fulfill({ json: { data: manifest() } })
    if (pathname.endsWith('/tags')) return route.fulfill({ json: { data: [] } })
    if (pathname.endsWith('/documents')) return route.fulfill({ json: { data: [document], meta: { currentPage: 1, lastPage: 1, perPage: 30, total: 1 } } })
    return route.fulfill({ status: 404, json: {} })
  })
  await setOffline(context, true)
  await page.goto(`/projects/${projectId}/documents`)
  await page.route('**/auth/logout', route => route.fulfill({ status: 204 }))
  await setOffline(context, false)
  await page.evaluate(() => window.dispatchEvent(new Event('online')))
  await page.getByRole('button', { name: 'AN', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Entre no Ganttist' })).toBeVisible()
  expect(await offlineState(page)).toMatchObject({ caches: [], identityUserId: null, records: 0 })
})

test('switching users purges the previously trusted user cache', async ({ page, context }) => {
  await waitForControlledPage(page)
  await seedPreparedProject(page, true)
  const replacement = { id: 'replacement-user', name: 'Bia', email: 'bia@example.test' }
  let currentRequests = 0
  await page.route('**/api/v1/me', route => { currentRequests += 1; return route.fulfill({ json: { user: replacement } }) })
  await page.route('**/api/v1/projects', route => route.fulfill({ json: { data: [] } }))
  await page.route('**/api/v1/invitations', route => route.fulfill({ json: { data: [] } }))
  await setOffline(context, false)
  await page.reload()
  await expect(page.getByText(/Crie seu primeiro projeto/)).toBeVisible()
  expect(currentRequests).toBe(1)
  await expect.poll(() => offlineState(page)).toMatchObject({ caches: [], identityUserId: replacement.id, records: 0 })
})

test('the first authorized project refresh removes revoked project caches', async ({ page, context }) => {
  await waitForControlledPage(page)
  await seedPreparedProject(page, true)
  await page.route('**/api/v1/me', route => route.fulfill({ json: { user } }))
  await page.route('**/api/v1/projects', route => route.fulfill({ json: { data: [] } }))
  await page.route('**/api/v1/invitations', route => route.fulfill({ json: { data: [] } }))
  await setOffline(context, false)
  await page.reload()
  await expect(page.getByText(/Crie seu primeiro projeto/)).toBeVisible()
  expect(await offlineState(page)).toMatchObject({ caches: [], identityUserId: user.id, records: 0 })
})

for (const status of [401, 419]) {
  test(`${status} online invalidates the trusted identity and all of its caches`, async ({ page }) => {
    await waitForControlledPage(page)
    await seedPreparedProject(page, true)
    await page.route('**/api/v1/me', route => route.fulfill({ status, json: { message: 'Sessão inválida.' } }))
    await page.reload()
    await expect(page.getByRole('heading', { name: 'Entre no Ganttist' })).toBeVisible()
    await expect.poll(() => offlineState(page)).toMatchObject({ caches: [], identityUserId: null, records: 0 })
  })
}

test('failed mutation transports create no outbox, background sync or replay', async ({ page, context }) => {
  await waitForControlledPage(page)
  await setOffline(context, true)
  const result = await page.evaluate(async () => {
    const errors: string[] = []
    for (const method of ['POST', 'PUT', 'PATCH', 'DELETE']) {
      try { await fetch('/api/v1/offline-mutation-probe', { method, body: method === 'DELETE' ? undefined : '{}' }) }
      catch (error) { errors.push(error instanceof Error ? error.name : 'unknown') }
    }
    const db = await new Promise<IDBDatabase>((resolve, reject) => {
      const request = indexedDB.open('ganttist-offline-v1', 1)
      request.onerror = () => reject(request.error)
      request.onsuccess = () => resolve(request.result)
    })
    const stores = Array.from(db.objectStoreNames)
    db.close()
    const registration = await navigator.serviceWorker.ready
    const sync = (registration as ServiceWorkerRegistration & { sync?: { getTags(): Promise<string[]> } }).sync
    return { errors, stores, syncTags: sync ? await sync.getTags() : [] }
  })
  expect(result.errors).toEqual(['TypeError', 'TypeError', 'TypeError', 'TypeError'])
  expect(result.stores.sort()).toEqual(['identity', 'manifests'])
  expect(result.syncTags).toEqual([])

  let replayCount = 0
  await page.route('**/api/v1/offline-mutation-probe', route => { replayCount += 1; return route.fulfill({ status: 204 }) })
  await setOffline(context, false)
  await page.waitForTimeout(500)
  expect(replayCount).toBe(0)
})
