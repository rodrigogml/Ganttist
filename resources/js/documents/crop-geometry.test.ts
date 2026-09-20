import { describe, expect, it } from 'vitest'
import { moveRect, normalizedPointer, resizeRect } from './crop-geometry'

describe('normalized PDF crop geometry', () => {
  it('does not depend on canvas zoom or device pixels', () => {
    expect(normalizedPointer(300, 250, { left: 100, top: 50, width: 400, height: 400 })).toEqual({ x: .5, y: .5 })
    expect(normalizedPointer(500, 450, { left: 100, top: 50, width: 800, height: 800 })).toEqual({ x: .5, y: .5 })
  })

  it('moves a rectangle without allowing it to leave the page', () => {
    expect(moveRect({ x: .7, y: .7, width: .2, height: .2 }, .4, .4)).toEqual({ x: .8, y: .8, width: .2, height: .2 })
  })

  it('resizes from every corner and clamps to normalized bounds', () => {
    const northwest = resizeRect({ x: .2, y: .2, width: .4, height: .4 }, -.4, -.4, 'nw')
    expect(northwest.x).toBe(0); expect(northwest.y).toBe(0); expect(northwest.width).toBeCloseTo(.6); expect(northwest.height).toBeCloseTo(.6)
    const southeast = resizeRect({ x: .2, y: .2, width: .4, height: .4 }, .8, .8, 'se')
    expect(southeast.x).toBe(.2); expect(southeast.y).toBe(.2); expect(southeast.width).toBeCloseTo(.8); expect(southeast.height).toBeCloseTo(.8)
  })
})
