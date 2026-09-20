// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const renderPage = vi.fn(() => ({ promise: Promise.resolve() }))
const getPage = vi.fn(async () => ({
  getViewport: ({ scale }: { scale: number }) => ({ width: 400 * scale, height: 300 * scale }),
  render: renderPage,
}))
const cleanup = vi.fn()

vi.mock('pdfjs-dist', () => ({
  GlobalWorkerOptions: { workerSrc: '' },
  getDocument: vi.fn(() => ({ promise: Promise.resolve({ numPages: 2, getPage, cleanup }) })),
}))
vi.mock('pdfjs-dist/build/pdf.worker.min.mjs?url', () => ({ default: '/pdf.worker.mjs' }))

import PdfDocumentViewer from './PdfDocumentViewer.vue'

describe('PDF document viewer', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue({} as CanvasRenderingContext2D)
  })

  it('paginates, zooms and emits multiple normalized crop regions', async () => {
    const wrapper = mount(PdfDocumentViewer, { props: { url: '/document.pdf', cropMode: true } })
    await flushPromises()

    expect(wrapper.text()).toContain('Página 1 de 2')
    await wrapper.get('header button:nth-of-type(2)').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Página 2 de 2')
    await wrapper.get('header button:nth-of-type(4)').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('120%')

    const scroll = wrapper.get('.pdf-scroll').element
    Object.defineProperties(scroll, { clientWidth: { value: 836 }, clientHeight: { value: 486 } })
    await wrapper.findAll('button.fit')[0].trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('200%')
    await wrapper.findAll('button.fit')[1].trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('150%')

    const overlay = wrapper.get('.crop-overlay')
    Object.defineProperty(overlay.element, 'getBoundingClientRect', {
      value: () => ({ left: 100, top: 50, width: 400, height: 300, right: 500, bottom: 350, x: 100, y: 50, toJSON: () => ({}) }),
    })
    Object.defineProperty(overlay.element, 'setPointerCapture', { value: vi.fn() })

    for (const [start, end] of [
      [{ clientX: 140, clientY: 80 }, { clientX: 300, clientY: 200 }],
      [{ clientX: 220, clientY: 110 }, { clientX: 420, clientY: 260 }],
    ] as const) {
      await overlay.trigger('pointerdown', { ...start, pointerId: 1 })
      await overlay.trigger('pointermove', { ...end, pointerId: 1 })
      await overlay.trigger('pointerup', { ...end, pointerId: 1 })
      await wrapper.get('.add-region').trigger('click')
    }

    const crops = wrapper.emitted('crop')
    expect(crops).toHaveLength(2)
    expect(crops?.[0]?.[0]).toEqual({ page: 2, rectNormalized: { x: .1, y: .1, width: .4, height: .4 } })
    const second = crops?.[1]?.[0] as { page: number; rectNormalized: { x: number; y: number; width: number; height: number } }
    expect(second.page).toBe(2)
    expect(second.rectNormalized.x).toBeCloseTo(.3)
    expect(second.rectNormalized.y).toBeCloseTo(.2)
    expect(second.rectNormalized.width).toBeCloseTo(.5)
    expect(second.rectNormalized.height).toBeCloseTo(.5)
    expect(renderPage).toHaveBeenCalled()
  })
})
