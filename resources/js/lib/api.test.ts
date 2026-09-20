import { afterEach, describe, expect, it, vi } from 'vitest'
import { apiFetch, apiUpload, connectivity, OfflineMutationError } from './api'

describe('apiFetch offline contract', () => {
  afterEach(() => { connectivity.markOnline(); vi.unstubAllGlobals() })

  it('rejects every mutation before touching the network', async () => {
    const fetch = vi.fn()
    vi.stubGlobal('fetch', fetch)
    connectivity.markOffline()

    for (const method of ['POST', 'PUT', 'PATCH', 'DELETE']) {
      await expect(apiFetch('/api/v1/projects/1', { method })).rejects.toBeInstanceOf(OfflineMutationError)
    }
    await expect(apiUpload('/api/v1/projects/1/documents', new FormData())).rejects.toBeInstanceOf(OfflineMutationError)
    expect(fetch).not.toHaveBeenCalled()
  })

  it('allows read attempts and records a network loss', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('network unavailable')))

    await expect(apiFetch('/api/v1/projects')).rejects.toThrow('network unavailable')
    expect(connectivity.online.value).toBe(false)
  })
})
