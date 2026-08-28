// @vitest-environment jsdom

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import DateInput from './DateInput.vue'

describe('DateInput', () => {
  it('clears a filled date while preserving the native date picker input', async () => {
    const wrapper = mount(DateInput, { props: { modelValue: '2026-08-27' } })

    expect(wrapper.find('input[type="date"]').exists()).toBe(true)
    expect(wrapper.findAll('.date-input-action')).toHaveLength(2)
    await wrapper.get('button[aria-label="Limpar data"]').trigger('click')

    expect(wrapper.emitted('update:modelValue')).toEqual([[null]])
  })
})
