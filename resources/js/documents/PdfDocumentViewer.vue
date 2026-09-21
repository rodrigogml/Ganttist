<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import * as pdfjs from 'pdfjs-dist'
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url'
import type { CropRect } from './types'
import { moveRect, normalizedPointer, resizeRect, type CropCorner } from './crop-geometry'

pdfjs.GlobalWorkerOptions.workerSrc = workerUrl
const props = defineProps<{ url: string; cropMode?: boolean }>()
const emit = defineEmits<{ crop: [value: { page: number; rectNormalized: CropRect }] }>()
const canvas = ref<HTMLCanvasElement | null>(null), overlay = ref<HTMLDivElement | null>(null), scroll = ref<HTMLDivElement | null>(null)
const pageNumber = ref(1), pageCount = ref(0), scale = ref(1), loading = ref(false), error = ref('')
const selection = ref<CropRect | null>(null)
let documentHandle: Awaited<ReturnType<typeof pdfjs.getDocument>['promise']> | null = null
let gesture: { mode: 'create' | 'move' | 'resize'; start: { x: number; y: number }; initial: CropRect; corner?: CropCorner } | null = null
const selectionStyle = computed(() => selection.value ? ({ left: `${selection.value.x * 100}%`, top: `${selection.value.y * 100}%`, width: `${selection.value.width * 100}%`, height: `${selection.value.height * 100}%` }) : {})

async function load(): Promise<void> {
  loading.value = true; error.value = ''; selection.value = null
  try { documentHandle = await pdfjs.getDocument({ url: props.url, withCredentials: true }).promise; pageCount.value = documentHandle.numPages; pageNumber.value = 1; await render() }
  catch { error.value = 'Não foi possível abrir este PDF.' }
  finally { loading.value = false }
}
async function render(): Promise<void> {
  if (!documentHandle) return
  await nextTick()
  const page = await documentHandle.getPage(pageNumber.value), viewport = page.getViewport({ scale: scale.value }), target = canvas.value
  if (!target) return
  const ratio = window.devicePixelRatio || 1
  target.width = Math.floor(viewport.width * ratio); target.height = Math.floor(viewport.height * ratio); target.style.width = `${viewport.width}px`; target.style.height = `${viewport.height}px`
  await page.render({ canvas: target, canvasContext: target.getContext('2d')!, viewport, transform: ratio === 1 ? undefined : [ratio, 0, 0, ratio, 0, 0] }).promise
}
function pointerPosition(event: PointerEvent): { x: number; y: number } { return normalizedPointer(event.clientX, event.clientY, overlay.value!.getBoundingClientRect()) }
function beginCrop(event: PointerEvent): void { if (!props.cropMode || event.target !== overlay.value) return; const start = pointerPosition(event); selection.value = { ...start, width: 0, height: 0 }; gesture = { mode: 'create', start, initial: selection.value }; overlay.value?.setPointerCapture(event.pointerId) }
function beginMove(event: PointerEvent): void { if (!selection.value) return; event.stopPropagation(); gesture = { mode: 'move', start: pointerPosition(event), initial: { ...selection.value } }; overlay.value?.setPointerCapture(event.pointerId) }
function beginResize(event: PointerEvent, corner: CropCorner): void { if (!selection.value) return; event.stopPropagation(); gesture = { mode: 'resize', corner, start: pointerPosition(event), initial: { ...selection.value } }; overlay.value?.setPointerCapture(event.pointerId) }
function moveCrop(event: PointerEvent): void {
  if (!gesture) return
  const point = pointerPosition(event), dx = point.x - gesture.start.x, dy = point.y - gesture.start.y, initial = gesture.initial
  if (gesture.mode === 'create') selection.value = { x: Math.min(gesture.start.x, point.x), y: Math.min(gesture.start.y, point.y), width: Math.abs(point.x - gesture.start.x), height: Math.abs(point.y - gesture.start.y) }
  else if (gesture.mode === 'move') selection.value = moveRect(initial, dx, dy)
  else selection.value = resizeRect(initial, dx, dy, gesture.corner!)
}
function finishCrop(): void { gesture = null; if (selection.value && (selection.value.width <= .005 || selection.value.height <= .005)) selection.value = null }
function addSelection(): void { if (!selection.value) return; emit('crop', { page: pageNumber.value, rectNormalized: { ...selection.value } }); selection.value = null }
async function changePage(delta: number): Promise<void> { pageNumber.value = Math.max(1, Math.min(pageCount.value, pageNumber.value + delta)); selection.value = null; await render() }
async function zoom(delta: number): Promise<void> { scale.value = Math.max(.5, Math.min(3, scale.value + delta)); selection.value = null; await render() }
function clampScale(value: number): number { return Math.max(.5, Math.min(3, value)) }
async function fitWidth(): Promise<void> { if (!documentHandle) return; const page = await documentHandle.getPage(pageNumber.value), base = page.getViewport({ scale: 1 }), available = scroll.value?.clientWidth ?? base.width; scale.value = clampScale((available - 36) / base.width); selection.value = null; await render() }
async function fitPage(): Promise<void> { if (!documentHandle) return; const page = await documentHandle.getPage(pageNumber.value), base = page.getViewport({ scale: 1 }), availableWidth = scroll.value?.clientWidth ?? base.width, availableHeight = scroll.value?.clientHeight ?? base.height; scale.value = clampScale(Math.min((availableWidth - 36) / base.width, (availableHeight - 36) / base.height)); selection.value = null; await render() }
watch(() => props.url, load, { immediate: true })
watch(() => props.cropMode, enabled => { if (!enabled) selection.value = null })
onBeforeUnmount(() => { void documentHandle?.cleanup() })
</script>

<template>
  <section class="pdf-viewer">
    <header><button :disabled="pageNumber <= 1" @click="changePage(-1)">‹</button><span>Página {{ pageNumber }} de {{ pageCount || '…' }}</span><button :disabled="pageNumber >= pageCount" @click="changePage(1)">›</button><i></i><button @click="zoom(-.2)">−</button><span>{{ Math.round(scale * 100) }}%</span><button @click="zoom(.2)">+</button><button class="fit" @click="fitWidth">Largura</button><button class="fit" @click="fitPage">Página</button><button v-if="cropMode && selection" class="add-region" @click="addSelection">Adicionar região</button></header>
    <p v-if="cropMode" class="crop-warning">Recorte visual: não use como redação segura; conteúdo fora da janela pode permanecer recuperável no PDF.</p>
    <p v-if="error" class="viewer-error">{{ error }}</p>
    <div v-else ref="scroll" class="pdf-scroll"><div class="pdf-page"><canvas ref="canvas"></canvas><div ref="overlay" class="crop-overlay" :class="{ active: cropMode }" @pointerdown="beginCrop" @pointermove="moveCrop" @pointerup="finishCrop"><span v-if="selection" class="crop-selection" :style="selectionStyle" @pointerdown="beginMove"><i class="handle nw" @pointerdown="beginResize($event, 'nw')"></i><i class="handle ne" @pointerdown="beginResize($event, 'ne')"></i><i class="handle sw" @pointerdown="beginResize($event, 'sw')"></i><i class="handle se" @pointerdown="beginResize($event, 'se')"></i></span></div></div></div>
  </section>
</template>

<style scoped>
.pdf-viewer{min-height:420px;display:flex;flex-direction:column;border:1px solid #e2e4ec;border-radius:12px;background:#eef0f4;overflow:hidden}.pdf-viewer>header{min-height:44px;display:flex;align-items:center;justify-content:center;gap:8px;padding:4px;background:#171d2d;color:#fff}.pdf-viewer button{width:30px;height:28px;border:0;border-radius:6px;background:#2b3348;color:#fff}.pdf-viewer button.fit,.pdf-viewer button.add-region{width:auto;padding:0 9px}.pdf-viewer button.add-region{background:var(--action-primary-gradient)}.pdf-viewer header>i{width:1px;height:20px;background:#ffffff25;margin:0 8px}.crop-warning{margin:0;padding:8px 12px;background:#fff3d8;color:#765817;font-size:11px;text-align:center}.pdf-scroll{flex:1;overflow:auto;padding:18px}.pdf-page{position:relative;width:max-content;margin:auto;box-shadow:0 8px 22px #11182730}.pdf-page canvas{display:block;background:#fff}.crop-overlay{position:absolute;inset:0}.crop-overlay.active{cursor:crosshair;touch-action:none}.crop-selection{position:absolute;border:2px solid #7566e8;background:#7566e829;pointer-events:auto;cursor:move}.handle{position:absolute;width:12px;height:12px;border:2px solid #fff;border-radius:50%;background:#7566e8}.handle.nw{left:-7px;top:-7px;cursor:nwse-resize}.handle.ne{right:-7px;top:-7px;cursor:nesw-resize}.handle.sw{left:-7px;bottom:-7px;cursor:nesw-resize}.handle.se{right:-7px;bottom:-7px;cursor:nwse-resize}.viewer-error{margin:auto;color:#b04f49}
.pdf-viewer button.add-region{background:var(--button-primary-gradient)}.crop-selection{border-color:var(--button-primary-start)}.handle{background:var(--button-primary-start)}
</style>
