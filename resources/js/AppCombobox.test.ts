// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { defineComponent, nextTick } from 'vue'
import { describe, expect, it } from 'vitest'
import AppCombobox from './AppCombobox.vue'
import PersonCombobox from './PersonCombobox.vue'

HTMLElement.prototype.scrollIntoView ||= () => undefined

describe('AppCombobox', () => {
  it('opens its options when the input or trigger is clicked', async () => {
    const wrapper = mount(AppCombobox, {
      attachTo: document.body,
      props: { modelValue: null, allowEmpty: true, emptyLabel: 'Sem responsável', options: [{ id: 'ana', label: 'Ana' }] },
    })

    await wrapper.find('input').trigger('click')
    expect(document.body.textContent).toContain('Ana')
    await wrapper.find('.app-combobox-trigger').trigger('click')
    expect(document.body.textContent).not.toContain('Ana')
    wrapper.unmount()
  })

  it('maps collaborators into selectable options', async () => {
    const wrapper = mount(PersonCombobox, { attachTo: document.body, props: { modelValue: null, people: [{ id: 'ana', name: 'Ana Silva', email: 'ana@example.test' }] } })
    await wrapper.find('input').trigger('click')
    expect(document.body.textContent).toContain('Ana Silva')
    await (document.querySelector('.app-combobox-option:not(.app-combobox-empty-choice)') as HTMLElement).click()
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['ana'])
    wrapper.unmount()
  })

  it('shows the task assignee when the editor context loads people after the task', async () => {
    const wrapper = mount(PersonCombobox, { props: { modelValue: 'ana', people: [] } })
    await wrapper.setProps({ people: [{ id: 'ana', name: 'Ana Silva' }] })
    expect((wrapper.find('input').element as HTMLInputElement).value).toBe('Ana Silva')
    wrapper.unmount()
  })

  it('works when embedded in the task editor label', async () => {
    const Host = defineComponent({ components: { PersonCombobox }, template: '<label>Responsável<PersonCombobox :model-value="null" :people="[{ id: \'ana\', name: \'Ana Silva\' }]" /></label>' })
    const wrapper = mount(Host, { attachTo: document.body })
    await wrapper.find('input').trigger('click')
    expect(document.body.textContent).toContain('Ana Silva')
    wrapper.unmount()
  })

  it('keeps Home and End for cursor movement while Page Up and Page Down navigate options', async () => {
    const wrapper = mount(AppCombobox, {
      attachTo: document.body,
      props: { modelValue: null, options: [{ id: 'ana', label: 'Ana' }, { id: 'bia', label: 'Bia' }] },
    })
    const input = wrapper.find('input').element
    await wrapper.find('input').trigger('click')

    const home = new KeyboardEvent('keydown', { key: 'Home', bubbles: true, cancelable: true })
    input.dispatchEvent(home)
    expect(home.defaultPrevented).toBe(false)

    const pageDown = new KeyboardEvent('keydown', { key: 'PageDown', bubbles: true, cancelable: true })
    input.dispatchEvent(pageDown)
    await nextTick()
    expect(pageDown.defaultPrevented).toBe(true)
    expect(document.querySelectorAll('.app-combobox-option[data-highlighted]')).toHaveLength(1)
    expect(document.querySelector('.app-combobox-option[data-highlighted]')?.textContent).toContain('Bia')
    wrapper.unmount()
  })
})
