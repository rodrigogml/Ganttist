import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useWorkspaceStore } from './workspace'

function workspaceResponse(workspace: unknown) { return { data: workspace } }

describe('workspace visibility', () => {
  beforeEach(() => setActivePinia(createPinia()))
  afterEach(() => vi.unstubAllGlobals())

  it('reveals a hidden dependency endpoint by opening its ancestors and clearing filters', () => {
    const store = useWorkspaceStore()
    store.workspace = {
      project: { id: 'p', name: 'Projeto', source: 'Todoist', sync_status: 'synced', updated_at: '2026-08-17T00:00:00Z' },
      tasks: [
        { id: 'group', title: 'Grupo', kind: 'group', level: 0, start: null, finish: null, progress: 0, status: 'running', critical: false },
        { id: 'task', title: 'Tarefa escondida', kind: 'task', level: 1, parent_id: 'group', start: '2026-08-17', finish: '2026-08-17', progress: 0, status: 'running', critical: false },
        { id: 'other', title: 'Outra tarefa', kind: 'task', level: 0, start: '2026-08-18', finish: '2026-08-18', progress: 0, status: 'running', critical: false },
      ],
      dependencies: [{ id: 'd', from: 'task', to: 'other', type: 'FS', critical: false }],
      stats: { progress: 0, completed: 0, total: 2, critical: 0, unscheduled: 0 },
    }
    store.hiddenGroups = new Set(['group'])
    store.search = 'sem resultado'
    store.statusFilter = 'completed'

    store.revealTask('task')

    expect(store.hiddenGroups.has('group')).toBe(false)
    expect(store.search).toBe('')
    expect(store.statusFilter).toBe('all')
  })

  it('reconciles an already-open workspace without resetting selection or replacing it with an error', async () => {
    const store = useWorkspaceStore()
    store.workspace = {
      project: { id: 'p', name: 'Projeto', source: 'Todoist', sync_status: 'synced', updated_at: '2026-08-17T00:00:00Z' },
      tasks: [{ id: 'task', title: 'Antes', kind: 'task', level: 0, start: '2026-08-17', finish: '2026-08-17', progress: 0, status: 'running', critical: false }],
      dependencies: [], stats: { progress: 0, completed: 0, total: 1, critical: 0, unscheduled: 0 },
    }
    store.selected = ['task']
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => workspaceResponse({ ...store.workspace, tasks: [{ ...store.workspace!.tasks[0], title: 'Depois' }] }) }))

    await store.load()

    expect(store.workspace?.tasks[0].title).toBe('Depois')
    expect(store.selected).toEqual(['task'])
    expect(store.stale).toBe(false)
  })

  it('keeps the last projection when the API response violates the workspace contract', async () => {
    const store = useWorkspaceStore()
    store.workspace = { project: { id: 'p', name: 'Projeto', source: 'Todoist', sync_status: 'synced', updated_at: '2026-08-17T00:00:00Z' }, tasks: [], dependencies: [], stats: { progress: 0, completed: 0, total: 0, critical: 0, unscheduled: 0 } }
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ data: { project: {}, tasks: [], dependencies: [], stats: {} } }) }))

    await store.load()

    expect(store.workspace?.project.id).toBe('p')
    expect(store.stale).toBe(true)
  })
})
