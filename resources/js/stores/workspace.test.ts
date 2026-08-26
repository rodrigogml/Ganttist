import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useWorkspaceStore } from './workspace'
import { useAuthStore } from './auth'

function workspaceResponse(workspace: unknown) { return { data: workspace } }

describe('workspace visibility', () => {
  beforeEach(() => setActivePinia(createPinia()))
  afterEach(() => vi.unstubAllGlobals())

  it('reveals a hidden dependency endpoint by opening its ancestors and clearing filters', () => {
    const store = useWorkspaceStore()
    store.workspace = {
      project: { id: 'p', name: 'Projeto', source: 'Local', sync_status: 'local', updated_at: '2026-08-17T00:00:00Z' },
      tasks: [
        { id: 'group', title: 'Grupo', kind: 'section', level: 0, start: null, finish: null, progress: 0, status: 'opened', critical: false },
        { id: 'task', title: 'Tarefa escondida', kind: 'task', level: 1, parent_id: 'group', start: '2026-08-17', finish: '2026-08-17', progress: 0, status: 'opened', critical: false },
        { id: 'other', title: 'Outra tarefa', kind: 'task', level: 0, start: '2026-08-18', finish: '2026-08-18', progress: 0, status: 'opened', critical: false },
      ],
      dependencies: [{ id: 'd', from: 'task', to: 'other', type: 'FS', critical: false }],
      stats: { progress: 0, completed: 0, total: 2, critical: 0, opened: 2, blocked: 0, scheduled: 0, late: 0, without_dates: 0 },
    }
    store.hiddenGroups = new Set(['group'])
    store.search = 'sem resultado'
    store.setStatusFilters(['completed'])

    store.revealTask('task')

    expect(store.hiddenGroups.has('group')).toBe(false)
    expect(store.search).toBe('')
    expect(store.statusFilters).toEqual(['opened', 'scheduled', 'late', 'blocked', 'completed'])
  })

  it('combines multiple status choices and toggles the unlocked virtual parent', () => {
    const store = useWorkspaceStore()
    const base = { kind: 'task' as const, level: 0, start: null, finish: null, progress: 0, critical: false }
    store.workspace = {
      project: { id: 'p', name: 'Projeto', source: 'Local', sync_status: 'local', updated_at: '2026-08-17T00:00:00Z' },
      tasks: [
        { ...base, id: 'opened', title: 'Aberta', status: 'opened' },
        { ...base, id: 'scheduled', title: 'Agendada', status: 'scheduled' },
        { ...base, id: 'late', title: 'Atrasada', status: 'late' },
        { ...base, id: 'blocked', title: 'Bloqueada', status: 'blocked' },
        { ...base, id: 'completed', title: 'Concluída', status: 'completed' },
      ],
      dependencies: [], stats: { progress: 0, completed: 1, total: 5, critical: 0, opened: 1, blocked: 1, scheduled: 1, late: 1, without_dates: 5 },
    }

    store.setStatusFilters([])
    store.toggleUnblockedStatusFilters()
    expect(store.tasks.map(task => task.id)).toEqual(['opened', 'scheduled', 'late'])

    store.toggleStatusFilter('blocked')
    expect(store.tasks.map(task => task.id)).toEqual(['opened', 'scheduled', 'late', 'blocked'])

    store.toggleStatusFilter('opened')
    expect(store.tasks.map(task => task.id)).toEqual(['scheduled', 'late', 'blocked'])
  })

  it('reconciles an already-open workspace without resetting selection or replacing it with an error', async () => {
    const store = useWorkspaceStore()
    store.workspace = {
      project: { id: 'p', name: 'Projeto', source: 'Local', sync_status: 'local', updated_at: '2026-08-17T00:00:00Z' },
      tasks: [{ id: 'task', title: 'Antes', kind: 'task', level: 0, start: '2026-08-17', finish: '2026-08-17', progress: 0, status: 'opened', critical: false }],
      dependencies: [], stats: { progress: 0, completed: 0, total: 1, critical: 0, opened: 1, blocked: 0, scheduled: 0, late: 0, without_dates: 0 },
    }
    store.selected = ['task']
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => workspaceResponse({ ...store.workspace, tasks: [{ ...store.workspace!.tasks[0], title: 'Depois' }] }) }))

    await store.load()

    expect(store.workspace?.tasks[0].title).toBe('Depois')
    expect(store.selected).toEqual(['task'])
    expect(store.stale).toBe(false)
  })

  it('coalesces concurrent event refreshes into one workspace request', async () => {
    const store = useWorkspaceStore()
    const workspace = { project: { id: 'p', name: 'Projeto', source: 'Local', sync_status: 'local', updated_at: '2026-08-17T00:00:00Z' }, tasks: [], dependencies: [], stats: { progress: 0, completed: 0, total: 0, critical: 0, opened: 0, blocked: 0, scheduled: 0, late: 0, without_dates: 0 } }
    let resolveResponse!: (value: unknown) => void
    const response = new Promise(resolve => { resolveResponse = resolve })
    const fetch = vi.fn().mockReturnValue(response)
    vi.stubGlobal('fetch', fetch)

    const first = store.load()
    const second = store.load()
    const third = store.load()
    resolveResponse({ ok: true, json: async () => workspaceResponse(workspace) })
    await Promise.all([first, second, third])

    expect(fetch).toHaveBeenCalledTimes(1)
  })

  it('keeps the last projection when the API response violates the workspace contract', async () => {
    const store = useWorkspaceStore()
    store.workspace = { project: { id: 'p', name: 'Projeto', source: 'Local', sync_status: 'local', updated_at: '2026-08-17T00:00:00Z' }, tasks: [], dependencies: [], stats: { progress: 0, completed: 0, total: 0, critical: 0, opened: 0, blocked: 0, scheduled: 0, late: 0, without_dates: 0 } }
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data: { project: {}, tasks: [], dependencies: [], stats: {} } }) }))

    await store.load()

    expect(store.workspace?.project.id).toBe('p')
    expect(store.stale).toBe(true)
  })

  it('returns to authentication when the workspace session expires', async () => {
    const auth = useAuthStore()
    auth.user = { id: 'u1', name: 'Pessoa', email: 'pessoa@example.test' }
    const store = useWorkspaceStore()
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 401 }))

    await store.load()

    expect(auth.user).toBeNull()
    expect(auth.error).toContain('sessão expirou')
    expect(store.stale).toBe(false)
  })
})
