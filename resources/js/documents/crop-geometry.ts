import type { CropRect } from './types'

export type CropCorner = 'nw' | 'ne' | 'sw' | 'se'

export function normalizedPointer(clientX: number, clientY: number, bounds: Pick<DOMRect, 'left' | 'top' | 'width' | 'height'>): { x: number; y: number } {
  return {
    x: Math.max(0, Math.min(1, (clientX - bounds.left) / bounds.width)),
    y: Math.max(0, Math.min(1, (clientY - bounds.top) / bounds.height)),
  }
}

export function moveRect(initial: CropRect, dx: number, dy: number): CropRect {
  return { ...initial, x: Math.max(0, Math.min(1 - initial.width, initial.x + dx)), y: Math.max(0, Math.min(1 - initial.height, initial.y + dy)) }
}

export function resizeRect(initial: CropRect, dx: number, dy: number, corner: CropCorner, minimum = .005): CropRect {
  const west = corner.includes('w'), north = corner.includes('n')
  const left = Math.max(0, Math.min(initial.x + initial.width - minimum, west ? initial.x + dx : initial.x))
  const right = Math.min(1, Math.max(initial.x + minimum, west ? initial.x + initial.width : initial.x + initial.width + dx))
  const top = Math.max(0, Math.min(initial.y + initial.height - minimum, north ? initial.y + dy : initial.y))
  const bottom = Math.min(1, Math.max(initial.y + minimum, north ? initial.y + initial.height : initial.y + initial.height + dy))
  return { x: left, y: top, width: right - left, height: bottom - top }
}
