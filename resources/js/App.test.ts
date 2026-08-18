// @vitest-environment jsdom
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { afterEach, describe, expect, it, vi } from 'vitest'
import App from './App.vue'

const firstWorkspace = { project: { id: 'p1', name: 'Projeto A', source: 'Todoist', sync_status: 'synced', updated_at: '2026-08-17T00:00:00Z' }, tasks: [{ id: 'group', title: 'Grupo', kind: 'group', level: 0, has_children: true, start: '2026-08-17', finish: '2026-08-18', progress: 0, status: 'running', critical: false }, { id: 'child', title: 'Subtarefa', kind: 'task', level: 1, parent_id: 'group', start: '2026-08-17', finish: '2026-08-17', progress: 0, status: 'running', critical: false }], dependencies: [], stats: { progress: 0, completed: 0, total: 1, critical: 0, unscheduled: 0 } }
const secondWorkspace = { ...firstWorkspace, project: { ...firstWorkspace.project, id: 'p2', name: 'Projeto B' }, tasks: [{ ...firstWorkspace.tasks[0], title: 'Grupo atualizado' }, firstWorkspace.tasks[1]] }

class FakeEventSource {
  listeners = new Map<string, () => void>()
  onerror: (() => void) | null = null
  addEventListener(type: string, listener: () => void) { this.listeners.set(type, listener) }
  close() {}
}

describe('workspace interaction', () => {
  afterEach(() => vi.unstubAllGlobals())

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
  })

  it('renders ghosts until the user confirms the operation and then reconciles the workspace', async () => {
    let reconciled = false
    const fetch = vi.fn(async (url: string, options?: RequestInit) => {
      if (url === '/api/v1/me') return { ok: true, json: async () => ({ user: { id: 'u1', name: 'Pessoa', email: 'pessoa@example.test' } }) }
      if (url === '/api/v1/todoist/status') return { ok: true, json: async () => ({ connected: true, project: true, sync_state: 'synced', pending_operations: 0, conflict_operations: 0 }) }
      if (url === '/api/v1/workspace') return { ok: true, json: async () => ({ data: reconciled ? { ...firstWorkspace, tasks: [firstWorkspace.tasks[0], { ...firstWorkspace.tasks[1], start: '2026-08-20', finish: '2026-08-20' }] } : firstWorkspace }) }
      if (url === '/api/v1/schedule/simulate') return { ok: true, json: async () => ({ data: { command_id: 'cmd-1', changes: [{ task_id: 'child', start: '2026-08-20', finish: '2026-08-20' }] } }) }
      if (url === '/api/v1/schedule/apply' && options?.method === 'POST') return { ok: true, json: async () => ({ data: { operation_id: 'op-1', state: 'pending' } }) }
      if (url === '/api/v1/schedule/operations/op-1') { reconciled = true; return { ok: true, json: async () => ({ data: { operation_id: 'op-1', state: 'completed', items: [{ state: 'applied' }] } }) } }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
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
  })
})
