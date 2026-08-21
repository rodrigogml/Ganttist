import { civilDayOffset } from './timeline'
import type { TimeEndpoint } from '../composables/useTimeblockGesture'

export type DependencyType = 'FS' | 'SS' | 'FF' | 'SF'

export function shiftCivilDate(value: string, days: number): string {
  const date = new Date(value + 'T12:00:00')
  date.setDate(date.getDate() + days)
  return date.toISOString().slice(0, 10)
}

export function dependencyTypeFor(source: TimeEndpoint, target: TimeEndpoint): DependencyType {
  if (source === 'finish' && target === 'start') return 'FS'
  if (source === 'start' && target === 'start') return 'SS'
  if (source === 'finish' && target === 'finish') return 'FF'
  return 'SF'
}

export function resizePreview(input: {
  edge: TimeEndpoint
  originX: number
  pointerX: number
  dayWidth: number
  start: string
  finish: string
  earliestStart?: string | null
}): { start: string; finish: string; limited: boolean } {
  const delta = Math.round((input.pointerX - input.originX) / input.dayWidth)
  if (input.edge === 'finish') {
    const candidate = shiftCivilDate(input.finish, delta)
    return candidate < input.start
      ? { start: input.start, finish: input.start, limited: true }
      : { start: input.start, finish: candidate, limited: false }
  }

  let candidate = shiftCivilDate(input.start, delta)
  let limited = false
  if (input.earliestStart && candidate < input.earliestStart) {
    candidate = input.earliestStart
    limited = true
  }
  if (candidate > input.finish) {
    candidate = input.finish
    limited = true
  }
  return { start: candidate, finish: input.finish, limited }
}

export function inclusiveDuration(start: string, finish: string): number {
  return Math.max(1, civilDayOffset(start, finish) + 1)
}
