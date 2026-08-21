import type { Workspace } from '../types'

type RecordValue = Record<string, unknown>

function record(value: unknown, label: string): RecordValue {
  if (!value || typeof value !== 'object' || Array.isArray(value)) throw new Error(`Contrato de workspace inválido: ${label}.`)
  return value as RecordValue
}

function string(value: unknown, label: string): void {
  if (typeof value !== 'string') throw new Error(`Contrato de workspace inválido: ${label}.`)
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
    if (!['completed', 'blocked', 'scheduled', 'late', 'opened'].includes(item.status as string)) throw new Error('Contrato de workspace inválido: task.status.')
    if (item.start !== null && typeof item.start !== 'string') throw new Error('Contrato de workspace inválido: task.start.')
    if (item.finish !== null && typeof item.finish !== 'string') throw new Error('Contrato de workspace inválido: task.finish.')
    if (item.description !== undefined && item.description !== null && typeof item.description !== 'string') throw new Error('Contrato de workspace inválido: task.description.')
    for (const field of ['considered_start', 'considered_deadline', 'unlock_date', 'earliest_start']) if (item[field] !== undefined && item[field] !== null && typeof item[field] !== 'string') throw new Error(`Contrato de workspace inválido: task.${field}.`)
    if (item.completed !== undefined && typeof item.completed !== 'boolean') throw new Error('Contrato de workspace inválido: task.completed.')
  }
  for (const dependency of data.dependencies) {
    const item = record(dependency, 'dependency')
    for (const field of ['id', 'from', 'to', 'type']) string(item[field], `dependency.${field}`)
    if (!['FS', 'SS', 'FF', 'SF'].includes(item.type as string) || typeof item.critical !== 'boolean') throw new Error('Contrato de workspace inválido: dependency.')
  }

  return data as unknown as Workspace
}
