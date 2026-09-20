// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const { purgeOfflineUser } = vi.hoisted(() => ({ purgeOfflineUser: vi.fn(async () => undefined) }))
vi.mock('./offline/offline-store', () => ({ purgeOfflineUser }))

import AccountPanel from './AccountPanel.vue'
import { connectivity } from './lib/api'

describe('account privacy controls', () => {
  afterEach(() => {
    connectivity.markOnline()
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
  })

  it('removes every offline project for the active user even without connectivity', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('offline')))
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    const wrapper = mount(AccountPanel, { props: { open: false, userId: 'user-1' } })
    await wrapper.setProps({ open: true })
    await flushPromises()
    connectivity.markOffline()
    await wrapper.get('footer .soft-btn').trigger('click')
    await flushPromises()

    expect(purgeOfflineUser).toHaveBeenCalledWith('user-1')
    expect(wrapper.text()).toContain('Todos os dados offline deste usuário foram removidos.')
  })
})
