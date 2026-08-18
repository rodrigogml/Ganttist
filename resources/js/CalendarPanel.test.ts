// @vitest-environment jsdom
import { mount, flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import CalendarPanel from './CalendarPanel.vue'

const calendar = { version: 1, workingDays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], reschedulingMode: 'MANUAL', deadlinePolicy: 'ANTERIOR', allowUnscheduledTasks: true, exceptions: [] }

describe('calendar impact confirmation', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('simulates first and persists only after the user confirms the preview', async () => {
    const fetch = vi.fn(async (url: string, options?: RequestInit) => {
      if (url === '/api/v1/calendar' && !options?.method) return { ok: true, json: async () => ({ data: calendar }) }
      if (url === '/api/v1/calendar/simulate') return { ok: true, json: async () => ({ data: { changes: [{ task_id: 'task-1', before: { start: '2026-08-17', finish: '2026-08-17' }, after: { start: '2026-08-18', finish: '2026-08-18' } }] } }) }
      if (url === '/api/v1/calendar' && options?.method === 'PUT') return { ok: true, json: async () => ({ data: { ...calendar, version: 2 } }) }
      throw new Error(`Unexpected request ${url}`)
    })
    vi.stubGlobal('fetch', fetch)
    const wrapper = mount(CalendarPanel, { props: { open: false } })

    await wrapper.setProps({ open: true })
    await flushPromises()
    await wrapper.get('footer .primary').trigger('click')
    await flushPromises()
    expect(fetch).toHaveBeenCalledWith('/api/v1/calendar/simulate', expect.objectContaining({ method: 'POST' }))
    expect(fetch).not.toHaveBeenCalledWith('/api/v1/calendar', expect.objectContaining({ method: 'PUT' }))
    expect(wrapper.text()).toContain('Impacto previsto')

    await wrapper.get('footer .primary').trigger('click')
    await flushPromises()
    expect(fetch).toHaveBeenCalledWith('/api/v1/calendar', expect.objectContaining({ method: 'PUT' }))
    expect(wrapper.emitted('saved')).toHaveLength(1)
  })
})
