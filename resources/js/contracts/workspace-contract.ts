import type { Workspace } from '../types'

type RecordValue = Record<string, unknown>

function record(value: unknown, label: string): RecordValue {
  if (!value || typeof value !== 'object' || Array.isArray(value)) throw new Error(`Contrato de workspace inválido: ${label}.`)
  return value as RecordValue
}

function string(value: unknown, label: string): void {
  if (typeof value !== 'string') throw new Error(`Contrato de workspace inválido: ${label}.`)
}

function nullableString(value: unknown, label: string): void {
  if (value !== null && typeof value !== 'string') throw new Error(`Contrato de workspace inválido: ${label}.`)
}

export function parseWorkspaceResponse(payload: unknown): Workspace {
  const root = record(payload, 'resposta')
  const data = record(root.data, 'data')
  const project = record(data.project, 'project')
  for (const field of ['id', 'name', 'source', 'sync_status', 'updated_at']) string(project[field], `project.${field}`)
  if (!Array.isArray(data.tasks) || !Array.isArray(data.dependencies)) throw new Error('Contrato de workspace inválido: coleções.')
  const stats = record(data.stats, 'stats')
  for (const field of ['progress', 'completed', 'total', 'critical']) if (typeof stats[field] !== 'number') throw new Error(`Contrato de workspace inválido: stats.${field}.`)
  for (const task of data.tasks) {
    const item = record(task, 'task')
    for (const field of ['id', 'title', 'kind', 'status']) string(item[field], `task.${field}`)
    if (typeof item.level !== 'number' || !['task', 'section'].includes(item.kind as string)) throw new Error('Contrato de workspace inválido: task.kind/level.')
    if (!['completed', 'blocked', 'scheduled', 'late', 'in_progress', 'opened'].includes(item.status as string)) throw new Error('Contrato de workspace inválido: task.status.')
    if (item.start !== null && typeof item.start !== 'string') throw new Error('Contrato de workspace inválido: task.start.')
    if (item.finish !== null && typeof item.finish !== 'string') throw new Error('Contrato de workspace inválido: task.finish.')
    if (item.kind === 'task') {
      if (item.plannedDurationWorkdays !== null && typeof item.plannedDurationWorkdays !== 'number') throw new Error('Contrato de workspace inválido: task.plannedDurationWorkdays.')
      if (!Number.isInteger(item.resolved_duration_workdays) || (item.resolved_duration_workdays as number) < 1) throw new Error('Contrato de workspace inválido: task.resolved_duration_workdays.')
      if (!['satisfied', 'violated'].includes(item.schedule_constraint_state as string)) throw new Error('Contrato de workspace inválido: task.schedule_constraint_state.')
      if (item.schedule_constraint_reason !== null && typeof item.schedule_constraint_reason !== 'string') throw new Error('Contrato de workspace inválido: task.schedule_constraint_reason.')
    }
    if (item.description !== undefined && item.description !== null && typeof item.description !== 'string') throw new Error('Contrato de workspace inválido: task.description.')
    if (item.assignee_id !== undefined && item.assignee_id !== null && typeof item.assignee_id !== 'string') throw new Error('Contrato de workspace inválido: task.assignee_id.')
    if (item.comment_count !== undefined && (typeof item.comment_count !== 'number' || item.comment_count < 0)) throw new Error('Contrato de workspace inválido: task.comment_count.')
    if (item.checklist !== undefined) {
      if (!Array.isArray(item.checklist)) throw new Error('Contrato de workspace inválido: task.checklist.')
      for (const checklistItem of item.checklist) {
        const checklist = record(checklistItem, 'task.checklist')
        for (const field of ['id', 'text']) string(checklist[field], `task.checklist.${field}`)
        if (typeof checklist.completed !== 'boolean' || typeof checklist.position !== 'number') throw new Error('Contrato de workspace inválido: task.checklist.')
      }
    }
    for (const field of ['considered_start', 'considered_deadline', 'unlock_date', 'earliest_start']) if (item[field] !== undefined && item[field] !== null && typeof item[field] !== 'string') throw new Error(`Contrato de workspace inválido: task.${field}.`)
    if (item.completed !== undefined && typeof item.completed !== 'boolean') throw new Error('Contrato de workspace inválido: task.completed.')
    if (item.participatesInFiniteNetwork !== undefined && typeof item.participatesInFiniteNetwork !== 'boolean') throw new Error('Contrato de workspace inválido: task.participatesInFiniteNetwork.')
    if (item.occurrenceHistoryCount !== undefined && (!Number.isInteger(item.occurrenceHistoryCount) || (item.occurrenceHistoryCount as number) < 0)) throw new Error('Contrato de workspace inválido: task.occurrenceHistoryCount.')
    if (item.recurrence !== undefined && item.recurrence !== null) {
      const recurrence = record(item.recurrence, 'task.recurrence')
      string(recurrence.expression, 'task.recurrence.expression')
      if (!Number.isInteger(recurrence.version) || (recurrence.version as number) < 0) throw new Error('Contrato de workspace inválido: task.recurrence.version.')
      nullableString(recurrence.endsOn, 'task.recurrence.endsOn')
      const rule = record(recurrence.rule, 'task.recurrence.rule')
      if (!['fixed', 'interval'].includes(rule.basis as string) || !['daily', 'weekly', 'monthly', 'yearly'].includes(rule.frequency as string) || !Number.isInteger(rule.interval) || (rule.interval as number) < 1) throw new Error('Contrato de workspace inválido: task.recurrence.rule.')
      for (const field of ['weekdays', 'monthDays', 'annualDates']) if (!Array.isArray(rule[field])) throw new Error(`Contrato de workspace inválido: task.recurrence.rule.${field}.`)
    }
    if (item.occurrence !== undefined && item.occurrence !== null) {
      const occurrence = record(item.occurrence, 'task.occurrence')
      for (const field of ['logicalDate', 'consideredStart', 'consideredDeadline']) string(occurrence[field], `task.occurrence.${field}`)
      for (const field of ['scheduledStart', 'scheduledFinish']) nullableString(occurrence[field], `task.occurrence.${field}`)
      if (typeof occurrence.snoozed !== 'boolean') throw new Error('Contrato de workspace inválido: task.occurrence.snoozed.')
    }
  }
  for (const dependency of data.dependencies) {
    const item = record(dependency, 'dependency')
    for (const field of ['id', 'from', 'to', 'type']) string(item[field], `dependency.${field}`)
    if (!['FS', 'SS', 'FF', 'SF'].includes(item.type as string) || typeof item.critical !== 'boolean') throw new Error('Contrato de workspace inválido: dependency.')
    for (const field of ['from_kind', 'to_kind']) if (item[field] !== undefined && !['task', 'section'].includes(item[field] as string)) throw new Error('Contrato de workspace inválido: dependency endpoint kind.')
    if (item.constraint_state !== undefined && !['active', 'violated'].includes(item.constraint_state as string)) throw new Error('Contrato de workspace inválido: dependency state.')
  }
  if (data.people !== undefined) {
    if (!Array.isArray(data.people)) throw new Error('Contrato de workspace inválido: people.')
    for (const person of data.people) {
      const item = record(person, 'person')
      for (const field of ['id', 'name']) string(item[field], `person.${field}`)
      if (item.email !== undefined && item.email !== null && typeof item.email !== 'string') throw new Error('Contrato de workspace inválido: person.email.')
      if (item.linkedUserId !== undefined && item.linkedUserId !== null && typeof item.linkedUserId !== 'string') throw new Error('Contrato de workspace inválido: person.linkedUserId.')
    }
  }
  if (stats.finite !== undefined) {
    const finite = record(stats.finite, 'stats.finite')
    for (const field of ['totalTasks', 'completedTasks', 'progressPercent', 'criticalTaskCount']) if (!Number.isInteger(finite[field]) || (finite[field] as number) < 0) throw new Error(`Contrato de workspace inválido: stats.finite.${field}.`)
    nullableString(finite.projectFinish, 'stats.finite.projectFinish')
  }
  if (stats.operational !== undefined) {
    const operational = record(stats.operational, 'stats.operational')
    for (const field of ['recurringTaskCount', 'openRecurringOccurrenceCount', 'overdueRecurringOccurrenceCount', 'snoozedRecurringOccurrenceCount']) if (!Number.isInteger(operational[field]) || (operational[field] as number) < 0) throw new Error(`Contrato de workspace inválido: stats.operational.${field}.`)
  }

  return data as unknown as Workspace
}
