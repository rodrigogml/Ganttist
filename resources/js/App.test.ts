// @vitest-environment jsdom
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { afterEach, describe, expect, it, vi } from 'vitest'
import App from './App.vue'

const firstWorkspace = { project: { id: 'p1', name: 'Projeto A', source: 'Todoist', sync_status: 'synced', updated_at: '2026-08-17T00:00:00Z' }, tasks: [{ id: 'section', title: 'Grupo', kind: 'section', level: 0, has_children: true, start: null, finish: null, considered_start: '2026-08-17', considered_deadline: '2026-08-18', completed: false, progress: 0, status: 'opened', critical: false }, { id: 'child', title: 'Subtarefa', kind: 'task', level: 1, parent_id: 'section', start: '2026-08-17', finish: '2026-08-17', considered_start: '2026-08-17', considered_deadline: '2026-08-17', completed: false, progress: 0, status: 'opened', critical: false }], dependencies: [], stats: { progress: 0, completed: 0, total: 1, critical: 0, opened: 1, blocked: 0, scheduled: 0, late: 0, without_dates: 0 } }
const secondWorkspace = { ...firstWorkspace, project: { ...firstWorkspace.project, id: 'p2', name: 'Projeto B' }, tasks: [{ ...firstWorkspace.tasks[0], title: 'Grupo atualizado' }, firstWorkspace.tasks[1]] }
const selectionWorkspace = { ...firstWorkspace, tasks: [firstWorkspace.tasks[0], { ...firstWorkspace.tasks[1], id: 'parent', title: 'Tarefa pai', has_children: true }, { ...firstWorkspace.tasks[1], level: 2, parent_id: 'parent' }, { ...firstWorkspace.tasks[1], id: 'sibling', title: 'Outra tarefa' }] }
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

class FakeEventSource {
  listeners = new Map<string, () => void>()
  onerror: (() => void) | null = null
  addEventListener(type: string, listener: () => void) { this.listeners.set(type, listener) }
  close() {}
}

describe('workspace interaction', () => {
  afterEach(() => { sessionStorage.clear(); localStorage.clear(); vi.unstubAllGlobals() })

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
    expect(JSON.parse(String((request?.[1] as RequestInit).body))).toEqual({ start: '2026-08-19', commandId: 'drag-command' })
    expect(fetch.mock.calls.some(([url]) => url === '/api/v1/schedule/simulate')).toBe(false)
    expect(wrapper.get('[aria-label^="Planejada:"]').attributes('style')).toContain('width: 126px')
    await wrapper.findAll('.gantt-row').find(row => row.text().includes('Sem prazo'))!.trigger('dblclick')
    const dateInputs = wrapper.findAll<HTMLInputElement>('.drawer input[type="date"]')
    expect(dateInputs[0].element.value).toBe('2026-08-18')
    expect(dateInputs[1].element.value).toBe('')
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
    expect(rows[2].get('.gantt-tree-toggle-spacer').get('path').attributes('d')).toBe('M0 50 H22')
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
