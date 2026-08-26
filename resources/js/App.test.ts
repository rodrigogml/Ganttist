import { describe, expect, it } from 'vitest'
import { parseWorkspaceResponse } from './contracts/workspace-contract'

describe('autonomous workspace', () => {
  it('accepts the local workspace contract without a Todoist source', () => {
    const workspace = parseWorkspaceResponse({ data: { project: { id: 'local', name: 'Local', source: 'Local', sync_status: 'local', updated_at: '2026-08-25T00:00:00Z' }, tasks: [], dependencies: [], stats: { progress: 0, completed: 0, total: 0, critical: 0 } } })
    expect(workspace.project.source).toBe('Local')
  })
})
