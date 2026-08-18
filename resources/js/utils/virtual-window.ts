export type VirtualWindow = { start: number; end: number }

/**
 * Returns the bounded slice of rows that needs DOM nodes for a scroll position.
 * Overscan keeps keyboard focus and pointer interactions stable at the edges.
 */
export function virtualWindow(total: number, rowHeight: number, scrollTop: number, viewportHeight: number, overscan = 12): VirtualWindow {
  if (total <= 0 || rowHeight <= 0 || viewportHeight <= 0) return { start: 0, end: 0 }

  const visibleRows = Math.ceil(viewportHeight / rowHeight)
  const start = Math.max(0, Math.floor(Math.max(0, scrollTop) / rowHeight) - overscan)
  const end = Math.min(total, start + visibleRows + overscan * 2)

  return { start, end }
}
