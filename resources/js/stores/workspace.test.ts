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
    expect(store.statusFilters).toEqual(['opened', 'in_progress', 'scheduled', 'late', 'blocked', 'completed'])
  })

  it('combines multiple status choices and toggles the unlocked virtual parent', () => {
    const store = useWorkspaceStore()
    const base = { kind: 'task' as const, level: 0, start: null, finish: null, progress: 0, critical: false }
    store.workspace = {
      project: { id: 'p', name: 'Projeto', source: 'Local', sync_status: 'local', updated_at: '2026-08-17T00:00:00Z' },
      tasks: [
        { ...base, id: 'opened', title: 'Aberta', status: 'opened' },
        { ...base, id: 'in-progress', title: 'Em andamento', status: 'in_progress' },
        { ...base, id: 'scheduled', title: 'Agendada', status: 'scheduled' },
        { ...base, id: 'late', title: 'Atrasada', status: 'late' },
        { ...base, id: 'blocked', title: 'Bloqueada', status: 'blocked' },
        { ...base, id: 'completed', title: 'Concluída', status: 'completed' },
      ],
      dependencies: [], stats: { progress: 0, completed: 1, total: 6, critical: 0, opened: 1, in_progress: 1, blocked: 1, scheduled: 1, late: 1, without_dates: 5 },
    }

    store.setStatusFilters([])
    store.toggleUnblockedStatusFilters()
    expect(store.tasks.map(task => task.id)).toEqual(['opened', 'in-progress', 'scheduled', 'late'])

    store.toggleStatusFilter('blocked')
    expect(store.tasks.map(task => task.id)).toEqual(['opened', 'in-progress', 'scheduled', 'late', 'blocked'])

    store.toggleStatusFilter('opened')
    expect(store.tasks.map(task => task.id)).toEqual(['in-progress', 'scheduled', 'late', 'blocked'])
  })

  it('keeps ancestor sections visible when a nested task matches the search', () => {
    const store = useWorkspaceStore()
    const base = { start: null, finish: null, progress: 0, status: 'opened' as const, critical: false }
    store.workspace = {
      project: { id: 'p', name: 'Projeto', source: 'Local', sync_status: 'local', updated_at: '2026-08-17T00:00:00Z' },
      tasks: [
        { ...base, id: 'root', title: 'Entrega', kind: 'section' as const, level: 0 },
        { ...base, id: 'planning', title: 'Planejamento', kind: 'section' as const, level: 1, parent_id: 'root' },
        { ...base, id: 'match', title: 'Preparar protótipo', kind: 'task' as const, level: 2, parent_id: 'planning' },
        { ...base, id: 'other', title: 'Outra atividade', kind: 'task' as const, level: 1, parent_id: 'root' },
        { ...base, id: 'unrelated', title: 'Financeiro', kind: 'section' as const, level: 0 },
      ],
      dependencies: [], stats: { progress: 0, completed: 0, total: 2, critical: 0, opened: 2, blocked: 0, scheduled: 0, late: 0, without_dates: 2 },
    }

    store.search = 'protótipo'

    expect(store.tasks.map(task => task.id)).toEqual(['root', 'planning', 'match'])

    store.search = 'financeiro'

    expect(store.tasks.map(task => task.id)).toEqual(['unrelated'])
  })

  it('expands the ancestors of search results while keeping unrelated groups collapsed', () => {
    const store = useWorkspaceStore()
    const base = { start: null, finish: null, progress: 0, status: 'opened' as const, critical: false }
    store.workspace = {
      project: { id: 'p', name: 'Projeto', source: 'Local', sync_status: 'local', updated_at: '2026-08-17T00:00:00Z' },
      tasks: [
        { ...base, id: 'root', title: 'Entrega', kind: 'section' as const, level: 0 },
        { ...base, id: 'planning', title: 'Planejamento', kind: 'section' as const, level: 1, parent_id: 'root' },
        { ...base, id: 'match', title: 'Preparar protótipo', kind: 'task' as const, level: 2, parent_id: 'planning' },
        { ...base, id: 'unrelated', title: 'Financeiro', kind: 'section' as const, level: 0 },
        { ...base, id: 'unrelated-task', title: 'Fechar orçamento', kind: 'task' as const, level: 1, parent_id: 'unrelated' },
      ],
      dependencies: [], stats: { progress: 0, completed: 0, total: 2, critical: 0 },
    }
    store.hiddenGroups = new Set(['root', 'planning', 'unrelated'])

    store.search = 'protótipo'

    expect(store.hiddenGroups).toEqual(new Set(['unrelated']))
    expect(store.tasks.map(task => task.id)).toEqual(['root', 'planning', 'match'])
  })

  it('clears the task search and every task filter', () => {
    const store = useWorkspaceStore()

    store.search = 'protótipo'
    store.setStatusFilters(['blocked'])
    store.toggleAssigneeFilter('person-1')
    store.periodStart = '2026-08-01'
    store.periodEnd = '2026-08-31'

    store.clearTaskFilters()

    expect(store.search).toBe('')
    expect(store.statusFilters).toEqual(['opened', 'in_progress', 'scheduled', 'late', 'blocked', 'completed'])
    expect(store.assigneeFilters).toEqual([])
    expect(store.periodStart).toBe('')
    expect(store.periodEnd).toBe('')
  })

  it('applies all hierarchy display modes without treating task-only groups as intermediate', () => {
    const store = useWorkspaceStore()
    const base = { start: null, finish: null, progress: 0, status: 'opened' as const, critical: false }
    store.workspace = {
      project: { id: 'p', name: 'Projeto', source: 'Local', sync_status: 'local', updated_at: '2026-08-17T00:00:00Z' },
      tasks: [
        { ...base, id: 'root', title: 'Raiz', kind: 'section' as const, level: 0 },
        { ...base, id: 'tasks-only', title: 'Somente tarefas', kind: 'section' as const, level: 1, parent_id: 'root' },
        { ...base, id: 'task-a', title: 'Tarefa A', kind: 'task' as const, level: 2, parent_id: 'tasks-only' },
        { ...base, id: 'mixed', title: 'Misto', kind: 'section' as const, level: 1, parent_id: 'root' },
        { ...base, id: 'nested', title: 'Nó interno', kind: 'section' as const, level: 2, parent_id: 'mixed' },
        { ...base, id: 'task-b', title: 'Tarefa B', kind: 'task' as const, level: 2, parent_id: 'mixed' },
        { ...base, id: 'task-c', title: 'Tarefa C', kind: 'task' as const, level: 3, parent_id: 'nested' },
      ],
      dependencies: [], stats: { progress: 0, completed: 0, total: 3, critical: 0 },
    }

    store.collapseAllGroups()
    expect(store.hiddenGroups).toEqual(new Set(['root', 'tasks-only', 'mixed', 'nested']))

    store.expandAllGroups()
    expect(store.hiddenGroups).toEqual(new Set())

    store.expandIntermediateGroups()
    expect(store.hiddenGroups).toEqual(new Set(['tasks-only', 'nested']))
  })

  it('focuses a task, its complete dependency chain, and the required parent sections', () => {
    const store = useWorkspaceStore()
    const base = { start: null, finish: null, progress: 0, status: 'opened' as const, critical: false }
    store.workspace = {
      project: { id: 'p', name: 'Projeto', source: 'Local', sync_status: 'local', updated_at: '2026-08-17T00:00:00Z' },
      tasks: [
        { ...base, id: 'root', title: 'Entrega', kind: 'section' as const, level: 0 },
        { ...base, id: 'upstream-section', title: 'Preparação', kind: 'section' as const, level: 1, parent_id: 'root' },
        { ...base, id: 'upstream', title: 'Preparar insumo', kind: 'task' as const, level: 2, parent_id: 'upstream-section' },
        { ...base, id: 'focus', title: 'Executar', kind: 'task' as const, level: 1, parent_id: 'root' },
        { ...base, id: 'downstream', title: 'Revisar', kind: 'task' as const, level: 1, parent_id: 'root' },
        { ...base, id: 'later', title: 'Publicar', kind: 'task' as const, level: 1, parent_id: 'root' },
        { ...base, id: 'unrelated', title: 'Financeiro', kind: 'task' as const, level: 0 },
      ],
      dependencies: [
        { id: 'one', from: 'upstream', to: 'focus', type: 'FS', critical: false },
        { id: 'two', from: 'focus', to: 'downstream', type: 'FS', critical: false },
        { id: 'three', from: 'downstream', to: 'later', type: 'FS', critical: false },
      ],
      stats: { progress: 0, completed: 0, total: 5, critical: 0, opened: 5, blocked: 0, scheduled: 0, late: 0, without_dates: 5 },
    }
    store.hiddenGroups = new Set(['root', 'upstream-section'])
    store.search = 'financeiro'

    store.focusTaskRelations('focus')

    expect(store.relationshipFocusTaskId).toBe('focus')
    expect(store.hiddenGroups.has('root')).toBe(false)
    expect(store.hiddenGroups.has('upstream-section')).toBe(false)
    expect(store.tasks.map(task => task.id)).toEqual(['root', 'upstream-section', 'upstream', 'focus', 'downstream', 'later'])

    store.search = 'executar'

    expect(store.relationshipFocusTaskId).toBeNull()
    expect(store.tasks.map(task => task.id)).toEqual(['root', 'focus'])
  })

  it('keeps the last valid task query applied while the current expression is invalid', () => {
    const store = useWorkspaceStore()
    const base = { kind: 'task' as const, level: 0, start: null, finish: null, progress: 0, status: 'opened' as const, critical: false }
    store.workspace = {
      project: { id: 'p', name: 'Projeto', source: 'Local', sync_status: 'local', updated_at: '2026-08-17T00:00:00Z' },
      tasks: [
        { ...base, id: 'kitchen', title: 'Planejar cozinha' },
        { ...base, id: 'office', title: 'Planejar escritório' },
      ],
      dependencies: [], stats: { progress: 0, completed: 0, total: 2, critical: 0, opened: 2, blocked: 0, scheduled: 0, late: 0, without_dates: 2 },
    }

    store.search = 'cozinha'
    store.search = '(cozinha'

    expect(store.searchError).toBe('Parêntese de fechamento ausente.')
    expect(store.tasks.map(task => task.id)).toEqual(['kitchen'])
  })

  it('reveals a filtered dependency endpoint with its ancestors until filters change', () => {
    const store = useWorkspaceStore()
    const base = { start: null, finish: null, progress: 0, status: 'opened' as const, critical: false }
    store.workspace = {
      project: { id: 'p', name: 'Projeto', source: 'Local', sync_status: 'local', updated_at: '2026-08-17T00:00:00Z' },
      tasks: [
        { ...base, id: 'group', title: 'Grupo', kind: 'section' as const, level: 0 },
        { ...base, id: 'visible', title: 'Planejar cozinha', kind: 'task' as const, level: 1, parent_id: 'group' },
        { ...base, id: 'exception', title: 'Comprar granito', kind: 'task' as const, level: 1, parent_id: 'group' },
      ],
      dependencies: [], stats: { progress: 0, completed: 0, total: 2, critical: 0, opened: 2, blocked: 0, scheduled: 0, late: 0, without_dates: 2 },
    }
    store.search = 'cozinha'
    store.hiddenGroups = new Set(['group'])

    store.revealFilterException('exception')

    expect(store.filterExceptions).toEqual(new Set(['exception']))
    expect(store.hiddenGroups.has('group')).toBe(false)
    expect(store.tasks.map(task => task.id)).toEqual(['group', 'visible', 'exception'])

    store.search = 'granito'

    expect(store.filterExceptions).toEqual(new Set())
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

  it('loads the project clicked while a different restored project is still loading', async () => {
    const store = useWorkspaceStore()
    let resolveFirst!: (value: unknown) => void
    const firstResponse = new Promise(resolve => { resolveFirst = resolve })
    const clickedWorkspace = { project: { id: 'clicked', name: 'Clicado', source: 'Local', sync_status: 'local', updated_at: '2026-08-27T00:00:00Z' }, tasks: [], dependencies: [], stats: { progress: 0, completed: 0, total: 0, critical: 0 } }
    const fetch = vi.fn()
      .mockReturnValueOnce(firstResponse)
      .mockResolvedValueOnce({ ok: true, json: async () => workspaceResponse(clickedWorkspace) })
    vi.stubGlobal('fetch', fetch)

    const restored = store.load('restored')
    const clicked = store.load('clicked')
    resolveFirst({ ok: false, status: 404 })
    await Promise.all([restored, clicked])

    expect(fetch).toHaveBeenNthCalledWith(1, '/api/v1/projects/restored/workspace')
    expect(fetch).toHaveBeenNthCalledWith(2, '/api/v1/projects/clicked/workspace')
    expect(store.workspace?.project.id).toBe('clicked')
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

  it('persists the successfully loaded project for restoration after refresh', async () => {
    const store = useWorkspaceStore()
    const project = { id: 'persisted-project', name: 'Projeto', source: 'Local', sync_status: 'local', updated_at: '2026-08-26T00:00:00Z' }
    const workspace = { project, tasks: [], dependencies: [], stats: { progress: 0, completed: 0, total: 0, critical: 0 } }
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => workspaceResponse(workspace) }))
    const storage = new Map<string, string>()
    vi.stubGlobal('localStorage', { getItem: (key: string) => storage.get(key) ?? null, setItem: (key: string, value: string) => storage.set(key, value), removeItem: (key: string) => storage.delete(key) })

    await store.load(project.id)

    expect(storage.get('ganttist.active-project-id')).toBe(project.id)
  })
})
