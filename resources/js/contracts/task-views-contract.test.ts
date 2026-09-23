import { describe, expect, it } from 'vitest'
import { parseSavedTaskView, parseViewsResponse } from './task-views-contract'

const view = { id: '01VIEW', name: 'Minhas Tarefas', query: 'status:aberta', visualState: { version: 1, gantt: { zoom: 'week' } }, formatVersion: 1 }

describe('views API contract', () => {
  it('accepts API lists and import responses in the SPA shape', () => {
    expect(parseViewsResponse({ data: [view] })).toEqual([view])
    expect(parseSavedTaskView(view)).toEqual(view)
  })
  it('rejects an invalid format version', () => {
    expect(() => parseSavedTaskView({ ...view, formatVersion: 2 })).toThrow('formatVersion')
  })
})
