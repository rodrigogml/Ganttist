import { describe, expect, it } from 'vitest'
import { parseWorkspaceResponse } from './contracts/workspace-contract'

describe('autonomous workspace', () => {
  it('accepts the local workspace contract without a Todoist source', () => {
    const workspace = parseWorkspaceResponse({ data: { project: { id: 'local', name: 'Local', source: 'Local', sync_status: 'local', updated_at: '2026-08-25T00:00:00Z' }, tasks: [], dependencies: [], stats: { progress: 0, completed: 0, total: 0, critical: 0 } } })
    expect(workspace.project.source).toBe('Local')
  })

  it('accepts the in-progress calculated status', () => {
    const workspace = parseWorkspaceResponse({ data: { project: { id: 'local', name: 'Local', source: 'Local', sync_status: 'local', updated_at: '2026-08-25T00:00:00Z' }, tasks: [{ id: 'task', title: 'Entrega', kind: 'task', level: 0, start: '2026-08-25', finish: '2026-08-29', progress: 0, status: 'in_progress', critical: false }], dependencies: [], stats: { progress: 0, completed: 0, total: 1, critical: 0 } } })
    expect(workspace.tasks[0].status).toBe('in_progress')
  })
})
