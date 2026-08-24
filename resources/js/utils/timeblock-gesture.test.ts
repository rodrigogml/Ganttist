import { describe, expect, it } from 'vitest'
import { dependencyTypeFor, inclusiveDuration, resizePreview } from './timeblock-gesture'

describe('timeblock gestures', () => {
  it('maps start and finish endpoints to canonical dependency types', () => {
    expect(dependencyTypeFor('finish', 'start')).toBe('FS')
    expect(dependencyTypeFor('start', 'start')).toBe('SS')
    expect(dependencyTypeFor('finish', 'finish')).toBe('FF')
    expect(dependencyTypeFor('start', 'finish')).toBe('SF')
  })

  it('snaps finish resize to whole days and clamps at one day', () => {
    expect(resizePreview({ edge: 'finish', originX: 100, pointerX: 184, dayWidth: 42, start: '2026-08-20', finish: '2026-08-22' })).toEqual({ start: '2026-08-20', finish: '2026-08-24', limited: false })
    expect(resizePreview({ edge: 'finish', originX: 100, pointerX: -100, dayWidth: 42, start: '2026-08-20', finish: '2026-08-22' })).toEqual({ start: '2026-08-20', finish: '2026-08-20', limited: true })
  })

  it('clamps start resize to dependency and deadline limits', () => {
    expect(resizePreview({ edge: 'start', originX: 100, pointerX: -100, dayWidth: 42, start: '2026-08-20', finish: '2026-08-25', earliestStart: '2026-08-18' })).toEqual({ start: '2026-08-18', finish: '2026-08-25', limited: true })
    expect(resizePreview({ edge: 'start', originX: 100, pointerX: 400, dayWidth: 42, start: '2026-08-20', finish: '2026-08-25' })).toEqual({ start: '2026-08-25', finish: '2026-08-25', limited: true })
    expect(inclusiveDuration('2026-08-20', '2026-08-25')).toBe(6)
  })
})
