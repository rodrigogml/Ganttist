import { describe, expect, it } from 'vitest'
import { parseWorkspaceResponse } from './workspace-contract'
import type { Workspace } from '../types'

const workspaceContractFixture = {
  data: {
    project: { id: 'project-1', name: 'Projeto', source: 'Local', sync_status: 'local', updated_at: '2026-09-21T12:00:00Z' },
    tasks: [
      { id: 'legacy', title: 'Legada', kind: 'task', level: 0, start: null, finish: null, plannedDurationWorkdays: null, resolved_duration_workdays: 1, schedule_constraint_state: 'satisfied', schedule_constraint_reason: null, progress: 0, status: 'opened', critical: false },
      { id: 'isolated', title: 'Estimativa', kind: 'task', level: 0, start: null, finish: null, plannedDurationWorkdays: 5, resolved_duration_workdays: 5, schedule_constraint_state: 'satisfied', schedule_constraint_reason: null, considered_start: '2026-09-21', considered_deadline: '2026-09-25', progress: 0, status: 'opened', critical: false },
      { id: 'anchored', title: 'Prazo', kind: 'task', level: 0, start: null, finish: '2026-09-25', plannedDurationWorkdays: 3, resolved_duration_workdays: 3, schedule_constraint_state: 'satisfied', schedule_constraint_reason: null, considered_start: '2026-09-23', considered_deadline: '2026-09-25', progress: 0, status: 'in_progress', critical: true },
      { id: 'violated', title: 'Restrita', kind: 'task', level: 0, start: null, finish: '2026-09-25', plannedDurationWorkdays: 2, resolved_duration_workdays: 2, schedule_constraint_state: 'violated', schedule_constraint_reason: 'A duração planejada não pode ser satisfeita junto do prazo após aplicar as dependências.', considered_start: '2026-09-29', considered_deadline: '2026-09-30', progress: 0, status: 'blocked', critical: true },
      { id: 'recurring', title: 'Rotina', kind: 'task', level: 0, start: '2026-09-29', finish: '2026-09-29', plannedDurationWorkdays: 1, resolved_duration_workdays: 1, schedule_constraint_state: 'satisfied', schedule_constraint_reason: null, progress: 0, status: 'scheduled', critical: false, participatesInFiniteNetwork: false, occurrenceHistoryCount: 2, recurrence: { expression: 'toda segunda', version: 3, endsOn: null, rule: { version: 1, basis: 'fixed', frequency: 'weekly', interval: 1, intervalUnit: null, weekdays: [1], monthDays: [], annualDates: [], monthlyOrdinal: null, monthlyOrdinalWeekday: null, workdayPosition: null, startsOn: null, endsOn: null } }, occurrence: { logicalDate: '2026-09-28', scheduledStart: '2026-09-29', scheduledFinish: '2026-09-29', consideredStart: '2026-09-29', consideredDeadline: '2026-09-29', snoozed: true } },
    ],
    dependencies: [{ id: 'dependency-1', from: 'anchored', to: 'violated', type: 'FS', critical: true, constraint_state: 'active' }],
    people: [{ id: 'person-1', name: 'Pessoa', email: 'pessoa@example.test', linkedUserId: 'user-1' }],
    stats: { progress: 0, completed: 0, total: 4, critical: 2, without_dates: 2, without_duration: 1, finite: { totalTasks: 4, completedTasks: 0, progressPercent: 0, projectFinish: '2026-09-25', criticalTaskCount: 2 }, operational: { recurringTaskCount: 1, openRecurringOccurrenceCount: 1, overdueRecurringOccurrenceCount: 0, snoozedRecurringOccurrenceCount: 1 } },
  },
}

describe('workspace duration contract', () => {
  it('accepts the complete backend shape consumed by the SPA', () => {
    const workspace: Workspace = parseWorkspaceResponse(workspaceContractFixture)

    expect(workspace.tasks.map(task => task.plannedDurationWorkdays)).toEqual([null, 5, 3, 2, 1])
    expect(workspace.tasks[3].schedule_constraint_state).toBe('violated')
    expect(workspace.stats.without_duration).toBe(1)
    expect(workspace.tasks[4].occurrence?.snoozed).toBe(true)
    expect(workspace.stats.operational?.recurringTaskCount).toBe(1)
    expect(workspace.people).toEqual([{ id: 'person-1', name: 'Pessoa', email: 'pessoa@example.test', linkedUserId: 'user-1' }])
  })

  it('rejects a task leaf that omits an authoritative duration field', () => {
    const invalid = structuredClone(workspaceContractFixture)
    delete (invalid.data.tasks[0] as Record<string, unknown>).resolved_duration_workdays

    expect(() => parseWorkspaceResponse(invalid)).toThrow('task.resolved_duration_workdays')
  })

  it('rejects an invalid linked user reference in the people projection', () => {
    const invalid = structuredClone(workspaceContractFixture)
    invalid.data.people[0].linkedUserId = 42 as unknown as string

    expect(() => parseWorkspaceResponse(invalid)).toThrow('person.linkedUserId')
  })
})
