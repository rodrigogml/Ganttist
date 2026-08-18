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
  for (const field of ['progress', 'completed', 'total', 'critical', 'unscheduled']) if (typeof stats[field] !== 'number') throw new Error(`Contrato de workspace inválido: stats.${field}.`)
  for (const task of data.tasks) {
    const item = record(task, 'task')
    for (const field of ['id', 'title', 'kind', 'status']) string(item[field], `task.${field}`)
    if (typeof item.level !== 'number' || !['task', 'group'].includes(item.kind as string)) throw new Error('Contrato de workspace inválido: task.kind/level.')
    if (item.start !== null && typeof item.start !== 'string') throw new Error('Contrato de workspace inválido: task.start.')
    if (item.finish !== null && typeof item.finish !== 'string') throw new Error('Contrato de workspace inválido: task.finish.')
  }
  for (const dependency of data.dependencies) {
    const item = record(dependency, 'dependency')
    for (const field of ['id', 'from', 'to', 'type']) string(item[field], `dependency.${field}`)
    if (!['FS', 'SS', 'FF', 'SF'].includes(item.type as string) || typeof item.critical !== 'boolean') throw new Error('Contrato de workspace inválido: dependency.')
  }

  return data as unknown as Workspace
}
