// @vitest-environment jsdom
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { afterEach, describe, expect, it, vi } from 'vitest'
import App from './App.vue'
import CalendarPanel from './CalendarPanel.vue'

const firstWorkspace = { project: { id: 'p1', name: 'Projeto A', source: 'Todoist', sync_status: 'synced', updated_at: '2026-08-17T00:00:00Z' }, tasks: [{ id: 'section', title: 'Grupo', kind: 'section', level: 0, has_children: true, start: null, finish: null, considered_start: '2026-08-17', considered_deadline: '2026-08-18', completed: false, progress: 0, status: 'opened', critical: false }, { id: 'child', title: 'Subtarefa', kind: 'task', level: 1, parent_id: 'section', start: '2026-08-17', finish: '2026-08-17', considered_start: '2026-08-17', considered_deadline: '2026-08-17', completed: false, progress: 0, status: 'opened', critical: false, comment_count: 3 }], dependencies: [], stats: { progress: 0, completed: 0, total: 1, critical: 0, opened: 1, blocked: 0, scheduled: 0, late: 0, without_dates: 0 } }
const secondWorkspace = { ...firstWorkspace, project: { ...firstWorkspace.project, id: 'p2', name: 'Projeto B' }, tasks: [{ ...firstWorkspace.tasks[0], title: 'Grupo atualizado' }, firstWorkspace.tasks[1]] }
const selectionWorkspace = { ...firstWorkspace, tasks: [firstWorkspace.tasks[0], { ...firstWorkspace.tasks[1], id: 'parent', title: 'Tarefa pai', description: 'Descrição do agrupador não exibida', priority: 4, has_children: true }, { ...firstWorkspace.tasks[1], description: 'Descrição da tarefa folha', priority: 2, level: 2, parent_id: 'parent' }, { ...firstWorkspace.tasks[1], id: 'sibling', title: 'Outra tarefa', description: 'Descrição da tarefa P4', priority: 1 }] }
const chainWorkspace = { ...firstWorkspace, tasks: [
  { ...firstWorkspace.tasks[1], id: 'a', title: 'A', level: 0, parent_id: null, has_children: true },
  { ...firstWorkspace.tasks[1], id: 'b', title: 'B', level: 1, parent_id: 'a', has_children: true },
  { ...firstWorkspace.tasks[1], id: 'c', title: 'C', level: 2, parent_id: 'b', has_children: true },
  { ...firstWorkspace.tasks[1], id: 'd', title: 'D', level: 3, parent_id: 'c', has_children: false },
] }
const routedWorkspace = { ...firstWorkspace, tasks: [
  { ...firstWorkspace.tasks[1], id: 'a', title: 'A', level: 0, parent_id: null, has_children: true },
  { ...firstWorkspace.tasks[1], id: 'b', title: 'B', level: 1, parent_id: 'a', has_children: true },
  { ...firstWorkspace.tasks[1], id: 'c', title: 'C', level: 2, parent_id: 'b', has_children: true },
  { ...firstWorkspace.tasks[1], id: 'c1', title: 'C1', level: 3, parent_id: 'c', has_children: false },
  { ...firstWorkspace.tasks[1], id: 'c2', title: 'C2', level: 3, parent_id: 'c', has_children: false },
  { ...firstWorkspace.tasks[1], id: 'c3', title: 'C3', level: 3, parent_id: 'c', has_children: false },
] }
const relationWorkspace = { ...firstWorkspace, tasks: [
  { ...firstWorkspace.tasks[1], id: 'current', title: 'Tarefa atual', description: 'Descrição nativa', priority: 3, assignee_id: 'user-1', level: 0, parent_id: null },
  { ...firstWorkspace.tasks[1], id: 'predecessor', title: 'Predecessora com um título bastante extenso para truncamento', level: 0, parent_id: null },
  { ...firstWorkspace.tasks[1], id: 'dependent', title: 'Dependente com outro título igualmente extenso', level: 0, parent_id: null },
  { ...firstWorkspace.tasks[1], id: 'available', title: 'Revisar documentação técnica', level: 0, parent_id: null },
], dependencies: [
  { id: 'incoming', from: 'predecessor', to: 'current', type: 'FS', critical: false },
  { id: 'outgoing', from: 'current', to: 'dependent', type: 'SS', critical: false },
] }
const priorityWorkspace = { ...firstWorkspace, tasks: [
  { ...firstWorkspace.tasks[1], id: 'priority-1', title: 'Urgente', description: 'Descrição urgente', level: 0, parent_id: null, priority: 4 },
  { ...firstWorkspace.tasks[1], id: 'priority-2', title: 'Alta', description: '', level: 0, parent_id: null, priority: 3 },
  { ...firstWorkspace.tasks[1], id: 'priority-3', title: 'Normal', level: 0, parent_id: null, priority: 2 },
  { ...firstWorkspace.tasks[1], id: 'priority-4', title: 'Sem marcador', description: 'Descrição sem marcador', level: 0, parent_id: null, priority: 1 },
] }

class FakeEventSource {
  listeners = new Map<string, () => void>()
  onerror: (() => void) | null = null
  addEventListener(type: string, listener: () => void) { this.listeners.set(type, listener) }
  close() {}
}

describe('workspace interaction', () => {
  afterEach(() => { sessionStorage.clear(); localStorage.clear(); vi.restoreAllMocks(); vi.unstubAllGlobals() })

  it('explains an expired session after reloading an authenticated tab', async () => {
    sessionStorage.setItem('ganttist.authenticated-session', '1')
    const fetch = vi.fn(async (url: string) => {
      if (url === '/api/v1/me') return { ok: false, status: 401 }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    window.fetch = fetch as unknown as typeof window.fetch
    vi.stubGlobal('EventSource', FakeEventSource)

    const wrapper = mount(App, { global: { plugins: [createPinia()], stubs: { AccountPanel: true, CalendarPanel: true, HistoryPanel: true, TodoistSetup: true } } })
    await flushPromises()

    expect(wrapper.text()).toContain('Sua sessão expirou')
    expect(wrapper.text()).toContain('Entre no Ganttist')
    wrapper.unmount()
  })

  it('keeps the SPA open while selecting a project and reconciling its hierarchy', async () => {
    let selected = false
    const fetch = vi.fn(async (url: string, options?: RequestInit) => {
      if (url === '/api/v1/me') return { ok: true, json: async () => ({ user: { id: 'u1', name: 'Pessoa', email: 'pessoa@example.test' } }) }
      if (url === '/api/v1/todoist/status') return { ok: true, json: async () => ({ connected: true, project: true, sync_state: 'synced', pending_operations: 0, conflict_operations: 0 }) }
      if (url === '/api/v1/workspace') return { ok: true, json: async () => ({ data: selected ? secondWorkspace : firstWorkspace }) }
      if (url === '/api/v1/todoist/projects') return { ok: true, json: async () => ({ data: [{ id: 'p2', name: 'Projeto B' }] }) }
      if (url === '/api/v1/todoist/project' && options?.method === 'POST') { selected = true; return { ok: true, json: async () => ({ data: { id: 'p2' } }) } }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    window.fetch = fetch as unknown as typeof window.fetch
    vi.stubGlobal('EventSource', FakeEventSource)

    const wrapper = mount(App, { global: { plugins: [createPinia()], stubs: { AccountPanel: true, CalendarPanel: true, HistoryPanel: true, TodoistSetup: true, AuthGate: true } } })
    await flushPromises()

    expect(wrapper.text()).toContain('Grupo')
    expect(wrapper.text()).toContain('Subtarefa')
    expect(wrapper.get('.settings-trigger').attributes('aria-label')).toBe('Abrir configurações do projeto')
    expect(wrapper.find('.settings-trigger svg').exists()).toBe(true)
    expect(wrapper.get('.settings-trigger').attributes('aria-expanded')).toBe('false')
    await wrapper.get('.settings-trigger').trigger('click')
    expect(wrapper.get('.settings-trigger').attributes('aria-expanded')).toBe('true')
    wrapper.getComponent(CalendarPanel).vm.$emit('notify', { kind: 'success', message: 'Configurações de automação salvas.' })
    await wrapper.vm.$nextTick()
    expect(wrapper.get('.toast').classes()).toContain('success')
    expect(wrapper.get('.toast').text()).toContain('Configurações de automação salvas.')
    expect(wrapper.get('.toast').attributes('role')).toBe('status')
    await wrapper.get('.project-switcher > button').trigger('click')
    await flushPromises()
    await wrapper.get('.project-menu button').trigger('click')
    await flushPromises()

    expect(fetch).toHaveBeenCalledWith('/api/v1/todoist/project', expect.objectContaining({ method: 'POST' }))
    expect(wrapper.text()).toContain('Projeto B')
    expect(wrapper.text()).toContain('Grupo atualizado')
    expect(wrapper.exists()).toBe(true)
    wrapper.unmount()
  })

  it('moves focus to task search with Command or Control K', async () => {
    const fetch = vi.fn(async (url: string) => {
      if (url === '/api/v1/me') return { ok: true, json: async () => ({ user: { id: 'u1', name: 'Pessoa', email: 'pessoa@example.test' } }) }
      if (url === '/api/v1/todoist/status') return { ok: true, json: async () => ({ connected: true, project: true, sync_state: 'synced', pending_operations: 0, conflict_operations: 0 }) }
      if (url === '/api/v1/workspace') return { ok: true, json: async () => ({ data: firstWorkspace }) }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    window.fetch = fetch as unknown as typeof window.fetch
    vi.stubGlobal('EventSource', FakeEventSource)

    const wrapper = mount(App, { attachTo: document.body, global: { plugins: [createPinia()], stubs: { AccountPanel: true, CalendarPanel: true, HistoryPanel: true, TodoistSetup: true, AuthGate: true } } })
    await flushPromises()
    const shortcut = new KeyboardEvent('keydown', { key: 'k', metaKey: true, cancelable: true })
    window.dispatchEvent(shortcut)

    expect(shortcut.defaultPrevented).toBe(true)
    expect(document.activeElement).toBe(wrapper.get('.search input').element)
    wrapper.unmount()
  })

  it('opens the task context menu and completes the task through Todoist', async () => {
    const fetch = vi.fn(async (url: string, options?: RequestInit) => {
      if (url === '/api/v1/me') return { ok: true, json: async () => ({ user: { id: 'u1', name: 'Pessoa', email: 'pessoa@example.test' } }) }
      if (url === '/api/v1/todoist/status') return { ok: true, json: async () => ({ connected: true, project: true, sync_state: 'synced', pending_operations: 0, conflict_operations: 0 }) }
      if (url === '/api/v1/workspace') return { ok: true, json: async () => ({ data: firstWorkspace }) }
      if (url === '/api/v1/tasks/child/completion' && options?.method === 'PATCH') return { ok: true, json: async () => ({ data: { task_id: 'child', completed: true } }) }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    window.fetch = fetch as unknown as typeof window.fetch
    vi.stubGlobal('EventSource', FakeEventSource)

    const wrapper = mount(App, { attachTo: document.body, global: { plugins: [createPinia()], stubs: { AccountPanel: true, CalendarPanel: true, HistoryPanel: true, TodoistSetup: true, AuthGate: true } } })
    await flushPromises()
    wrapper.get('.task-row[data-task-id="child"]').element.dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true, clientX: 120, clientY: 180 }))
    await wrapper.vm.$nextTick()
    const menu = document.querySelector<HTMLElement>('.task-context-menu')

    expect(menu?.textContent).toContain('Concluir tarefa')
    menu?.querySelector<HTMLButtonElement>('button')?.click()
    await flushPromises()

    expect(fetch).toHaveBeenCalledWith('/api/v1/tasks/child/completion', expect.objectContaining({ method: 'PATCH' }))
    expect(JSON.parse(String(fetch.mock.calls.find(([url]) => url === '/api/v1/tasks/child/completion')?.[1]?.body))).toMatchObject({ completed: true })
    wrapper.unmount()
  })

  it('closes the task context menu before reporting a Todoist completion error', async () => {
    const fetch = vi.fn(async (url: string, options?: RequestInit) => {
      if (url === '/api/v1/me') return { ok: true, json: async () => ({ user: { id: 'u1', name: 'Pessoa', email: 'pessoa@example.test' } }) }
      if (url === '/api/v1/todoist/status') return { ok: true, json: async () => ({ connected: true, project: true, sync_state: 'synced', pending_operations: 0, conflict_operations: 0 }) }
      if (url === '/api/v1/workspace') return { ok: true, json: async () => ({ data: firstWorkspace }) }
      if (url === '/api/v1/tasks/child/completion' && options?.method === 'PATCH') return { ok: false, json: async () => ({ message: 'O Todoist não confirmou a alteração da tarefa. Tente novamente.' }) }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    window.fetch = fetch as unknown as typeof window.fetch
    vi.stubGlobal('EventSource', FakeEventSource)

    const wrapper = mount(App, { attachTo: document.body, global: { plugins: [createPinia()], stubs: { AccountPanel: true, CalendarPanel: true, HistoryPanel: true, TodoistSetup: true, AuthGate: true } } })
    await flushPromises()
    wrapper.get('.task-row[data-task-id="child"]').element.dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true, clientX: 120, clientY: 180 }))
    await wrapper.vm.$nextTick()
    document.querySelector<HTMLButtonElement>('.task-context-menu button')?.click()
    await flushPromises()

    expect(document.querySelector('.task-context-menu')).toBeNull()
    expect(wrapper.get('.toast').text()).toContain('O Todoist não confirmou a alteração da tarefa.')
    wrapper.unmount()
  })

  it('combines status filters as checkboxes and closes the menu after an outside click', async () => {
    const fetch = vi.fn(async (url: string) => {
      if (url === '/api/v1/me') return { ok: true, json: async () => ({ user: { id: 'u1', name: 'Pessoa', email: 'pessoa@example.test' } }) }
      if (url === '/api/v1/todoist/status') return { ok: true, json: async () => ({ connected: true, project: true, sync_state: 'synced', pending_operations: 0, conflict_operations: 0 }) }
      if (url === '/api/v1/workspace') return { ok: true, json: async () => ({ data: firstWorkspace }) }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    window.fetch = fetch as unknown as typeof window.fetch
    vi.stubGlobal('EventSource', FakeEventSource)

    const wrapper = mount(App, { global: { plugins: [createPinia()], stubs: { AccountPanel: true, CalendarPanel: true, HistoryPanel: true, TodoistSetup: true, AuthGate: true } } })
    await flushPromises()
    await wrapper.findAll('.commands button').find(button => button.text().includes('Filtros'))!.trigger('click')

    const popover = wrapper.get('.filter-popover')
    const group = popover.get('.filter-status-group')
    const parent = group.get('.filter-status-parent')
    const parentInput = parent.get('input').element as HTMLInputElement
    const children = group.findAll('.filter-status-children input')
    expect(parent.text()).toContain('Desbloqueadas')
    expect(parentInput.checked).toBe(true)
    expect(children.every(input => (input.element as HTMLInputElement).checked)).toBe(true)
    expect(popover.text().indexOf('Desbloqueadas')).toBeLessThan(popover.text().indexOf('Abertas'))
    expect(popover.text().indexOf('Atrasadas')).toBeLessThan(popover.text().indexOf('Bloqueadas'))

    await parent.get('input').trigger('change')
    expect(parentInput.checked).toBe(false)
    expect(children.every(input => !(input.element as HTMLInputElement).checked)).toBe(true)
    expect(wrapper.get('.commands .count').text()).toBe('2')
    expect(wrapper.text()).not.toContain('Subtarefa')

    await children[1].trigger('change')
    expect((children[1].element as HTMLInputElement).checked).toBe(true)
    expect(parentInput.indeterminate).toBe(true)
    expect(wrapper.get('.commands .count').text()).toBe('3')

    document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }))
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.filter-popover').exists()).toBe(false)
    wrapper.unmount()
  })

  it('returns to the login screen when an interaction detects an expired session', async () => {
    const fetch = vi.fn(async (url: string) => {
      if (url === '/api/v1/me') return { ok: true, status: 200, json: async () => ({ user: { id: 'u1', name: 'Pessoa', email: 'pessoa@example.test' } }) }
      if (url === '/api/v1/todoist/status') return { ok: true, status: 200, json: async () => ({ connected: true, project: true, sync_state: 'synced', pending_operations: 0, conflict_operations: 0 }) }
      if (url === '/api/v1/workspace') return { ok: true, status: 200, json: async () => ({ data: firstWorkspace }) }
      if (url === '/api/v1/todoist/projects') return { ok: false, status: 401 }
      throw new Error('Unexpected request '+url)
    })
    vi.stubGlobal('fetch', fetch)
    window.fetch = fetch as unknown as typeof window.fetch
    vi.stubGlobal('EventSource', FakeEventSource)

    const wrapper = mount(App, { global: { plugins: [createPinia()], stubs: { AccountPanel: true, CalendarPanel: true, HistoryPanel: true, TodoistSetup: true } } })
    await flushPromises()
    await wrapper.get('.project-switcher > button').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Sua sessão expirou')
    expect(wrapper.text()).toContain('Entre no Ganttist')
    wrapper.unmount()
  })

  it('renders ghosts until the user confirms the operation and then reconciles the workspace', async () => {
    let reconciled = false
    const fetch = vi.fn(async (url: string, options?: RequestInit) => {
      if (url === '/api/v1/me') return { ok: true, json: async () => ({ user: { id: 'u1', name: 'Pessoa', email: 'pessoa@example.test' } }) }
      if (url === '/api/v1/todoist/status') return { ok: true, json: async () => ({ connected: true, project: true, sync_state: 'synced', pending_operations: 0, conflict_operations: 0 }) }
      if (url === '/api/v1/workspace') return { ok: true, json: async () => ({ data: reconciled ? { ...firstWorkspace, tasks: [firstWorkspace.tasks[0], { ...firstWorkspace.tasks[1], start: '2026-08-20', finish: '2026-08-20', considered_start: '2026-08-20', considered_deadline: '2026-08-20' }] } : firstWorkspace }) }
      if (url === '/api/v1/schedule/simulate') return { ok: true, json: async () => ({ data: { command_id: 'cmd-1', changes: [{ task_id: 'child', start: '2026-08-20', finish: '2026-08-20' }] } }) }
      if (url === '/api/v1/schedule/apply' && options?.method === 'POST') return { ok: true, json: async () => ({ data: { operation_id: 'op-1', state: 'pending' } }) }
      if (url === '/api/v1/schedule/operations/op-1') { reconciled = true; return { ok: true, json: async () => ({ data: { operation_id: 'op-1', state: 'completed', items: [{ state: 'applied' }] } }) } }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    window.fetch = fetch as unknown as typeof window.fetch
    vi.stubGlobal('EventSource', FakeEventSource)
    vi.stubGlobal('crypto', { randomUUID: () => 'cmd-1' })

    const wrapper = mount(App, { global: { plugins: [createPinia()], stubs: { AccountPanel: true, CalendarPanel: true, HistoryPanel: true, TodoistSetup: true, AuthGate: true } } })
    await flushPromises()
    await wrapper.findAll('.task-row').find(row => row.text().includes('Subtarefa'))!.trigger('click')
    await wrapper.findAll('button').find(button => button.text().includes('Simular cenário'))!.trigger('click')
    await flushPromises()

    expect(wrapper.get('.task-ghost').text()).toContain('Prévia')
    expect(wrapper.get('.simulation-review').text()).toContain('Subtarefa: 2026-08-20 → 2026-08-20')
    await wrapper.findAll('.simulation-review button').find(button => button.text().includes('Confirmar operação'))!.trigger('click')
    await flushPromises()

    expect(fetch).toHaveBeenCalledWith('/api/v1/schedule/apply', expect.objectContaining({ method: 'POST' }))
    expect(wrapper.find('.task-ghost').exists()).toBe(false)
    expect(wrapper.get('.operation-status').text()).toContain('completed')
    expect(wrapper.get('.task-bar.task').attributes('style')).toContain('left: 714px')
    wrapper.unmount()
  })

  it('moves an empty timeblock ghost by whole days, cancels with Escape and persists a drop directly', async () => {
    let reconciled = false
    const dragWorkspace = { ...firstWorkspace, calendar: { timezone: 'America/Sao_Paulo', working_days: [1, 2, 3, 4, 5] }, tasks: [
      { ...firstWorkspace.tasks[1], id: 'planned', title: 'Planejada', level: 0, parent_id: null, start: '2026-08-17', finish: '2026-08-19', considered_start: '2026-08-17', considered_deadline: '2026-08-19' },
      { ...firstWorkspace.tasks[1], id: 'single-day', title: 'Sem prazo', level: 0, parent_id: null, start: '2026-08-18', finish: null, considered_start: '2026-08-18', considered_deadline: '2026-08-18' },
      { ...firstWorkspace.tasks[1], id: 'empty', title: 'Sem data', level: 0, parent_id: null, has_children: true, start: '', finish: '', considered_start: null, considered_deadline: null, status: 'opened' },
      { ...firstWorkspace.tasks[1], id: 'empty-child', title: 'Sem data filha', level: 1, parent_id: 'empty', start: null, finish: null, considered_start: null, considered_deadline: null, status: 'opened' },
    ] }
    const fetch = vi.fn(async (url: string, options?: RequestInit) => {
      if (url === '/api/v1/me') return { ok: true, json: async () => ({ user: { id: 'u1', name: 'Pessoa', email: 'pessoa@example.test' } }) }
      if (url === '/api/v1/todoist/status') return { ok: true, json: async () => ({ connected: true, project: true, sync_state: 'synced', pending_operations: 0, conflict_operations: 0 }) }
      if (url === '/api/v1/workspace') return { ok: true, json: async () => ({ data: reconciled ? { ...dragWorkspace, tasks: [{ ...dragWorkspace.tasks[0], start: '2026-08-19', finish: '2026-08-21', considered_start: '2026-08-19', considered_deadline: '2026-08-21' }, ...dragWorkspace.tasks.slice(1)] } : dragWorkspace }) }
      if (url === '/api/v1/tasks/planned/dates' && options?.method === 'PUT') { reconciled = true; return { ok: true, json: async () => ({ data: { task_id: 'planned', start: '2026-08-19', finish: '2026-08-21', deadline: '2026-08-21' } }) } }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    window.fetch = fetch as unknown as typeof window.fetch
    vi.stubGlobal('EventSource', FakeEventSource)
    vi.stubGlobal('crypto', { randomUUID: () => 'drag-command' })

    const wrapper = mount(App, { global: { plugins: [createPinia()], stubs: { AccountPanel: true, CalendarPanel: true, HistoryPanel: true, TodoistSetup: true, AuthGate: true } } })
    await flushPromises()
    const planned = wrapper.get('[aria-label^="Planejada:"]')
    const provisional = wrapper.get('[aria-label^="Sem data:"]')
    const provisionalLeaf = wrapper.get('[aria-label^="Sem data filha:"]')
    expect(planned.text()).toBe('')
    expect(provisional.text()).toBe('')
    expect(provisional.classes()).toContain('provisional')
    expect(provisional.attributes('style')).toContain('width: 42px')
    expect(provisional.attributes('style')?.match(/left: [^;]+/)?.[0]).toBe(wrapper.get('.today-line').attributes('style')?.match(/left: [^;]+/)?.[0])
    expect(provisionalLeaf.attributes('style')).toContain('width: 42px')
    expect(provisionalLeaf.attributes('style')?.match(/left: [^;]+/)?.[0]).toBe(wrapper.get('.today-line').attributes('style')?.match(/left: [^;]+/)?.[0])
    expect(wrapper.get('[aria-label^="Sem prazo:"]').attributes('style')).toContain('width: 42px')

    await planned.trigger('pointerdown', { clientX: 100 })
    window.dispatchEvent(new MouseEvent('pointermove', { clientX: 184, bubbles: true }))
    await wrapper.vm.$nextTick()
    expect(wrapper.get('.drag-ghost').attributes('style')).toContain('width: 126px')
    expect(wrapper.get('.drag-ghost').attributes('aria-label')).toContain('2026-08-19')
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.drag-ghost').exists()).toBe(false)
    expect(fetch.mock.calls.some(([url]) => url === '/api/v1/tasks/planned/dates')).toBe(false)

    await wrapper.get('[aria-label^="Planejada:"]').trigger('pointerdown', { clientX: 100 })
    window.dispatchEvent(new MouseEvent('pointermove', { clientX: 184, bubbles: true }))
    window.dispatchEvent(new MouseEvent('pointerup', { clientX: 184, bubbles: true }))
    await flushPromises()
    const request = fetch.mock.calls.find(([url]) => url === '/api/v1/tasks/planned/dates')
    expect(JSON.parse(String((request?.[1] as RequestInit).body))).toEqual({ intent: 'MOVE', start: '2026-08-19', commandId: 'drag-command' })
    expect(fetch.mock.calls.some(([url]) => url === '/api/v1/schedule/simulate')).toBe(false)
    expect(wrapper.get('[aria-label^="Planejada:"]').attributes('style')).toContain('width: 126px')
    await wrapper.findAll('.gantt-row').find(row => row.text().includes('Sem prazo'))!.trigger('dblclick')
    const dateInputs = wrapper.findAll<HTMLInputElement>('.drawer input[type="date"]')
    expect(dateInputs[0].element.value).toBe('2026-08-18')
    expect(dateInputs[1].element.value).toBe('')
    wrapper.unmount()
  })

  it('resizes a timeblock by snapped days and creates graphical dependency types with undo', async () => {
    let resized = false
    const gestureWorkspace = { ...firstWorkspace, calendar: { timezone: 'America/Sao_Paulo', working_days: [1, 2, 3, 4, 5] }, tasks: [
      { ...firstWorkspace.tasks[1], id: 'source', title: 'Origem', level: 0, parent_id: null, start: '2026-08-17', finish: '2026-08-19', considered_start: '2026-08-17', considered_deadline: '2026-08-19', earliest_start: null },
      { ...firstWorkspace.tasks[1], id: 'target', title: 'Destino', level: 0, parent_id: null, start: '2026-08-21', finish: null, considered_start: '2026-08-21', considered_deadline: '2026-08-21', earliest_start: '2026-08-19' },
    ], dependencies: [] }
    const fetch = vi.fn(async (url: string, options?: RequestInit) => {
      if (url === '/api/v1/me') return { ok: true, json: async () => ({ user: { id: 'u1', name: 'Pessoa', email: 'pessoa@example.test' } }) }
      if (url === '/api/v1/todoist/status') return { ok: true, json: async () => ({ connected: true, project: true, sync_state: 'synced', pending_operations: 0, conflict_operations: 0 }) }
      if (url === '/api/v1/workspace') return { ok: true, json: async () => ({ data: resized ? { ...gestureWorkspace, tasks: [{ ...gestureWorkspace.tasks[0], finish: '2026-08-21', considered_deadline: '2026-08-21' }, gestureWorkspace.tasks[1]] } : gestureWorkspace }) }
      if (url === '/api/v1/tasks/source/dates' && options?.method === 'PUT') { resized = true; return { ok: true, json: async () => ({ data: { task_id: 'source', start: '2026-08-17', deadline: '2026-08-21' } }) } }
      if (url === '/api/v1/tasks/target/dates' && options?.method === 'PUT') return { ok: true, json: async () => ({ data: { task_id: 'target', start: '2026-08-19', deadline: '2026-08-21' } }) }
      if (url === '/api/v1/dependencies' && options?.method === 'POST') return { ok: true, json: async () => ({ data: { id: 'dep-new', from: 'source', to: 'target', type: 'SF', critical: false } }) }
      if (url === '/api/v1/dependencies/dep-new' && options?.method === 'DELETE') return { ok: true, json: async () => ({ data: { id: 'dep-new' } }) }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    window.fetch = fetch as unknown as typeof window.fetch
    vi.stubGlobal('EventSource', FakeEventSource)
    vi.stubGlobal('crypto', { randomUUID: () => 'gesture-command' })

    const wrapper = mount(App, { global: { plugins: [createPinia()], stubs: { AccountPanel: true, CalendarPanel: true, HistoryPanel: true, TodoistSetup: true, AuthGate: true } } })
    await flushPromises()
    const sourceBar = wrapper.get('[aria-label^="Origem:"]')
    await sourceBar.get('.timeblock-grip.finish').trigger('pointerdown', { clientX: 100 })
    window.dispatchEvent(new MouseEvent('pointermove', { clientX: 184, bubbles: true }))
    await wrapper.vm.$nextTick()
    expect(wrapper.get('.drag-ghost.gesture-resize').attributes('style')).toContain('width: 210px')
    window.dispatchEvent(new MouseEvent('pointerup', { clientX: 184, bubbles: true }))
    await flushPromises()

    const resizeRequest = fetch.mock.calls.find(([url]) => url === '/api/v1/tasks/source/dates')
    expect(JSON.parse(String((resizeRequest?.[1] as RequestInit).body))).toEqual({ intent: 'RESIZE_END', deadline: '2026-08-21', commandId: 'gesture-command' })

    const targetBar = wrapper.get('[aria-label^="Destino:"]')
    await targetBar.get('.timeblock-grip.start').trigger('pointerdown', { clientX: 300 })
    window.dispatchEvent(new MouseEvent('pointermove', { clientX: 90, bubbles: true }))
    window.dispatchEvent(new MouseEvent('pointerup', { clientX: 90, bubbles: true }))
    await flushPromises()
    const startResizeRequest = fetch.mock.calls.find(([url]) => url === '/api/v1/tasks/target/dates')
    expect(JSON.parse(String((startResizeRequest?.[1] as RequestInit).body))).toEqual({ intent: 'RESIZE_START', start: '2026-08-19', deadline: '2026-08-21', commandId: 'gesture-command' })

    await wrapper.get('[aria-label="Conector de início de Origem"]').trigger('keydown', { key: 'Enter' })
    await wrapper.get('[aria-label="Conector de fim de Destino"]').trigger('keydown', { key: 'Enter' })
    await flushPromises()
    const dependencyRequest = fetch.mock.calls.find(([url, options]) => url === '/api/v1/dependencies' && (options as RequestInit)?.method === 'POST')
    expect(JSON.parse(String((dependencyRequest?.[1] as RequestInit).body))).toEqual({ from: 'source', to: 'target', type: 'SF', commandId: 'gesture-command' })
    expect(wrapper.get('.toast').text()).toContain('Desfazer')
    await wrapper.get('.toast button').trigger('click')
    await flushPromises()
    expect(fetch).toHaveBeenCalledWith('/api/v1/dependencies/dep-new', expect.objectContaining({ method: 'DELETE' }))
    wrapper.unmount()
  })

  it('separates the row cursor from checkbox selection and expands the hierarchy', async () => {
    const fetch = vi.fn(async (url: string) => {
      if (url === '/api/v1/me') return { ok: true, json: async () => ({ user: { id: 'u1', name: 'Pessoa', email: 'pessoa@example.test' } }) }
      if (url === '/api/v1/todoist/status') return { ok: true, json: async () => ({ connected: true, project: true, sync_state: 'synced', pending_operations: 0, conflict_operations: 0 }) }
      if (url === '/api/v1/workspace') return { ok: true, json: async () => ({ data: selectionWorkspace }) }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    window.fetch = fetch as unknown as typeof window.fetch
    vi.stubGlobal('EventSource', FakeEventSource)

    const wrapper = mount(App, { global: { plugins: [createPinia()], stubs: { AccountPanel: true, CalendarPanel: true, HistoryPanel: true, TodoistSetup: true, AuthGate: true } } })
    await flushPromises()
    const rows = wrapper.findAll('.task-row')

    expect(rows[0].find('.task-priority-flag').exists()).toBe(false)
    expect(rows[0].find('.task-description').exists()).toBe(false)
    expect(rows[1].find('.task-priority-flag').exists()).toBe(false)
    expect(rows[1].find('.task-description').exists()).toBe(false)
    expect(rows[2].get('.task-priority-flag').classes()).toContain('p3')
    expect(rows[2].get('.task-priority-flag').attributes('aria-label')).toBe('Prioridade P3')
    expect(rows[2].get('.task-terminal-slot').classes()).toContain('has-priority')
    expect(rows[2].get('.task-terminal-slot').find('.task-priority-flag').exists()).toBe(true)
    expect(rows[2].get('.task-title-line').find('.task-priority-flag').exists()).toBe(false)
    expect(rows[2].get('.task-description').text()).toBe('Descrição da tarefa folha')
    expect(rows[2].get('.task-description').attributes('title')).toBe('Descrição da tarefa folha')
    expect(rows[3].find('.task-priority-flag').exists()).toBe(false)
    expect(rows[3].get('.task-terminal-slot').classes()).not.toContain('has-priority')
    expect(rows[3].get('.task-description').text()).toBe('Descrição da tarefa P4')

    await rows[0].trigger('click')
    expect(wrapper.find('.drawer.open').exists()).toBe(false)
    expect(wrapper.find('.gantt-card > .selection-bar').exists()).toBe(false)
    expect(rows[0].classes()).toContain('cursor')
    expect(wrapper.findAll('.bar-lane')[0].classes()).not.toContain('selected')
    await rows[0].trigger('keydown', { key: ' ' })
    expect(wrapper.find('.gantt-card > .selection-bar').exists()).toBe(false)

    const checkboxes = wrapper.findAll<HTMLInputElement>('.task-check')
    expect(checkboxes).toHaveLength(3)
    expect(rows[0].find('.task-check').exists()).toBe(false)
    expect(rows[0].find('.task-selection-slot').exists()).toBe(true)
    expect(rows[0].attributes('aria-selected')).toBeUndefined()
    expect(rows[0].find('.task-tree-content').exists()).toBe(true)
    await checkboxes[0].trigger('click')
    expect(wrapper.get('.gantt-card > .selection-bar').text()).toContain('1 tarefa(s) selecionada(s)')
    expect((checkboxes[0].element as HTMLInputElement).checked).toBe(true)
    expect(wrapper.findAll('.bar-lane')[1].classes()).toContain('selected')

    const shiftClick = new MouseEvent('click', { shiftKey: true, bubbles: true, cancelable: true })
    checkboxes[2].element.dispatchEvent(shiftClick)
    await flushPromises()
    expect(shiftClick.defaultPrevented).toBe(false)
    expect(wrapper.get('.gantt-card > .selection-bar').text()).toContain('3 tarefa(s) selecionada(s)')
    expect(wrapper.findAll('.task-check').every(checkbox => (checkbox.element as HTMLInputElement).checked)).toBe(true)
    expect(wrapper.findAll('.task-name')[1].find('.task-selection-slot .task-check').exists()).toBe(true)
    expect(rows[1].text()).not.toContain('#CHILD')

    expect(wrapper.findAll('.gantt-tree-toggle')).toHaveLength(2)
    expect(rows[1].find('.task-check').exists()).toBe(true)
    expect(rows[1].find('.tree-slot').exists()).toBe(true)
    expect(rows[1].get('.task-tree-content').classes()).toContain('has-expanded-children')
    expect(rows[1].get('.tree-slot.current-branch').find('.tree-sibling-continuation').exists()).toBe(true)
    expect(rows[1].get('.tree-slot.current-branch').get('.tree-sibling-continuation').attributes('d')).toBe('M11 44 V100')
    expect(rows[2].get('.tree-slot.current-branch').find('.tree-sibling-continuation').exists()).toBe(false)
    expect(rows[2].find('.gantt-tree-toggle-spacer').exists()).toBe(true)
    expect(rows[2].get('.gantt-tree-toggle-spacer').get('path').attributes('d')).toBe('M0 50 H6')
    expect(rows[3].get('.gantt-tree-toggle-spacer').get('path').attributes('d')).toBe('M0 50 H22')
    await wrapper.findAll('.gantt-row')[2].trigger('mouseenter')
    expect(rows[0].classes()).toContain('hierarchy-ancestor')
    expect(rows[1].classes()).toContain('hierarchy-ancestor')
    expect(rows[1].find('.tree-segment-active').exists()).toBe(true)
    expect(rows[2].find('.tree-segment-active').exists()).toBe(true)
    expect(rows[2].find('.tree-segment-base.active').exists()).toBe(false)

    const collapse = wrapper.findAll('.gantt-tree-toggle')[0]
    expect(collapse.attributes('aria-expanded')).toBe('true')
    expect(collapse.text()).toBe('›')
    expect(collapse.attributes('title')).toBe('Recolher subitens')
    expect(collapse.get('.gantt-tree-toggle-chevron').classes()).toContain('expanded')
    await collapse.trigger('click')
    expect(wrapper.findAll('.task-row')).toHaveLength(1)
    expect(wrapper.get('.gantt-tree-toggle').attributes('aria-expanded')).toBe('false')
    expect(wrapper.get('.gantt-tree-toggle-chevron').classes()).not.toContain('expanded')
    await wrapper.get('.gantt-tree-toggle').trigger('click')
    expect(wrapper.findAll('.task-row')).toHaveLength(4)
    await wrapper.findAll('.gantt-tree-toggle')[1].trigger('click')
    expect(wrapper.findAll('.task-row')).toHaveLength(3)
    wrapper.unmount()
  })

  it('maps Todoist priorities to leaf flags and keeps P4 unmarked', async () => {
    const fetch = vi.fn(async (url: string) => {
      if (url === '/api/v1/me') return { ok: true, json: async () => ({ user: { id: 'u1', name: 'Pessoa', email: 'pessoa@example.test' } }) }
      if (url === '/api/v1/todoist/status') return { ok: true, json: async () => ({ connected: true, project: true, sync_state: 'synced', pending_operations: 0, conflict_operations: 0 }) }
      if (url === '/api/v1/workspace') return { ok: true, json: async () => ({ data: priorityWorkspace }) }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    window.fetch = fetch as unknown as typeof window.fetch
    vi.stubGlobal('EventSource', FakeEventSource)

    const wrapper = mount(App, { global: { plugins: [createPinia()], stubs: { AccountPanel: true, CalendarPanel: true, HistoryPanel: true, TodoistSetup: true, AuthGate: true } } })
    await flushPromises()
    const rows = wrapper.findAll('.task-row')

    expect(rows[0].get('.task-priority-flag').classes()).toContain('p1')
    expect(rows[1].get('.task-priority-flag').classes()).toContain('p2')
    expect(rows[2].get('.task-priority-flag').classes()).toContain('p3')
    expect(rows[3].find('.task-priority-flag').exists()).toBe(false)
    expect(rows.every(row => row.find('.task-terminal-slot').exists())).toBe(true)
    expect(rows.slice(0, 3).every(row => row.get('.task-terminal-slot').find('.task-priority-flag').exists())).toBe(true)
    expect(rows.every(row => !row.get('.task-title-line').find('.task-priority-flag').exists())).toBe(true)
    expect(rows[0].get('.task-description').text()).toBe('Descrição urgente')
    expect(rows[1].find('.task-description').exists()).toBe(false)
    expect(rows[3].get('.task-description').text()).toBe('Descrição sem marcador')
    wrapper.unmount()
  })

  it('keeps task cells and timeblocks in one row and handles keyboard commands from the chart', async () => {
    const fetch = vi.fn(async (url: string) => {
      if (url === '/api/v1/me') return { ok: true, json: async () => ({ user: { id: 'u1', name: 'Pessoa', email: 'pessoa@example.test' } }) }
      if (url === '/api/v1/todoist/status') return { ok: true, json: async () => ({ connected: true, project: true, sync_state: 'synced', pending_operations: 0, conflict_operations: 0 }) }
      if (url === '/api/v1/workspace') return { ok: true, json: async () => ({ data: firstWorkspace }) }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    window.fetch = fetch as unknown as typeof window.fetch
    vi.stubGlobal('EventSource', FakeEventSource)

    const wrapper = mount(App, { attachTo: document.body, global: { plugins: [createPinia()], stubs: { AccountPanel: true, CalendarPanel: true, HistoryPanel: true, TodoistSetup: true, AuthGate: true } } })
    await flushPromises()

    expect(wrapper.find('.rows-left').exists()).toBe(false)
    expect(wrapper.find('.timeline-scroll').exists()).toBe(false)
    expect(wrapper.findAll('.gantt-row')).toHaveLength(2)
    for (const row of wrapper.findAll('.gantt-row')) {
      expect(row.find('.task-row').exists()).toBe(true)
      expect(row.find('.bar-lane').exists()).toBe(true)
    }
    const sectionRow = wrapper.findAll('.gantt-row')[0]
    expect(sectionRow.get('.task-assignee').text()).toBe('')
    expect(sectionRow.get('.task-status').text()).toBe('')
    expect(sectionRow.get('.task-bar').classes()).toContain('summary')
    expect(sectionRow.get('.task-bar').classes()).not.toContain('provisional')
    expect(sectionRow.find('.group-line').exists()).toBe(true)

    await wrapper.findAll('.task-bar')[0].trigger('click')
    const chart = wrapper.get('.gantt-scroll')
    const scrollBy = vi.fn()
    Object.defineProperty(chart.element, 'scrollBy', { configurable: true, value: scrollBy })
    Object.defineProperty(chart.element, 'clientHeight', { configurable: true, value: 620 })
    Object.defineProperty(chart.element, 'clientWidth', { configurable: true, value: 1200 })
    await chart.trigger('focus')
    await chart.trigger('keydown', { key: 'ArrowDown' })
    await chart.trigger('keydown', { key: ' ' })

    expect(wrapper.get('.gantt-card > .selection-bar').text()).toContain('1 tarefa(s) selecionada(s)')
    expect(wrapper.findAll('.gantt-row')[1].find('.task-row').classes()).toContain('cursor')

    await chart.trigger('keydown', { key: 'ArrowRight' })
    expect(scrollBy).toHaveBeenCalledWith(expect.objectContaining({ left: 126 }))

    const shiftTab = new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true, cancelable: true })
    chart.element.dispatchEvent(shiftTab)
    await flushPromises()
    expect(shiftTab.defaultPrevented).toBe(true)
    expect((document.activeElement as HTMLElement).dataset.taskId).toBe('child')

    const space = new KeyboardEvent('keydown', { key: ' ', bubbles: true, cancelable: true })
    chart.element.dispatchEvent(space)
    expect(space.defaultPrevented).toBe(true)
    wrapper.unmount()
  })

  it('selects optional columns and resizes the task column within persisted viewport limits', async () => {
    vi.stubGlobal('innerWidth', 1600)
    vi.spyOn(HTMLElement.prototype, 'clientHeight', 'get').mockReturnValue(620)
    vi.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockReturnValue(1200)
    const columnWorkspace = { ...firstWorkspace, tasks: [{ ...firstWorkspace.tasks[1], level: 0, parent_id: null }] }
    const fetch = vi.fn(async (url: string) => {
      if (url === '/api/v1/me') return { ok: true, json: async () => ({ user: { id: 'u1', name: 'Pessoa', email: 'pessoa@example.test' } }) }
      if (url === '/api/v1/todoist/status') return { ok: true, json: async () => ({ connected: true, project: true, sync_state: 'synced', pending_operations: 0, conflict_operations: 0 }) }
      if (url === '/api/v1/workspace') return { ok: true, json: async () => ({ data: columnWorkspace }) }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    window.fetch = fetch as unknown as typeof window.fetch
    vi.stubGlobal('EventSource', FakeEventSource)

    const wrapper = mount(App, { attachTo: document.body, global: { plugins: [createPinia()], stubs: { AccountPanel: true, CalendarPanel: true, HistoryPanel: true, TodoistSetup: true, AuthGate: true, teleport: true } } })
    await flushPromises()

    expect(wrapper.get('.gantt-card').attributes('style')).toContain('grid-template-columns: 430px minmax(0,1fr)')
    const resizer = wrapper.get('.task-column-resizer')
    expect(resizer.attributes('role')).toBe('separator')
    expect(resizer.attributes('aria-valuemin')).toBe('278')
    expect(resizer.attributes('aria-valuemax')).toBe('400')

    await wrapper.get('.column-picker-button').trigger('click')
    const choices = wrapper.findAll<HTMLInputElement>('.column-picker-popover input')
    expect(choices).toHaveLength(6)
    expect(choices[0].element.disabled).toBe(true)
    expect(choices[0].element.checked).toBe(true)
    await choices[2].setValue(false)
    await choices[3].setValue(true)
    await choices[5].setValue(true)
    await wrapper.vm.$nextTick()

    expect(wrapper.get('.gantt-head-left').text()).toContain('INÍCIO')
    expect(wrapper.get('.gantt-head-left').text()).toContain('COMENT.')
    expect(wrapper.get('.gantt-head-left').text()).not.toContain('STATUS')
    const rowTexts = wrapper.findAll('.gantt-row').map(row => row.text())
    expect(rowTexts.some(text => text.includes('Subtarefa'))).toBe(true)
    const childRow = wrapper.findAll('.gantt-row').find(row => row.text().includes('Subtarefa'))!
    expect(childRow.get('.task-date').text()).toBe('17/08/2026')
    expect(childRow.get('.task-comments').text()).toContain('3')
    expect(wrapper.get('.gantt-card').attributes('style')).toContain('grid-template-columns: 504px minmax(0,1fr)')

    await resizer.trigger('pointerdown', { clientX: 278 })
    window.dispatchEvent(new MouseEvent('pointermove', { clientX: 600, bubbles: true }))
    window.dispatchEvent(new MouseEvent('pointerup', { bubbles: true }))
    await wrapper.vm.$nextTick()
    expect(resizer.attributes('aria-valuenow')).toBe('400')
    expect(wrapper.get('.gantt-head-left').attributes('style')).toContain('400px 58px 92px 76px')

    await resizer.trigger('keydown', { key: 'Home' })
    expect(resizer.attributes('aria-valuenow')).toBe('278')
    expect(localStorage.getItem('ganttist.task-column-width')).toBe('278')
    expect(JSON.parse(localStorage.getItem('ganttist.workspace-columns')!).status).toBe(false)
    wrapper.unmount()

    vi.stubGlobal('fetch', fetch)
    window.fetch = fetch as unknown as typeof window.fetch
    const reloaded = mount(App, { global: { plugins: [createPinia()], stubs: { AccountPanel: true, CalendarPanel: true, HistoryPanel: true, TodoistSetup: true, AuthGate: true, teleport: true } } })
    await flushPromises()
    expect(reloaded.get('.gantt-head-left').text()).toContain('INÍCIO')
    expect(reloaded.get('.gantt-head-left').text()).toContain('COMENT.')
    expect(reloaded.get('.gantt-head-left').text()).not.toContain('STATUS')
    reloaded.unmount()
  })

  it('opens the task editor on double click and protects dirty drafts', async () => {
    let saveSucceeds = false
    const fetch = vi.fn(async (url: string) => {
      if (url === '/api/v1/me') return { ok: true, json: async () => ({ user: { id: 'u1', name: 'Pessoa', email: 'pessoa@example.test' } }) }
      if (url === '/api/v1/todoist/status') return { ok: true, json: async () => ({ connected: true, project: true, sync_state: 'synced', pending_operations: 0, conflict_operations: 0 }) }
      if (url === '/api/v1/workspace') return { ok: true, json: async () => ({ data: selectionWorkspace }) }
      if (url === '/api/v1/tasks/child') return { ok: saveSucceeds }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    window.fetch = fetch as unknown as typeof window.fetch
    vi.stubGlobal('EventSource', FakeEventSource)
    vi.stubGlobal('innerWidth', 1400)

    const wrapper = mount(App, { attachTo: document.body, global: { plugins: [createPinia()], stubs: { AccountPanel: true, CalendarPanel: true, HistoryPanel: true, TodoistSetup: true, AuthGate: true } } })
    await flushPromises()
    const rows = wrapper.findAll('.task-row')

    await rows[1].trigger('dblclick')
    expect(wrapper.get('.drawer').classes()).toContain('open')
    expect(wrapper.find('.scrim').exists()).toBe(false)
    expect(wrapper.get('.drawer').attributes('aria-modal')).toBe('false')

    const title = wrapper.get<HTMLInputElement>('.drawer-body input')
    await title.setValue('Tarefa pai alterada')
    document.body.click()
    await flushPromises()
    expect(wrapper.get('.drawer').classes()).toContain('open')

    await wrapper.get('.drawer-close').trigger('click')
    expect(wrapper.get('.unsaved-confirm').attributes('role')).toBe('alertdialog')
    expect(wrapper.text()).toContain('Alterações não salvas')
    await wrapper.get('.continue-editing').trigger('click')
    expect(wrapper.find('.unsaved-confirm').exists()).toBe(false)

    await wrapper.get('.drawer-cancel').trigger('click')
    await wrapper.get('.discard-changes').trigger('click')
    expect(wrapper.get('.drawer').classes()).not.toContain('open')
    expect(wrapper.text()).toContain('Tarefa pai')
    expect(wrapper.text()).not.toContain('Tarefa pai alterada')

    await rows[1].trigger('dblclick')
    await wrapper.get('.drawer-pin').trigger('click')
    expect(wrapper.get('.app-shell').classes()).toContain('editor-pinned')
    expect(wrapper.get('.drawer').classes()).toContain('pinned')
    const separator = wrapper.get('.drawer-resizer')
    expect(separator.attributes('role')).toBe('separator')
    await separator.trigger('pointerdown', { clientX: 900 })
    window.dispatchEvent(new MouseEvent('pointermove', { clientX: 850 }))
    window.dispatchEvent(new MouseEvent('pointerup'))
    await flushPromises()
    expect(separator.attributes('aria-valuenow')).toBe('440')
    await separator.trigger('keydown', { key: 'End' })
    expect(separator.attributes('aria-valuenow')).toBe('700')
    expect(wrapper.get('.app-shell').attributes('style')).toContain('--task-editor-width: 700px')

    await wrapper.get<HTMLInputElement>('.drawer-body input').setValue('Troca protegida')
    await rows[2].trigger('dblclick')
    expect(wrapper.find('.unsaved-confirm').exists()).toBe(true)
    await wrapper.get('.discard-changes').trigger('click')
    expect(wrapper.get('.drawer h2').text()).toBe('Subtarefa')

    await wrapper.get<HTMLInputElement>('.drawer-body input').setValue('Subtarefa revisada')
    const unload = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(unload)
    expect(unload.defaultPrevented).toBe(true)
    await wrapper.get('.drawer-close').trigger('click')
    await wrapper.get('.save-before-close').trigger('click')
    await flushPromises()
    expect(wrapper.get('.drawer').classes()).toContain('open')
    expect(wrapper.text()).toContain('Não foi possível salvar a tarefa no Todoist')

    saveSucceeds = true
    await wrapper.get('.save-before-close').trigger('click')
    await flushPromises()
    expect(wrapper.get('.drawer').classes()).not.toContain('open')
    expect(fetch).toHaveBeenCalledWith('/api/v1/tasks/child', expect.objectContaining({ method: 'PUT' }))
    wrapper.unmount()
  })

  it('separates predecessors from dependents and exposes full related task titles', async () => {
    let commentCreated = false
    const fetch = vi.fn(async (url: string, options?: RequestInit) => {
      if (url === '/api/v1/me') return { ok: true, json: async () => ({ user: { id: 'u1', name: 'Pessoa', email: 'pessoa@example.test' } }) }
      if (url === '/api/v1/todoist/status') return { ok: true, json: async () => ({ connected: true, project: true, sync_state: 'synced', pending_operations: 0, conflict_operations: 0 }) }
      if (url === '/api/v1/workspace') return { ok: true, json: async () => ({ data: relationWorkspace }) }
      if (url === '/api/v1/tasks/current/editor-context') return { ok: true, json: async () => ({ data: { collaborators: [{ id: 'user-1', name: 'Ada Lovelace' }], comments: commentCreated ? [{ id: 'comment-2', content: 'Novo comentário', author_id: 'user-1', editable: true }] : [{ id: 'comment-1', content: 'Comentário existente', author_id: 'user-1', editable: true }] } }) }
      if (url === '/api/v1/tasks/current/comments' && options?.method === 'POST') { commentCreated = true; return { ok: true, json: async () => ({ data: { id: 'comment-2' } }) } }
      if (url === '/api/v1/dependencies' && options?.method === 'POST') return { ok: true, json: async () => ({ data: { id: 'new-relation' } }) }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    window.fetch = fetch as unknown as typeof window.fetch
    vi.stubGlobal('EventSource', FakeEventSource)
    vi.stubGlobal('crypto', { randomUUID: () => 'editor-command' })

    const wrapper = mount(App, { global: { plugins: [createPinia()], stubs: { AccountPanel: true, CalendarPanel: true, HistoryPanel: true, TodoistSetup: true, AuthGate: true } } })
    await flushPromises()
    await wrapper.findAll('.gantt-row')[0].trigger('dblclick')
    await flushPromises()

    const groups = wrapper.findAll('.dependency-direction')
    expect(groups).toHaveLength(2)
    expect(groups[0].get('header').text()).toContain('Depende de')
    expect(groups[0].get('.dependency-type').text()).toBe('[FS]')
    expect(groups[0].get('.dependency-task-title').text()).toBe(relationWorkspace.tasks[1].title)
    expect(groups[0].get('.dependency-task-title').attributes('title')).toBe(relationWorkspace.tasks[1].title)
    expect(groups[1].get('header').text()).toContain('Dependentes')
    expect(groups[1].get('.dependency-type').text()).toBe('[SS]')
    expect(groups[1].get('.dependency-task-title').text()).toBe(relationWorkspace.tasks[2].title)
    expect(groups[1].get('.dependency-delete').attributes('aria-label')).toContain(relationWorkspace.tasks[2].title)
    expect(wrapper.get('.dependency-box').text()).not.toContain('→')
    expect(wrapper.findAll<HTMLTextAreaElement>('.drawer-body textarea')[0].element.value).toBe('Descrição nativa')
    expect(wrapper.findAll<HTMLSelectElement>('.drawer-body select')[0].element.value).toBe('3')
    expect(wrapper.findAll<HTMLSelectElement>('.drawer-body select')[1].element.value).toBe('user-1')
    expect(wrapper.get('.comments-box').text()).toContain('Comentário existente')

    await groups[0].get('.dependency-add').trigger('click')
    const search = wrapper.get<HTMLInputElement>('.relation-search')
    await search.setValue('documentação')
    await wrapper.get('.relation-results [role="option"]').trigger('click')
    expect(wrapper.find('.relation-preview').exists()).toBe(false)
    await wrapper.findAll('.relation-modal fieldset button')[0].trigger('click')
    expect(wrapper.get('.relation-preview').text()).toContain('Término da predecessora → início da sucessora')
    await wrapper.findAll('.relation-modal footer button')[1].trigger('click')
    await flushPromises()
    const relationRequest = fetch.mock.calls.find(([url, options]) => url === '/api/v1/dependencies' && (options as RequestInit)?.method === 'POST')
    expect(JSON.parse(String((relationRequest?.[1] as RequestInit).body))).toEqual({ from: 'available', to: 'current', type: 'FS', commandId: 'editor-command' })

    await wrapper.findAll<HTMLTextAreaElement>('.comments-box textarea').at(-1)!.setValue('Novo comentário')
    await wrapper.get('.comment-submit').trigger('click')
    await flushPromises()
    expect(wrapper.get('.comments-box').text()).toContain('Novo comentário')

    await groups[0].get('.dependency-delete').trigger('click')
    expect(wrapper.get('.dependency-confirm').text()).toContain('Remover dependência?')
    wrapper.unmount()
  })

  it('renders a single terminal branch at every depth of a chain', async () => {
    const fetch = vi.fn(async (url: string) => {
      if (url === '/api/v1/me') return { ok: true, json: async () => ({ user: { id: 'u1', name: 'Pessoa', email: 'pessoa@example.test' } }) }
      if (url === '/api/v1/todoist/status') return { ok: true, json: async () => ({ connected: true, project: true, sync_state: 'synced', pending_operations: 0, conflict_operations: 0 }) }
      if (url === '/api/v1/workspace') return { ok: true, json: async () => ({ data: chainWorkspace }) }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    window.fetch = fetch as unknown as typeof window.fetch
    vi.stubGlobal('EventSource', FakeEventSource)

    const wrapper = mount(App, { global: { plugins: [createPinia()], stubs: { AccountPanel: true, CalendarPanel: true, HistoryPanel: true, TodoistSetup: true, AuthGate: true } } })
    await flushPromises()
    const rows = wrapper.findAll('.task-row')

    for (let index = 1; index < rows.length; index++) {
      expect(rows[index].findAll('.tree-slot')).toHaveLength(index)
      expect(rows[index].findAll('.tree-branch-path')).toHaveLength(1)
      expect(rows[index].findAll('.tree-sibling-continuation')).toHaveLength(0)
    }

    await wrapper.findAll('.gantt-row')[3].trigger('mouseenter')
    expect(rows.slice(1).every(row => row.find('.tree-segment-active').exists())).toBe(true)
    expect(wrapper.find('.tree-segment-base.active').exists()).toBe(false)
    wrapper.unmount()
  })

  it('highlights only the segments composing the route to a hovered sibling', async () => {
    const fetch = vi.fn(async (url: string) => {
      if (url === '/api/v1/me') return { ok: true, json: async () => ({ user: { id: 'u1', name: 'Pessoa', email: 'pessoa@example.test' } }) }
      if (url === '/api/v1/todoist/status') return { ok: true, json: async () => ({ connected: true, project: true, sync_state: 'synced', pending_operations: 0, conflict_operations: 0 }) }
      if (url === '/api/v1/workspace') return { ok: true, json: async () => ({ data: routedWorkspace }) }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    window.fetch = fetch as unknown as typeof window.fetch
    vi.stubGlobal('EventSource', FakeEventSource)

    const wrapper = mount(App, { global: { plugins: [createPinia()], stubs: { AccountPanel: true, CalendarPanel: true, HistoryPanel: true, TodoistSetup: true, AuthGate: true } } })
    await flushPromises()
    await wrapper.findAll('.gantt-row')[5].trigger('mouseenter')
    const rows = wrapper.findAll('.task-row')

    expect(rows[0].get('.task-tree-content').classes()).toContain('node-down-active')
    expect(rows[1].get('.task-tree-content').classes()).toContain('node-down-active')
    expect(rows[2].get('.task-tree-content').classes()).toContain('node-down-active')
    for (const siblingRow of [rows[3], rows[4]]) {
      expect(siblingRow.find('.tree-segment-active[d="M11 0 V50"]').exists()).toBe(true)
      expect(siblingRow.find('.tree-segment-active[d="M11 44 V100"]').exists()).toBe(true)
      expect(siblingRow.find('.tree-segment-active[d="M11 0 V44 Q11 50 17 50 H22"]').exists()).toBe(false)
    }
    expect(rows[5].find('.tree-segment-active[d="M11 0 V44 Q11 50 17 50 H22"]').exists()).toBe(true)
    wrapper.unmount()
  })
})
