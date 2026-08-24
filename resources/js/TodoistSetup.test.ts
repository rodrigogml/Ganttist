// @vitest-environment jsdom
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import TodoistSetup from './TodoistSetup.vue'

describe('Todoist setup', () => {
  afterEach(() => { window.history.replaceState({}, '', '/'); vi.unstubAllGlobals() })

  it('does not continue to the workspace when a saved project has no Todoist integration', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ connected: false, integration_status: 'disconnected', project: { id: 'p1' } }),
    }))

    const wrapper = mount(TodoistSetup)
    await flushPromises()

    expect(wrapper.text()).toContain('Conecte o Todoist')
    expect(wrapper.text()).toContain('Autorize o acesso')
    expect(wrapper.emitted('ready')).toBeUndefined()
  })

  it('explains an OAuth callback failure and removes the stale callback URL', async () => {
    window.history.replaceState({}, '', '/?todoist=authorization_failed')
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ connected: false, integration_status: 'disconnected', project: null }),
    }))

    const wrapper = mount(TodoistSetup)
    await flushPromises()

    expect(wrapper.text()).toContain('Não foi possível concluir a autorização do Todoist')
    expect(window.location.search).toBe('')
  })
})
