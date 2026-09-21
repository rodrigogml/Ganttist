<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { apiFetch, apiUpload, connectivity, csrfHeaders, responseMessage } from '../lib/api'
import { offlineContentUrl, prepareProjectOffline, preparedManifest, removeProjectOffline } from '../offline/offline-store'
import PdfDocumentViewer from './PdfDocumentViewer.vue'
import DefaultSubmitButton from '../components/forms/DefaultSubmitButton.vue'
import type { CropRect, DocumentRevision, DocumentTag, ProjectDocument } from './types'

type PageMeta = { currentPage: number; lastPage: number; perPage: number; total: number }
type CropRegion = { page: number; rectNormalized: CropRect; name: string; tagIds: string[]; autoRegenerate: boolean }
const props = defineProps<{ projectId: string; userId: string; role?: 'owner' | 'editor' | 'reader' }>()
const route = useRoute(), router = useRouter()
const documents = ref<ProjectDocument[]>([]), tags = ref<DocumentTag[]>([]), selected = ref<ProjectDocument | null>(null), revisions = ref<DocumentRevision[]>([])
const meta = ref<PageMeta>({ currentPage: 1, lastPage: 1, perPage: 30, total: 0 })
const loading = ref(true), error = ref(''), search = ref(''), kind = ref(''), mimeFamily = ref(''), state = ref(''), archived = ref(false), selectedTags = ref<string[]>([])
const uploadOpen = ref(false), uploadBusy = ref(false), uploadProgress = ref(0), uploadName = ref(''), uploadLabel = ref('R01'), uploadFile = ref<File | null>(null), uploadTagIds = ref<string[]>([])
const revisionOpen = ref(false), revisionBusy = ref(false), revisionProgress = ref(0), revisionLabel = ref(''), revisionFile = ref<File | null>(null)
const editName = ref(''), editTagIds = ref<string[]>([]), savingDocument = ref(false)
const newTagName = ref(''), newTagParent = ref('')
const cropMode = ref(false), regions = ref<CropRegion[]>([])
const derivationDraft = ref<{ page: number; x: number; y: number; width: number; height: number; autoRegenerate: boolean; active: boolean } | null>(null)
const offlineBusy = ref(false), offlineProgress = ref(0), offlineReady = ref(false), offlineTimestamp = ref(''), offlineUpdateAvailable = ref(false)
let pollTimer: ReturnType<typeof setInterval> | null = null
let filterTimer: ReturnType<typeof setTimeout> | null = null
const readOnly = computed(() => props.role === 'reader' || !connectivity.online.value)

function tagLabel(tag: DocumentTag): string { let depth = 0, parent = tag.parentTagId; while (parent) { depth += 1; parent = tags.value.find(candidate => candidate.id === parent)?.parentTagId ?? null } return `${'  '.repeat(depth)}${tag.name}` }
function documentState(document: ProjectDocument): string { if (document.outdated) return 'Desatualizado'; if (document.processing?.status === 'failed') return 'Falhou'; if (document.processing && document.processing.status !== 'succeeded') return document.processing.status === 'queued' ? 'Na fila' : 'Processando'; return document.currentRevision ? 'Pronto' : 'Sem revisão' }
function contentUrl(revision: DocumentRevision): string { return connectivity.online.value ? revision.contentUrl : offlineContentUrl(revision.contentUrl, props.userId) }

async function load(page = meta.value.currentPage || 1): Promise<void> {
  loading.value = true; error.value = ''
  try {
    const params = new URLSearchParams({ page: String(page), perPage: '30' })
    if (search.value.trim()) params.set('search', search.value.trim())
    if (kind.value) params.set('kind', kind.value)
    if (mimeFamily.value) params.set('mimeFamily', mimeFamily.value)
    if (state.value) params.set('state', state.value)
    if (archived.value) params.set('archived', '1')
    selectedTags.value.forEach(id => params.append('tagIds[]', id))
    const [documentResponse, tagResponse] = await Promise.all([apiFetch(`/api/v1/projects/${props.projectId}/documents?${params}`), apiFetch(`/api/v1/projects/${props.projectId}/tags`)])
    if ([403, 404].includes(documentResponse.status) || [403, 404].includes(tagResponse.status)) {
      await removeProjectOffline(props.userId, props.projectId)
      throw Object.assign(new Error('Você não possui mais acesso a este projeto.'), { accessRevoked: true })
    }
    if (!documentResponse.ok || !tagResponse.ok) throw new Error('Não foi possível carregar a biblioteca.')
    const pageBody = await documentResponse.json()
    documents.value = pageBody.data; meta.value = pageBody.meta; tags.value = (await tagResponse.json()).data
    const requested = typeof route.params.documentId === 'string' ? documents.value.find(document => document.id === route.params.documentId) : null
    if (requested && selected.value?.id !== requested.id) await openDocument(requested, false)
    schedulePolling()
  } catch (exception) {
    const cached = (exception as Error & { accessRevoked?: boolean })?.accessRevoked ? null : await preparedManifest(props.userId, props.projectId)
    if (cached) {
      documents.value = cached.documents as ProjectDocument[]; tags.value = cached.tags as DocumentTag[]; meta.value = { currentPage: 1, lastPage: 1, perPage: documents.value.length, total: documents.value.length }; offlineReady.value = true; offlineTimestamp.value = cached.generatedAt
      const requested = typeof route.params.documentId === 'string' ? documents.value.find(document => document.id === route.params.documentId) : null
      if (requested && selected.value?.id !== requested.id) await openDocument(requested, false)
    } else error.value = exception instanceof Error ? exception.message : 'Erro inesperado.'
  } finally { loading.value = false }
}

async function refreshSelected(): Promise<void> {
  if (!selected.value) return
  const response = await apiFetch(`/api/v1/projects/${props.projectId}/documents/${selected.value.id}`)
  if (response.ok) await openDocument((await response.json()).data, false)
  await load(meta.value.currentPage)
}

async function openDocument(document: ProjectDocument, updateRoute = true): Promise<void> {
  selected.value = document; editName.value = document.name; editTagIds.value = document.tags.map(tag => tag.id); regions.value = []; cropMode.value = false
  const configuration = document.derivation?.configuration
  derivationDraft.value = configuration ? { page: configuration.page, x: configuration.rectNormalized.x, y: configuration.rectNormalized.y, width: configuration.rectNormalized.width, height: configuration.rectNormalized.height, autoRegenerate: document.derivation!.autoRegenerate, active: document.derivation!.active } : null
  if (updateRoute) await router.push(`/projects/${props.projectId}/documents/${document.id}`)
  if (!connectivity.online.value) { revisions.value = document.currentRevision ? [document.currentRevision] : []; return }
  const response = await apiFetch(`/api/v1/projects/${props.projectId}/documents/${document.id}/revisions`)
  if (response.ok) revisions.value = (await response.json()).data
}
async function closeDocument(): Promise<void> { selected.value = null; revisions.value = []; await router.push(`/projects/${props.projectId}/documents`) }

function fileSelected(event: Event): void { uploadFile.value = (event.target as HTMLInputElement).files?.[0] ?? null; if (uploadFile.value && !uploadName.value) uploadName.value = uploadFile.value.name.replace(/\.[^.]+$/, '') }
function revisionSelected(event: Event): void { revisionFile.value = (event.target as HTMLInputElement).files?.[0] ?? null }
async function uploadMultipart(path: string, form: FormData, progress: (value: number) => void): Promise<void> {
  const response = await apiUpload(path, form, progress)
  if (!response.ok) throw new Error(await responseMessage(response, 'Não foi possível enviar o arquivo.'))
}
async function upload(): Promise<void> {
  if (!uploadFile.value || !uploadName.value.trim() || uploadBusy.value || readOnly.value) return
  uploadBusy.value = true; uploadProgress.value = 0; error.value = ''
  const form = new FormData(); form.set('name', uploadName.value.trim()); form.set('revisionLabel', uploadLabel.value.trim()); form.set('file', uploadFile.value); uploadTagIds.value.forEach((id, index) => form.set(`tagIds[${index}]`, id))
  try { await uploadMultipart(`/api/v1/projects/${props.projectId}/documents`, form, value => { uploadProgress.value = value }); uploadOpen.value = false; uploadName.value = ''; uploadLabel.value = 'R01'; uploadFile.value = null; uploadTagIds.value = []; await load(1) }
  catch (exception) { error.value = exception instanceof Error ? exception.message : 'Falha no upload.' } finally { uploadBusy.value = false }
}
async function uploadRevision(): Promise<void> {
  if (!selected.value || !revisionFile.value || !revisionLabel.value.trim() || revisionBusy.value || readOnly.value) return
  revisionBusy.value = true; revisionProgress.value = 0
  const form = new FormData(); form.set('revisionLabel', revisionLabel.value.trim()); form.set('file', revisionFile.value)
  try { await uploadMultipart(`/api/v1/projects/${props.projectId}/documents/${selected.value.id}/revisions`, form, value => { revisionProgress.value = value }); revisionOpen.value = false; revisionFile.value = null; revisionLabel.value = ''; await refreshSelected() }
  catch (exception) { error.value = exception instanceof Error ? exception.message : 'Falha ao criar revisão.' } finally { revisionBusy.value = false }
}

async function saveDocument(): Promise<void> {
  if (!selected.value || readOnly.value) return
  savingDocument.value = true
  const response = await apiFetch(`/api/v1/projects/${props.projectId}/documents/${selected.value.id}`, { method: 'PATCH', headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() }, body: JSON.stringify({ name: editName.value.trim(), tagIds: editTagIds.value }) })
  if (!response.ok) error.value = await responseMessage(response, 'Não foi possível salvar o documento.')
  else await openDocument((await response.json()).data, false)
  savingDocument.value = false; await load(meta.value.currentPage)
}
async function archiveOrRestore(): Promise<void> {
  if (!selected.value || readOnly.value) return
  const restore = Boolean(selected.value.archivedAt)
  if (!restore && !confirm('Arquivar este documento? O histórico será preservado.')) return
  const response = await apiFetch(`/api/v1/projects/${props.projectId}/documents/${selected.value.id}${restore ? '/restore' : ''}`, { method: restore ? 'POST' : 'DELETE', headers: { Accept: 'application/json', ...csrfHeaders() } })
  if (!response.ok) { error.value = await responseMessage(response, restore ? 'Não foi possível restaurar.' : 'Não foi possível arquivar.'); return }
  await closeDocument(); await load(1)
}

async function createTag(): Promise<void> {
  if (!newTagName.value.trim() || readOnly.value) return
  const response = await apiFetch(`/api/v1/projects/${props.projectId}/tags`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() }, body: JSON.stringify({ name: newTagName.value.trim(), parentTagId: newTagParent.value || null }) })
  if (!response.ok) error.value = await responseMessage(response, 'Não foi possível criar a tag.')
  else { newTagName.value = ''; newTagParent.value = ''; await load(1) }
}
async function renameTag(tag: DocumentTag): Promise<void> {
  if (readOnly.value) return
  const name = prompt('Novo nome da tag:', tag.name)?.trim(); if (!name || name === tag.name) return
  const response = await apiFetch(`/api/v1/projects/${props.projectId}/tags/${tag.id}`, { method: 'PATCH', headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() }, body: JSON.stringify({ name }) })
  if (!response.ok) error.value = await responseMessage(response, 'Não foi possível renomear a tag.'); else await load(1)
}
async function deleteTag(tag: DocumentTag): Promise<void> {
  if (readOnly.value || !confirm(`Arquivar a tag “${tag.name}”?`)) return
  const response = await apiFetch(`/api/v1/projects/${props.projectId}/tags/${tag.id}`, { method: 'DELETE', headers: { Accept: 'application/json', ...csrfHeaders() } })
  if (!response.ok) error.value = await responseMessage(response, 'Não foi possível arquivar a tag.'); else { selectedTags.value = selectedTags.value.filter(id => id !== tag.id); await load(1) }
}

function addRegion(value: { page: number; rectNormalized: CropRect }): void { regions.value.push({ ...value, name: `Recorte ${regions.value.length + 1}`, tagIds: [], autoRegenerate: true }) }
async function createDerivations(): Promise<void> {
  if (!selected.value || regions.value.length === 0 || readOnly.value) return
  const response = await apiFetch(`/api/v1/projects/${props.projectId}/documents/${selected.value.id}/derivations`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() }, body: JSON.stringify({ items: regions.value }) })
  if (!response.ok) error.value = await responseMessage(response, 'Não foi possível criar os derivados.')
  else { regions.value = []; cropMode.value = false; await load(1) }
}
async function regenerate(): Promise<void> {
  if (!selected.value?.derivation || readOnly.value) return
  const response = await apiFetch(`/api/v1/projects/${props.projectId}/derivations/${selected.value.derivation.id}/regenerate`, { method: 'POST', headers: { Accept: 'application/json', ...csrfHeaders() } })
  if (!response.ok) error.value = await responseMessage(response, 'Não foi possível regenerar o documento.'); else await refreshSelected()
}
async function saveDerivation(): Promise<void> {
  if (!selected.value?.derivation || !derivationDraft.value || readOnly.value) return
  const draft = derivationDraft.value
  const response = await apiFetch(`/api/v1/projects/${props.projectId}/derivations/${selected.value.derivation.id}`, { method: 'PATCH', headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() }, body: JSON.stringify({ page: draft.page, rectNormalized: { x: draft.x, y: draft.y, width: draft.width, height: draft.height }, autoRegenerate: draft.autoRegenerate, active: draft.active }) })
  if (!response.ok) error.value = await responseMessage(response, 'Não foi possível atualizar a derivação.'); else await refreshSelected()
}

async function prepareOffline(): Promise<void> {
  if (!confirm('Os documentos atuais deste projeto serão armazenados neste navegador. Qualquer pessoa com acesso a este perfil poderá consultá-los. Continuar?')) return
  offlineBusy.value = true; offlineProgress.value = 0
  try { const manifest = await prepareProjectOffline(props.projectId, props.userId, (done, total) => { offlineProgress.value = total ? Math.round(done / total * 100) : 100 }); offlineReady.value = true; offlineTimestamp.value = manifest.generatedAt; offlineUpdateAvailable.value = false }
  catch (exception) { error.value = exception instanceof Error ? exception.message : 'Não foi possível preparar o cache.' } finally { offlineBusy.value = false }
}
async function removeOffline(): Promise<void> { await removeProjectOffline(props.userId, props.projectId); offlineReady.value = false; offlineTimestamp.value = '' }
async function checkOfflineUpdate(): Promise<void> { const cached = await preparedManifest(props.userId, props.projectId); if (!cached || !connectivity.online.value) return; try { const response = await apiFetch(`/api/v1/projects/${props.projectId}/offline-manifest`, { headers: { Accept: 'application/json' } }); if (response.ok) offlineUpdateAvailable.value = (await response.json()).data.version !== cached.version } catch { /* apiFetch owns connectivity */ } }
function schedulePolling(): void { if (pollTimer) clearInterval(pollTimer); if (documents.value.some(document => ['queued', 'processing'].includes(document.processing?.status ?? ''))) pollTimer = setInterval(() => { void load(meta.value.currentPage) }, 3000) }
function scheduleFilterLoad(): void { if (filterTimer) clearTimeout(filterTimer); filterTimer = setTimeout(() => { void load(1) }, 250) }
watch([search, kind, mimeFamily, state, archived, selectedTags], scheduleFilterLoad, { deep: true })
watch(() => props.projectId, () => { void load(1) })
watch(() => connectivity.online.value, online => { if (online) void checkOfflineUpdate() })
onMounted(async () => { const cached = await preparedManifest(props.userId, props.projectId); offlineReady.value = Boolean(cached); offlineTimestamp.value = cached?.generatedAt ?? ''; await load(1); await checkOfflineUpdate() })
onBeforeUnmount(() => { if (pollTimer) clearInterval(pollTimer); if (filterTimer) clearTimeout(filterTimer) })
</script>

<template>
  <main class="documents-workspace">
    <div v-if="!connectivity.online.value" class="offline-banner"><b>OFFLINE — SOMENTE LEITURA</b><span>Última atualização: {{ offlineTimestamp ? new Date(offlineTimestamp).toLocaleString('pt-BR') : 'projeto não preparado' }}. As informações podem estar desatualizadas.</span></div>
    <header class="documents-header"><div><span class="eyebrow">BIBLIOTECA DO PROJETO</span><h1>Documentos</h1></div><div class="document-actions"><button v-if="offlineReady" class="soft-btn" @click="removeOffline">Remover offline</button><button class="soft-btn" :disabled="offlineBusy || !connectivity.online.value" @click="prepareOffline">{{ offlineBusy ? `Baixando ${offlineProgress}%` : offlineUpdateAvailable ? 'Atualização offline disponível' : offlineReady ? 'Atualizar offline' : 'Disponibilizar offline' }}</button><button class="primary" :disabled="readOnly" @click="uploadOpen = true">+ Documento</button></div></header>
    <p v-if="error" class="document-error" role="alert">{{ error }}</p>
    <section class="library-layout">
      <aside class="filters">
        <label>Pesquisar<input v-model="search" placeholder="Nome do documento"></label>
        <label>Tipo<select v-model="kind"><option value="">Todos</option><option value="manual">Manuais</option><option value="derived">Derivados</option></select></label>
        <label>Formato<select v-model="mimeFamily"><option value="">Todos</option><option value="application">Documentos/PDF</option><option value="image">Imagens</option><option value="text">Texto</option></select></label>
        <label>Estado<select v-model="state"><option value="">Todos</option><option value="ready">Prontos</option><option value="queued">Na fila</option><option value="processing">Processando</option><option value="failed">Falharam</option><option value="outdated">Desatualizados</option></select></label>
        <label class="inline-check"><input v-model="archived" type="checkbox"> Exibir arquivados</label>
        <fieldset class="tag-filter"><legend>Tags (interseção)</legend><div v-for="tag in tags" :key="tag.id" class="tag-row"><label><input v-model="selectedTags" type="checkbox" :value="tag.id"><span>{{ tagLabel(tag) }}</span></label><span v-if="!readOnly" class="tag-actions"><button title="Renomear" @click="renameTag(tag)">✎</button><button title="Arquivar" @click="deleteTag(tag)">×</button></span></div></fieldset>
        <form v-if="!readOnly" v-default-form class="tag-create" @submit.prevent="createTag"><b>Nova tag</b><input v-model="newTagName" maxlength="100" placeholder="Nome"><select v-model="newTagParent"><option value="">Sem tag-pai</option><option v-for="tag in tags" :key="tag.id" :value="tag.id">{{ tagLabel(tag) }}</option></select><DefaultSubmitButton type="submit" :disabled="!newTagName.trim()">Criar</DefaultSubmitButton></form>
      </aside>
      <div class="library-content">
        <div v-if="loading" class="document-empty">Carregando biblioteca…</div>
        <div v-else-if="!documents.length" class="document-empty"><b>Nenhum documento</b><span>Envie o primeiro arquivo ou ajuste os filtros.</span></div>
        <div v-else class="document-list" role="table" aria-label="Documentos do projeto">
          <div class="document-list-head" role="row"><span>Documento</span><span>Revisão</span><span>Formato</span><span>Tags</span><span>Estado</span></div>
          <button v-for="document in documents" :key="document.id" class="document-row" role="row" @click="openDocument(document)"><span class="name"><small>{{ document.kind === 'derived' ? 'DERIVADO' : 'DOCUMENTO' }}</small><b>{{ document.name }}</b></span><span>{{ document.currentRevision?.label ?? '—' }}</span><span>{{ document.currentRevision?.mimeType ?? 'aguardando arquivo' }}</span><span class="tags"><i v-for="tag in document.tags" :key="tag.id">{{ tag.name }}</i></span><span class="state" :class="document.processing?.status">{{ document.archivedAt ? 'Arquivado' : documentState(document) }}</span></button>
        </div>
        <nav v-if="meta.lastPage > 1" class="pagination" aria-label="Paginação"><button :disabled="meta.currentPage <= 1" @click="load(meta.currentPage - 1)">Anterior</button><span>Página {{ meta.currentPage }} de {{ meta.lastPage }} · {{ meta.total }} itens</span><button :disabled="meta.currentPage >= meta.lastPage" @click="load(meta.currentPage + 1)">Próxima</button></nav>
      </div>
    </section>

    <Teleport to="body"><div v-if="uploadOpen" class="document-scrim" @click.self="uploadOpen = false"><form v-default-form class="document-dialog" @submit.prevent="upload"><header><h2>Novo documento</h2><button type="button" @click="uploadOpen = false">×</button></header><label>Arquivo<input type="file" required @change="fileSelected"></label><label>Nome<input v-model="uploadName" required maxlength="255"></label><label>Revisão<input v-model="uploadLabel" required maxlength="100"></label><fieldset><legend>Tags</legend><label v-for="tag in tags" :key="tag.id"><input v-model="uploadTagIds" type="checkbox" :value="tag.id">{{ tag.name }}</label></fieldset><footer><span v-if="uploadBusy">Enviando {{ uploadProgress }}%</span><DefaultSubmitButton type="submit" :disabled="uploadBusy">Enviar</DefaultSubmitButton></footer></form></div></Teleport>
    <Teleport to="body"><div v-if="revisionOpen" class="document-scrim" @click.self="revisionOpen = false"><form v-default-form class="document-dialog" @submit.prevent="uploadRevision"><header><h2>Nova revisão</h2><button type="button" @click="revisionOpen = false">×</button></header><label>Arquivo<input type="file" required @change="revisionSelected"></label><label>Rótulo<input v-model="revisionLabel" required maxlength="100" placeholder="R02"></label><footer><span v-if="revisionBusy">Enviando {{ revisionProgress }}%</span><DefaultSubmitButton type="submit" :disabled="revisionBusy">Criar revisão</DefaultSubmitButton></footer></form></div></Teleport>
    <Teleport to="body"><div v-if="selected" class="document-scrim"><section class="document-detail"><header><div><span class="eyebrow">{{ selected.kind === 'derived' ? 'DOCUMENTO DERIVADO' : 'DOCUMENTO' }}</span><h2>{{ selected.name }}</h2><small v-if="selected.derivation">Origem: {{ selected.derivation.sourceDocumentName || selected.derivation.sourceDocumentId }}</small></div><button @click="closeDocument">×</button></header><div class="detail-body"><PdfDocumentViewer v-if="selected.currentRevision?.mimeType === 'application/pdf'" :url="contentUrl(selected.currentRevision)" :crop-mode="cropMode" @crop="addRegion"/><img v-else-if="selected.currentRevision?.mimeType.startsWith('image/')" class="image-preview" :src="contentUrl(selected.currentRevision)" alt=""><div v-else class="document-empty">Pré-visualização indisponível. <a v-if="selected.currentRevision" :href="contentUrl(selected.currentRevision)">Baixar arquivo</a></div><aside>
      <section v-if="!readOnly" v-default-form class="detail-edit"><h3>Documento</h3><input v-model="editName"><fieldset><legend>Tags</legend><label v-for="tag in tags" :key="tag.id"><input v-model="editTagIds" type="checkbox" :value="tag.id">{{ tag.name }}</label></fieldset><DefaultSubmitButton :disabled="savingDocument" @click="saveDocument">Salvar nome e tags</DefaultSubmitButton><button v-if="selected.kind === 'manual' && !selected.archivedAt" class="soft-btn" @click="revisionOpen = true">Nova revisão</button><button class="danger-link" @click="archiveOrRestore">{{ selected.archivedAt ? 'Restaurar documento' : 'Arquivar documento' }}</button></section>
      <section><h3>Revisões</h3><ol><li v-for="revision in revisions" :key="revision.id"><a :href="contentUrl(revision)">{{ revision.label }}</a><small>#{{ revision.sequence }} · {{ new Date(revision.createdAt).toLocaleString('pt-BR') }}</small></li></ol></section>
      <section v-if="selected.derivation && derivationDraft" v-default-form class="derivation-editor"><h3>Derivação</h3><p v-if="selected.processing?.errorMessage" class="run-error">{{ selected.processing.errorMessage }}</p><div class="geometry"><label>Página<input v-model.number="derivationDraft.page" type="number" min="1"></label><label>X<input v-model.number="derivationDraft.x" type="number" min="0" max="1" step="0.001"></label><label>Y<input v-model.number="derivationDraft.y" type="number" min="0" max="1" step="0.001"></label><label>Largura<input v-model.number="derivationDraft.width" type="number" min="0.001" max="1" step="0.001"></label><label>Altura<input v-model.number="derivationDraft.height" type="number" min="0.001" max="1" step="0.001"></label></div><label><input v-model="derivationDraft.autoRegenerate" type="checkbox"> Regenerar automaticamente</label><label><input v-model="derivationDraft.active" type="checkbox"> Derivação ativa</label><DefaultSubmitButton :disabled="readOnly" @click="saveDerivation">Salvar definição</DefaultSubmitButton><button class="soft-btn" :disabled="readOnly || !derivationDraft.active" @click="regenerate">{{ selected.processing?.status === 'failed' ? 'Tentar novamente' : 'Regenerar agora' }}</button></section>
      <template v-if="selected.currentRevision?.mimeType === 'application/pdf' && !readOnly && !selected.archivedAt"><button class="soft-btn crop-toggle" @click="cropMode = !cropMode">{{ cropMode ? 'Encerrar seleção' : 'Criar derivados' }}</button><div v-if="regions.length" v-default-form class="regions"><h3>Regiões</h3><article v-for="(region, index) in regions" :key="index"><input v-model="region.name" aria-label="Nome do derivado"><label><input v-model="region.autoRegenerate" type="checkbox">Regenerar automaticamente</label><fieldset><legend>Tags</legend><label v-for="tag in tags" :key="tag.id"><input v-model="region.tagIds" type="checkbox" :value="tag.id">{{ tag.name }}</label></fieldset><button @click="regions.splice(index, 1)">Remover</button></article><DefaultSubmitButton @click="createDerivations">Gerar {{ regions.length }} documento(s)</DefaultSubmitButton></div></template>
    </aside></div></section></div></Teleport>
  </main>
</template>

<style scoped>
.documents-workspace{max-width:1600px;margin:auto;padding:24px 28px 40px}.offline-banner{position:sticky;top:0;z-index:10;display:flex;justify-content:center;gap:14px;padding:10px;border-radius:9px;background:#fff3d8;color:#795a19}.documents-header{display:flex;align-items:end;justify-content:space-between;margin:12px 0 20px}.documents-header h1{margin:0;font-size:26px}.document-actions{display:flex;gap:8px}.document-error{padding:11px;border:1px solid #efc8c5;border-radius:9px;background:#fff7f6;color:#994c47}.library-layout{display:grid;grid-template-columns:240px minmax(0,1fr);gap:18px}.filters{display:flex;flex-direction:column;gap:12px;padding:16px;border:1px solid #e5e7ee;border-radius:12px;background:#fff;align-self:start}.filters label,.document-dialog label,.detail-edit label,.geometry label{display:grid;gap:6px;color:#667085;font-size:11px;font-weight:700}.filters input,.filters select,.document-dialog input,.detail-edit input,.geometry input,.regions input{height:38px;border:1px solid #dfe2ea;border-radius:8px;padding:0 10px;background:#fff}.inline-check{display:flex!important;align-items:center;gap:7px}.inline-check input,.tag-filter input,.document-dialog fieldset input,.detail-edit fieldset input,.regions fieldset input,.derivation-editor>label input{width:auto;height:auto}.tag-filter{display:grid;gap:5px;margin:0;padding:10px;border:1px solid #e3e5ec;border-radius:8px}.tag-row{display:flex;align-items:center}.tag-row>label{display:flex;align-items:center;gap:6px;min-width:0;flex:1;white-space:pre}.tag-actions{display:flex}.tag-actions button{border:0;background:none;color:#777}.tag-create{display:grid;gap:7px}.tag-create b{font-size:11px}.library-content{min-width:0}.document-list{overflow:hidden;border:1px solid #e1e4eb;border-radius:12px;background:#fff}.document-list-head,.document-row{display:grid;grid-template-columns:minmax(220px,2fr) 100px 180px minmax(150px,1fr) 110px;align-items:center;gap:12px;padding:12px 16px}.document-list-head{background:#f4f5f8;color:#6d7483;font-size:10px;font-weight:800}.document-row{width:100%;min-height:66px;border:0;border-top:1px solid #eceef3;background:#fff;color:#20283a;text-align:left}.document-row:hover{background:#faf9ff}.document-row .name{display:grid}.document-row .name small{color:#7566e8;font-size:8px;font-weight:800}.document-row .tags{display:flex;flex-wrap:wrap;gap:4px}.document-row .tags i{padding:3px 6px;border-radius:99px;background:#f0eefc;color:#6256c5;font-size:9px;font-style:normal}.document-row .state{font-size:10px;font-weight:800;color:#4c7b5a}.document-row .state.failed{color:#b24f49}.document-empty{min-height:220px;display:grid;place-content:center;text-align:center;color:#838b9b}.document-empty b,.document-empty span{display:block}.pagination{display:flex;justify-content:center;align-items:center;gap:12px;padding:16px}.pagination button{padding:7px 12px}.document-scrim{position:fixed;z-index:150;inset:0;display:grid;place-items:center;padding:20px;background:#11182766;backdrop-filter:blur(3px)}.document-dialog{width:min(520px,100%);padding:22px;border-radius:15px;background:#fff}.document-dialog header,.document-detail>header{display:flex;justify-content:space-between;align-items:flex-start}.document-dialog header button,.document-detail>header>button{border:0;background:#eef0f4;border-radius:8px;width:32px;height:32px;font-size:20px}.document-dialog>label{margin-top:14px}.document-dialog fieldset,.detail-edit fieldset,.regions fieldset{display:flex;flex-wrap:wrap;gap:8px;margin:15px 0;border:1px solid #e2e4eb;border-radius:9px}.document-dialog fieldset label,.detail-edit fieldset label,.regions fieldset label{display:flex;align-items:center;gap:5px}.document-dialog footer{display:flex;align-items:center;justify-content:flex-end;gap:12px}.document-detail{width:min(1400px,100%);height:min(900px,calc(100vh - 32px));display:flex;flex-direction:column;padding:20px;border-radius:15px;background:#fff}.document-detail>header{padding-bottom:15px}.document-detail>header small{color:#7a8292}.detail-body{min-height:0;flex:1;display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:16px}.detail-body>aside{overflow:auto;padding:14px;border:1px solid #e5e7ee;border-radius:11px}.detail-body>aside>section{padding-bottom:14px;border-bottom:1px solid #eceef3}.detail-body h3{font-size:12px}.detail-body ol{padding-left:20px}.detail-body li{margin-bottom:9px}.detail-body li a,.detail-body li small{display:block}.detail-edit{display:grid;gap:8px}.danger-link{border:0;background:none;color:#ae4843;text-align:left}.geometry{display:grid;grid-template-columns:repeat(2,1fr);gap:7px}.derivation-editor{display:grid;gap:8px}.derivation-editor>label{display:flex;align-items:center;gap:6px;font-size:11px}.run-error{padding:8px;background:#fff1ef;color:#a24640;font-size:11px}.crop-toggle{width:100%;margin-top:14px}.regions article{padding:9px;border:1px solid #e5e7ee;border-radius:8px;margin-bottom:8px}.regions article>label{display:flex;align-items:center;gap:5px;margin:7px 0}.regions article>label input{height:auto}.regions article>button{border:0;background:none;color:#b24f49;font-size:10px}.regions>.primary{width:100%}.image-preview{max-width:100%;max-height:100%;margin:auto}.soft-btn:disabled,.primary:disabled{opacity:.5;cursor:not-allowed}@media(max-width:900px){.documents-workspace{padding:16px 12px}.documents-header{align-items:flex-start;flex-direction:column;gap:12px}.library-layout{grid-template-columns:1fr}.document-list-head{display:none}.document-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:10px;border:0;background:none;overflow:visible}.document-row{display:flex;min-height:180px;flex-direction:column;align-items:flex-start;border:1px solid #e1e4eb;border-radius:12px;gap:8px}.document-row .state{margin-top:auto}.detail-body{grid-template-columns:1fr}.document-detail{height:100vh;border-radius:0}.detail-body>aside{max-height:340px}.document-actions{flex-wrap:wrap}}
</style>
