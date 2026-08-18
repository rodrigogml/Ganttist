import { describe, expect, it } from 'vitest'
import { virtualWindow } from './virtual-window'

describe('virtual Gantt row window', () => {
  it('renders a bounded first window for a 2k-task project', () => {
    expect(virtualWindow(2_000, 49, 0, 620)).toEqual({ start: 0, end: 37 })
  })

  it('renders a bounded window near the end of a 5k-task project', () => {
    const window = virtualWindow(5_000, 49, 49 * 4_990, 620)
    expect(window.start).toBe(4_978)
    expect(window.end).toBe(5_000)
    expect(window.end - window.start).toBeLessThan(50)
  })

  it('keeps every sampled scroll position bounded across 2k and 5k tasks', () => {
    const startedAt = performance.now()
    for (const total of [2_000, 5_000]) {
      for (let row = 0; row < total; row += 17) {
        const window = virtualWindow(total, 49, row * 49, 620)
        expect(window.start).toBeGreaterThanOrEqual(0)
        expect(window.end).toBeLessThanOrEqual(total)
        expect(window.end - window.start).toBeLessThanOrEqual(37)
      }
    }
    expect(performance.now() - startedAt).toBeLessThan(250)
  })

  it('has no invalid range for empty or invalid dimensions', () => {
    expect(virtualWindow(0, 49, 0, 620)).toEqual({ start: 0, end: 0 })
    expect(virtualWindow(10, 0, 0, 620)).toEqual({ start: 0, end: 0 })
  })
})
