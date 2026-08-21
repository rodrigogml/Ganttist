import { computed, ref } from 'vue'

export type TimeEndpoint = 'start' | 'finish'

export type MoveGesture = {
  kind: 'move'
  taskId: string
  originX: number
  start: string
  finish: string
  previewStart: string
  previewFinish: string
  moved: boolean
  committing: boolean
}

export type ResizeGesture = {
  kind: 'resize'
  edge: TimeEndpoint
  taskId: string
  originX: number
  start: string
  finish: string
  earliestStart: string | null
  previewStart: string
  previewFinish: string
  moved: boolean
  limited: boolean
  committing: boolean
}

export type ConnectGesture = {
  kind: 'connect'
  taskId: string
  endpoint: TimeEndpoint
  pointerX: number
  pointerY: number
  targetTaskId: string | null
  targetEndpoint: TimeEndpoint | null
  committing: boolean
}

export type TimeblockGesture = MoveGesture | ResizeGesture | ConnectGesture

export function useTimeblockGesture() {
  const active = ref<TimeblockGesture | null>(null)
  const mode = computed(() => active.value?.kind ?? 'idle')

  function begin(gesture: TimeblockGesture): boolean {
    if (active.value !== null) return false
    active.value = gesture
    return true
  }

  function cancel(): void {
    if (active.value?.committing) return
    active.value = null
  }

  function finish(): void {
    active.value = null
  }

  return { active, mode, begin, cancel, finish }
}
