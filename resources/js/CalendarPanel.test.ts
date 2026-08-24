// @vitest-environment jsdom
import { mount, flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import CalendarPanel from './CalendarPanel.vue'

const calendar = { version: 1, workingDays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], reschedulingMode: 'MANUAL', projectionPolicy: 'PRESERVE_DURATION', deadlinePolicy: 'ANTERIOR', allowUnscheduledTasks: true, exceptions: [] }
const automation = { version: 1, autoScheduleBlockedTasks: false, clearParentTaskDates: false }

describe('calendar impact confirmation', () => {
  afterEach(() => vi.unstubAllGlobals())

  function mountPanel() {
    return mount(CalendarPanel, { props: { open: false }, global: { stubs: { Teleport: true } } })
  }

  it('simulates first and persists only after the user confirms the preview', async () => {
    const fetch = vi.fn(async (url: string, options?: RequestInit) => {
      if (url === '/api/v1/calendar' && !options?.method) return { ok: true, json: async () => ({ data: calendar }) }
      if (url === '/api/v1/settings/automation' && !options?.method) return { ok: true, json: async () => ({ data: automation }) }
      if (url === '/api/v1/calendar/simulate') return { ok: true, json: async () => ({ data: { changes: [{ task_id: 'task-1', before: { start: '2026-08-17', finish: '2026-08-17' }, after: { start: '2026-08-18', finish: '2026-08-18' } }] } }) }
      if (url === '/api/v1/calendar' && options?.method === 'PUT') return { ok: true, json: async () => ({ data: { ...calendar, version: 2 } }) }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    const wrapper = mountPanel()

    await wrapper.setProps({ open: true })
    await flushPromises()
    await wrapper.get('footer .primary').trigger('click')
    await flushPromises()
    expect(fetch).toHaveBeenCalledWith('/api/v1/calendar/simulate', expect.objectContaining({ method: 'POST' }))
    const simulationRequest = fetch.mock.calls.find(([url]) => url === '/api/v1/calendar/simulate')
    expect(JSON.parse(String(simulationRequest?.[1]?.body)).projectionPolicy).toBe('PRESERVE_DURATION')
    expect(fetch).not.toHaveBeenCalledWith('/api/v1/calendar', expect.objectContaining({ method: 'PUT' }))
    expect(wrapper.text()).toContain('Impacto previsto')

    await wrapper.get('footer .primary').trigger('click')
    await flushPromises()
    expect(fetch).toHaveBeenCalledWith('/api/v1/calendar', expect.objectContaining({ method: 'PUT' }))
    expect(wrapper.emitted('saved')).toHaveLength(1)
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
    expect(wrapper.emitted('close')).toBeUndefined()
  })

  it('does not close from the backdrop and protects dirty changes when closing', async () => {
    vi.stubGlobal('fetch', vi.fn(async (url: string) => ({ ok: true, json: async () => ({ data: url === '/api/v1/settings/automation' ? automation : calendar }) })))
    const wrapper = mountPanel()
    await wrapper.setProps({ open: true })
    await flushPromises()

    await wrapper.get('.settings-modal-scrim').trigger('click')
    expect(wrapper.emitted('close')).toBeUndefined()

    await wrapper.get('input[value="monday"]').setValue(false)
    await wrapper.get('[aria-label="Fechar configurações"]').trigger('click')
    expect(wrapper.find('[role="alertdialog"]').exists()).toBe(true)
    expect(wrapper.emitted('close')).toBeUndefined()

    await wrapper.get('.settings-unsaved-confirm .primary').trigger('click')
    expect(wrapper.find('[role="alertdialog"]').exists()).toBe(false)
    expect(wrapper.get('[role="tab"][aria-selected="true"]').text()).toBe('Calendário')
  })

  it('asks before changing a dirty tab and discards only after confirmation', async () => {
    vi.stubGlobal('fetch', vi.fn(async (url: string) => ({ ok: true, json: async () => ({ data: url === '/api/v1/settings/automation' ? automation : calendar }) })))
    const wrapper = mountPanel()
    await wrapper.setProps({ open: true })
    await flushPromises()

    await wrapper.get('input[value="monday"]').setValue(false)
    await wrapper.get('#settings-tab-automation').trigger('click')
    expect(wrapper.get('[role="tab"][aria-selected="true"]').text()).toBe('Calendário')
    expect(wrapper.find('[role="alertdialog"]').exists()).toBe(true)

    await wrapper.get('.settings-unsaved-confirm .danger-btn').trigger('click')
    expect(wrapper.get('[role="tab"][aria-selected="true"]').text()).toBe('Automação')
    expect(wrapper.get('input[value="monday"]').element).toHaveProperty('checked', true)
  })

  it('shows compact automation rows and saves both settings inside its own tab', async () => {
    const fetch = vi.fn(async (url: string, options?: RequestInit) => {
      if (url === '/api/v1/calendar') return { ok: true, json: async () => ({ data: calendar }) }
      if (url === '/api/v1/settings/automation' && !options?.method) return { ok: true, json: async () => ({ data: automation }) }
      if (url === '/api/v1/settings/automation' && options?.method === 'PUT') return { ok: true, json: async () => ({ data: { version: 2, autoScheduleBlockedTasks: true, clearParentTaskDates: true } }) }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    const wrapper = mountPanel()
    await wrapper.setProps({ open: true })
    await flushPromises()

    await wrapper.get('#settings-tab-automation').trigger('click')
    expect(wrapper.text()).toContain('Definir automaticamente o início de tarefas bloqueadas na data prevista de desbloqueio')
    expect(wrapper.text()).toContain('Manter sem datas no Todoist as tarefas que possuem subtarefas')
    expect(wrapper.find('.automation-section-heading').exists()).toBe(false)
    expect(wrapper.findAll('.automation-option')).toHaveLength(2)
    await wrapper.get('[aria-controls="blocked-task-automation-help"]').trigger('click')
    expect(wrapper.get('#blocked-task-automation-help').text()).toContain('Se a predecessora termina em 10/09')
    await wrapper.get('[aria-label="Fechar ajuda"]').trigger('click')
    await wrapper.get('[aria-controls="parent-task-dates-automation-help"]').trigger('click')
    expect(wrapper.get('#parent-task-dates-automation-help').text()).toContain('ela fica sem datas no Todoist')
    await wrapper.get('.settings-modal-header').trigger('pointerdown')
    expect(wrapper.find('#parent-task-dates-automation-help').exists()).toBe(false)
    expect(wrapper.get('[aria-controls="parent-task-dates-automation-help"]').attributes('aria-expanded')).toBe('false')

    await wrapper.get('#auto-schedule-blocked').setValue(true)
    await wrapper.get('#clear-parent-task-dates').setValue(true)
    await wrapper.get('.automation-settings .settings-tab-actions .primary').trigger('click')
    await flushPromises()
    const request = fetch.mock.calls.find(([url, options]) => url === '/api/v1/settings/automation' && options?.method === 'PUT')
    expect(JSON.parse(String(request?.[1]?.body))).toEqual(expect.objectContaining({ expectedVersion: 1, autoScheduleBlockedTasks: true, clearParentTaskDates: true }))
    expect(wrapper.emitted('saved')).toHaveLength(1)
    expect(wrapper.emitted('notify')).toContainEqual([{ kind: 'success', message: 'Configurações de automação salvas.' }])
    expect(wrapper.emitted('close')).toBeUndefined()
  })

  it('restores an unsaved automation draft after discarding and reopening', async () => {
    const fetch = vi.fn(async (url: string, _options?: RequestInit) => ({ ok: true, json: async () => ({ data: url === '/api/v1/settings/automation' ? automation : calendar }) }))
    vi.stubGlobal('fetch', fetch)
    const wrapper = mountPanel()
    await wrapper.setProps({ open: true })
    await flushPromises()
    await wrapper.get('#settings-tab-automation').trigger('click')
    await wrapper.get('#auto-schedule-blocked').setValue(true)
    await wrapper.get('.automation-settings .soft-btn').trigger('click')
    expect(wrapper.find('[role="alertdialog"]').exists()).toBe(true)

    await wrapper.get('.settings-unsaved-confirm .danger-btn').trigger('click')
    await wrapper.setProps({ open: false })
    await wrapper.setProps({ open: true })
    await flushPromises()
    await wrapper.get('#settings-tab-automation').trigger('click')

    expect((wrapper.get('#auto-schedule-blocked').element as HTMLInputElement).checked).toBe(false)
    expect(fetch.mock.calls.some(([, options]) => options?.method === 'PUT')).toBe(false)
  })

  it('emits an error notification when automation persistence fails', async () => {
    vi.stubGlobal('fetch', vi.fn(async (url: string, options?: RequestInit) => {
      if (url === '/api/v1/calendar') return { ok: true, json: async () => ({ data: calendar }) }
      if (url === '/api/v1/settings/automation' && !options?.method) return { ok: true, json: async () => ({ data: automation }) }
      return { ok: false, status: 500, json: async () => ({ message: 'Falha controlada ao salvar.' }) }
    }))
    const wrapper = mountPanel()
    await wrapper.setProps({ open: true })
    await flushPromises()
    await wrapper.get('#settings-tab-automation').trigger('click')
    await wrapper.get('#auto-schedule-blocked').setValue(true)
    await wrapper.get('.automation-settings .settings-tab-actions .primary').trigger('click')
    await flushPromises()

    expect(wrapper.emitted('saved')).toBeUndefined()
    expect(wrapper.emitted('notify')).toContainEqual([{ kind: 'error', message: 'Falha controlada ao salvar.' }])
    expect(wrapper.text()).toContain('Falha controlada ao salvar.')
  })
})
