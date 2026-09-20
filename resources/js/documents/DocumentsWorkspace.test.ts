// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import DocumentsWorkspace from './DocumentsWorkspace.vue'
import { connectivity } from '../lib/api'

const documentFixture = {
  id: 'document-1', projectId: 'project-1', name: 'Planta geral', kind: 'manual', archivedAt: null,
  currentRevision: { id: 'revision-1', label: 'R01', sequence: 1, mimeType: 'application/pdf', originalFilename: 'planta.pdf', sizeBytes: 100, sha256: 'a'.repeat(64), contentUrl: '/content', createdAt: '2026-09-19T12:00:00Z' },
  tags: [{ id: 'tag-1', name: 'Arquitetura', parentTagId: null }], outdated: false, processing: null,
} as const
const tagFixtures = [
  ...documentFixture.tags,
  { id: 'tag-2', name: 'Térreo', parentTagId: 'tag-1' },
]

async function mountWorkspace(role: 'owner' | 'reader' = 'owner') {
  const router = createRouter({ history: createMemoryHistory(), routes: [
    { path: '/projects/:id/documents', component: { template: '<div />' } },
    { path: '/projects/:id/documents/:documentId', component: { template: '<div />' } },
  ] })
  await router.push('/projects/project-1/documents'); await router.isReady()
  const wrapper = mount(DocumentsWorkspace, { props: { projectId: 'project-1', userId: 'user-1', role }, global: { plugins: [router], stubs: { PdfDocumentViewer: true, Teleport: true } } })
  await flushPromises()
  return wrapper
}

describe('document library', () => {
  beforeEach(() => {
    connectivity.markOnline()
    vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input)
      if (url.includes('/tags')) return new Response(JSON.stringify({ data: tagFixtures }))
      if (url.includes('/offline-manifest')) return new Response(JSON.stringify({ data: { version: 'v1' } }))
      return new Response(JSON.stringify({ data: [documentFixture], meta: { currentPage: 1, lastPage: 1, perPage: 30, total: 1 } }))
    }))
  })
  afterEach(() => { vi.unstubAllGlobals(); document.body.innerHTML = '' })

  it('renders the desktop table with all server-side filter controls', async () => {
    const wrapper = await mountWorkspace()
    expect(wrapper.get('[role="table"]').text()).toContain('Planta geral')
    expect(wrapper.text()).toContain('Arquitetura')
    expect(wrapper.findAll('select')).toHaveLength(4)
    expect(wrapper.findAll('.tag-row')).toHaveLength(2)
    expect(wrapper.findAll('.tag-row')[1].text()).toContain('Térreo')
  })

  it('becomes read-only offline while keeping the cached library visible', async () => {
    const wrapper = await mountWorkspace()
    connectivity.markOffline(); await wrapper.vm.$nextTick()
    const create = wrapper.get('.document-actions .primary')
    expect(create.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('OFFLINE — SOMENTE LEITURA')
  })

  it('enforces reader permissions while preserving document viewing', async () => {
    const wrapper = await mountWorkspace('reader')
    expect(wrapper.get('.document-actions .primary').attributes('disabled')).toBeDefined()
    await wrapper.get('.document-row').trigger('click')
    await flushPromises()
    expect(wrapper.find('.document-detail').exists()).toBe(true)
    expect(wrapper.find('.detail-edit').exists()).toBe(false)
    expect(wrapper.find('.crop-toggle').exists()).toBe(false)
  })

  it('keeps multiple crop regions in one batch before submitting', async () => {
    const wrapper = await mountWorkspace()
    await wrapper.get('.document-row').trigger('click')
    await flushPromises()
    await wrapper.get('.crop-toggle').trigger('click')
    const viewer = wrapper.getComponent({ name: 'PdfDocumentViewer' })
    viewer.vm.$emit('crop', { page: 1, rectNormalized: { x: .1, y: .2, width: .3, height: .4 } })
    viewer.vm.$emit('crop', { page: 2, rectNormalized: { x: .2, y: .1, width: .4, height: .3 } })
    await wrapper.vm.$nextTick()
    expect(wrapper.findAll('.regions article')).toHaveLength(2)
    expect(wrapper.get('.regions > .primary').text()).toContain('Gerar 2 documento(s)')
  })

  it('renders queued, failed and outdated derivation states', async () => {
    const states = [
      { ...documentFixture, id: 'queued', name: 'Na fila', processing: { id: 'run-1', status: 'queued', attemptCount: 0, errorCode: null, errorMessage: null } },
      { ...documentFixture, id: 'failed', name: 'Com falha', processing: { id: 'run-2', status: 'failed', attemptCount: 1, errorCode: 'INVALID_PDF', errorMessage: 'PDF inválido.' } },
      { ...documentFixture, id: 'outdated', name: 'Antigo', outdated: true },
    ]
    vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input)
      if (url.includes('/tags')) return new Response(JSON.stringify({ data: tagFixtures }))
      if (url.includes('/offline-manifest')) return new Response(JSON.stringify({ data: { version: 'v1' } }))
      return new Response(JSON.stringify({ data: states, meta: { currentPage: 1, lastPage: 1, perPage: 30, total: states.length } }))
    }))
    const wrapper = await mountWorkspace()
    expect(wrapper.text()).toContain('Na fila')
    expect(wrapper.text()).toContain('Falhou')
    expect(wrapper.text()).toContain('Desatualizado')
  })

  it('streams multipart uploads through the central client and reports progress', async () => {
    const request: { url: string; body: FormData | null; complete: (() => void) | null } = { url: '', body: null, complete: null }
    class FakeXMLHttpRequest {
      upload: { onprogress: ((event: { lengthComputable: boolean; loaded: number; total: number }) => void) | null } = { onprogress: null }
      status = 201
      statusText = 'Created'
      responseText = '{}'
      onload: (() => void) | null = null
      onerror: (() => void) | null = null
      onabort: (() => void) | null = null
      open(_method: string, url: string) { request.url = url }
      setRequestHeader() {}
      getAllResponseHeaders() { return 'content-type: application/json\r\n' }
      send(body: FormData) {
        request.body = body
        this.upload.onprogress?.({ lengthComputable: true, loaded: 5, total: 10 })
        request.complete = () => this.onload?.()
      }
    }
    vi.stubGlobal('XMLHttpRequest', FakeXMLHttpRequest)
    const wrapper = await mountWorkspace()
    await wrapper.get('.document-actions .primary').trigger('click')
    const fileInput = wrapper.get('.document-dialog input[type="file"]')
    Object.defineProperty(fileInput.element, 'files', { configurable: true, value: [new File(['pdf'], 'relatorio.pdf', { type: 'application/pdf' })] })
    await fileInput.trigger('change')
    await wrapper.get('.document-dialog form, form.document-dialog').trigger('submit')
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('Enviando 50%')
    expect(request.url).toBe('/api/v1/projects/project-1/documents')
    expect(request.body?.get('name')).toBe('relatorio')
    request.complete?.()
    await flushPromises()
    expect(wrapper.find('.document-dialog').exists()).toBe(false)
  })
})
