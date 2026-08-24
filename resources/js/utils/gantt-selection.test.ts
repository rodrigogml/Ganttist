import { describe, expect, it } from 'vitest'
import { selectGanttRow } from './gantt-selection'

const ids = ['a', 'b', 'c', 'd']

describe('Gantt row selection', () => {
  it('replaces selection on a plain click', () => {
    expect(selectGanttRow({ selected: ['a'], anchor: 'a', cursor: 'a' }, ids, 'c').selected).toEqual(['c'])
  })

  it('toggles one row with the additive modifier', () => {
    expect(selectGanttRow({ selected: ['a'], anchor: 'a', cursor: 'a' }, ids, 'c', { additive: true }).selected).toEqual(['a', 'c'])
    expect(selectGanttRow({ selected: ['a', 'c'], anchor: 'c', cursor: 'c' }, ids, 'c', { additive: true }).selected).toEqual(['a'])
  })

  it('replaces or extends selection with an anchored range', () => {
    const state = { selected: ['a'], anchor: 'b', cursor: 'b' }
    const range = selectGanttRow(state, ids, 'd', { range: true })
    expect(range.selected).toEqual(['b', 'c', 'd'])
    expect(range.anchor).toBe('b')
    expect(range.cursor).toBe('d')
    expect(selectGanttRow(state, ids, 'd', { range: true, additive: true }).selected).toEqual(['a', 'b', 'c', 'd'])
  })
})
