import { openDB, type DBSchema } from 'idb'
import { createSHA256 } from 'hash-wasm'
import { apiFetch } from '../lib/api'

export type OfflineManifest = {
  schemaVersion: number; version: string; generatedAt: string; userId: string; projectId: string
  workspace: unknown; documents: unknown[]; tags: unknown[]
  files: Array<{ revisionId: string; url: string; sizeBytes: number; sha256: string; mimeType: string }>
  totalBytes: number
}

interface GanttistOfflineDb extends DBSchema {
  manifests: { key: string; value: { key: string; active: boolean; manifest: OfflineManifest } }
  identity: { key: string; value: { key: string; user: { id: string; name: string | null; email: string } } }
}

const dbPromise = typeof indexedDB === 'undefined' ? null : openDB<GanttistOfflineDb>('ganttist-offline-v1', 1, {
  upgrade(db) { db.createObjectStore('manifests', { keyPath: 'key' }); db.createObjectStore('identity', { keyPath: 'key' }) },
})
const keyFor = (userId: string, projectId: string) => `${userId}:${projectId}`
const cacheFor = (userId: string, projectId: string, version: string) => `ganttist-documents:${userId}:${projectId}:${version}`

export async function rememberTrustedUser(user: { id: string; name: string | null; email: string }): Promise<void> {
  const db = await dbPromise
  if (!db) return
  const previous = await db.get('identity', 'active')
  const trusted = { id: String(user.id), name: user.name === null ? null : String(user.name), email: String(user.email) }
  const identityTransaction = db.transaction('identity', 'readwrite')
  await identityTransaction.store.put({ key: 'active', user: trusted })
  await identityTransaction.done
  if (previous && previous.user.id !== user.id) {
    for (const record of await db.getAll('manifests')) if (record.manifest.userId === previous.user.id) await db.delete('manifests', record.key)
    if (typeof caches !== 'undefined') {
      for (const name of await caches.keys()) if (name.includes(`:${previous.user.id}:`)) await caches.delete(name)
    }
  }
}

export async function trustedUser(): Promise<{ id: string; name: string | null; email: string } | null> {
  return (await dbPromise?.then(db => db.get('identity', 'active')))?.user ?? null
}

export async function preparedManifest(userId: string, projectId: string): Promise<OfflineManifest | null> {
  return (await dbPromise?.then(db => db.get('manifests', keyFor(userId, projectId))))?.manifest ?? null
}

export async function preparedProjects(userId: string): Promise<OfflineManifest[]> {
  const records = await dbPromise?.then(db => db.getAll('manifests')) ?? []
  return records.filter(record => record.active && record.manifest.userId === userId).map(record => record.manifest)
}

export async function reconcilePreparedProjects(userId: string, authorizedProjectIds: readonly string[]): Promise<void> {
  const authorized = new Set(authorizedProjectIds)
  for (const manifest of await preparedProjects(userId)) {
    if (!authorized.has(manifest.projectId)) await removeProjectOffline(userId, manifest.projectId)
  }
}

export async function prepareProjectOffline(projectId: string, userId: string, onProgress?: (done: number, total: number) => void): Promise<OfflineManifest> {
  const response = await apiFetch(`/api/v1/projects/${projectId}/offline-manifest`, { headers: { Accept: 'application/json' } })
  if (!response.ok) throw new Error('Não foi possível preparar o projeto para uso offline.')
  const manifest = (await response.json()).data as OfflineManifest
  if (manifest.userId !== userId || manifest.projectId !== projectId) throw new Error('Manifesto offline inválido.')
  const estimate = await navigator.storage?.estimate?.()
  if (estimate?.quota && estimate.usage !== undefined && manifest.totalBytes > estimate.quota - estimate.usage) throw new Error('Não há espaço suficiente no dispositivo.')
  await navigator.storage?.persist?.()
  const cacheName = cacheFor(userId, projectId, manifest.version)
  const cache = await caches.open(cacheName)
  let done = 0
  try {
    for (const file of manifest.files) {
      const url = `${file.url}?offlineUser=${encodeURIComponent(userId)}`
      const fileResponse = await apiFetch(file.url)
      if (!fileResponse.ok) throw new Error('Falha ao baixar um documento para uso offline.')
      const etag = fileResponse.headers.get('ETag')?.replace(/^W\//, '').replaceAll('"', '')
      if (etag !== file.sha256) throw new Error('A verificação de integridade de um documento falhou.')
      if (!fileResponse.body) throw new Error('O navegador não forneceu o conteúdo do documento.')
      const cacheWrite = cache.put(url, fileResponse.clone())
      const sha256 = await createSHA256()
      const reader = fileResponse.body.getReader()
      let received = 0
      for (;;) {
        const chunk = await reader.read()
        if (chunk.done) break
        sha256.update(chunk.value); received += chunk.value.byteLength
      }
      await cacheWrite
      if (received !== file.sizeBytes || sha256.digest() !== file.sha256) {
        await cache.delete(url)
        throw new Error('A verificação de integridade de um documento falhou.')
      }
      const cached = await cache.match(url)
      if (!cached || Number(cached.headers.get('Content-Length') ?? file.sizeBytes) !== file.sizeBytes) throw new Error('Um documento offline ficou incompleto.')
      done += file.sizeBytes
      onProgress?.(done, manifest.totalBytes)
    }
    await dbPromise?.then(db => db.put('manifests', { key: keyFor(userId, projectId), active: true, manifest }))
    for (const name of await caches.keys()) {
      if (name.startsWith(`ganttist-documents:${userId}:${projectId}:`) && name !== cacheName) await caches.delete(name)
    }
    return manifest
  } catch (error) {
    await caches.delete(cacheName)
    throw error
  }
}

export function offlineContentUrl(url: string, userId: string): string {
  return `${url}?offlineUser=${encodeURIComponent(userId)}`
}

export async function removeProjectOffline(userId: string, projectId: string): Promise<void> {
  await dbPromise?.then(db => db.delete('manifests', keyFor(userId, projectId)))
  if (typeof caches === 'undefined') return
  for (const name of await caches.keys()) if (name.startsWith(`ganttist-documents:${userId}:${projectId}:`)) await caches.delete(name)
}

export async function purgeOfflineUser(userId: string): Promise<void> {
  const db = await dbPromise
  if (db) {
    for (const record of await db.getAll('manifests')) if (record.manifest.userId === userId) await db.delete('manifests', record.key)
    const identity = await db.get('identity', 'active')
    if (identity?.user.id === userId) await db.delete('identity', 'active')
  }
  if (typeof caches !== 'undefined') {
    for (const name of await caches.keys()) if (name.includes(`:${userId}:`)) await caches.delete(name)
  }
}
