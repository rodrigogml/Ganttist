import { computed, ref, watch } from 'vue'
import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import { parseWorkspaceResponse } from '../contracts/workspace-contract'
import type { Dependency, Task, TaskStatus, Workspace } from '../types'
import { parseTaskQuery } from '../utils/task-query'

export const workspaceTaskStatuses: readonly TaskStatus[] = ['opened', 'in_progress', 'scheduled', 'late', 'blocked', 'completed']
export const unblockedTaskStatuses: readonly TaskStatus[] = ['opened', 'in_progress', 'scheduled', 'late']
const activeProjectStorageKey = 'ganttist.active-project-id'
const activeProjectStorage = () => typeof localStorage === 'undefined' ? null : localStorage

export const useWorkspaceStore = defineStore('workspace', () => {
  const workspace = ref<Workspace | null>(null)
  const loading = ref(true)
  const refreshing = ref(false)
  const stale = ref(false)
  const error = ref('')
  const search = ref('')
  const initialTaskQuery = parseTaskQuery('')
  if (!initialTaskQuery.valid) throw new Error('A consulta vazia deve ser válida.')
  const activeTaskQuery = ref(initialTaskQuery)
  const searchError = ref('')
  const statusFilters = ref<TaskStatus[]>([...workspaceTaskStatuses])
  const assigneeFilters = ref<string[]>([])
  const periodStart = ref('')
  const periodEnd = ref('')
  const selected = ref<string[]>([])
  const zoom = ref<'day' | 'week' | 'month'>('week')
  const hiddenGroups = ref(new Set<string>())
  const filterExceptions = ref(new Set<string>())
  const relationshipFocusTaskId = ref<string | null>(null)
  watch(search, value => {
    const query = parseTaskQuery(value)
    if (query.valid) {
      activeTaskQuery.value = query
      searchError.value = ''
      if (value.trim()) revealSearchResults()
    } else {
      searchError.value = query.error.message
    }
  }, { flush: 'sync' })
  watch([search, statusFilters, assigneeFilters, periodStart, periodEnd], () => {
    if (filterExceptions.value.size) filterExceptions.value = new Set()
    if (relationshipFocusTaskId.value) relationshipFocusTaskId.value = null
  }, { flush: 'sync' })
  const matchesTaskFilters = (task: Task): boolean => {
    if (!activeTaskQuery.value.matches(task.title)) return false
    if (task.kind === 'task' && !statusFilters.value.includes(task.status)) return false
    if (task.kind === 'task' && assigneeFilters.value.length) {
      const assignee = task.assignee_id ?? '__unassigned__'
      if (!assigneeFilters.value.includes(assignee)) return false
    }
    if (task.kind === 'task' && (periodStart.value || periodEnd.value)) {
      const today = new Date().toISOString().slice(0, 10)
      const start = task.considered_start ?? task.start ?? today
      const finish = task.considered_deadline ?? task.finish ?? start
      if (periodStart.value && finish < periodStart.value) return false
      if (periodEnd.value && start > periodEnd.value) return false
    }
    return true
  }
  const tasks = computed(() => {
    const source = workspace.value?.tasks ?? []
    const byId = new Map(source.map(task => [task.id, task]))
    const visibleIds = relationshipFocusTaskId.value
      ? relationshipFocusIds(relationshipFocusTaskId.value, source, workspace.value?.dependencies ?? [])
      : new Set([
          ...source.filter(matchesTaskFilters).map(task => task.id),
          ...filterExceptions.value,
        ])

    for (const task of source) {
      if (!visibleIds.has(task.id)) continue
      let parentId = task.parent_id
      while (parentId) {
        visibleIds.add(parentId)
        parentId = byId.get(parentId)?.parent_id
      }
    }

    return source.filter(task => visibleIds.has(task.id))
  })
  const empty = computed(() => workspace.value !== null && workspace.value.tasks.length === 0)
  let activeLoad: { projectId: string | undefined; promise: Promise<void> } | null = null

  function load(projectId?: string): Promise<void> {
    const requestedProjectId = projectId ?? workspace.value?.project.id
    if (activeLoad) {
      if (activeLoad.projectId === requestedProjectId) return activeLoad.promise
      return activeLoad.promise.then(() => load(projectId))
    }
    const promise = performLoad(projectId).finally(() => { activeLoad = null })
    activeLoad = { projectId: requestedProjectId, promise }

    return promise
  }

  async function performLoad(projectId?: string): Promise<void> {
    const initialLoad = workspace.value === null
    if (initialLoad) loading.value = true
    else refreshing.value = true
    error.value = ''
    try {
      const response = await fetch(`/api/v1/projects/${projectId ?? workspace.value?.project.id}/workspace`)
      if (useAuthStore().handleUnauthorized(response)) return
      if (!response.ok) {
        if ([403, 404].includes(response.status)) activeProjectStorage()?.removeItem(activeProjectStorageKey)
        throw new Error('Não foi possível carregar o projeto.')
      }
      workspace.value = parseWorkspaceResponse(await response.json())
      activeProjectStorage()?.setItem(activeProjectStorageKey, workspace.value.project.id)
      stale.value = false
    } catch (exception) {
      const message = exception instanceof Error ? exception.message : 'Erro inesperado'
      if (initialLoad) error.value = message
      else stale.value = true
    } finally {
      if (initialLoad) loading.value = false
      refreshing.value = false
    }
  }

  function toggleSelect(id: string, additive = false): void {
    if (!additive) selected.value = []
    selected.value = selected.value.includes(id) ? selected.value.filter(value => value !== id) : [...selected.value, id]
  }

  function toggleGroup(id: string): void {
    const next = new Set(hiddenGroups.value)
    next.has(id) ? next.delete(id) : next.add(id)
    hiddenGroups.value = next
  }

  function expandableGroupIds(): Set<string> {
    const source = workspace.value?.tasks ?? []
    const ids = new Set(source.filter(task => task.has_children).map(task => task.id))
    for (const task of source) if (task.parent_id) ids.add(task.parent_id)
    return ids
  }

  function collapseAllGroups(): void {
    hiddenGroups.value = expandableGroupIds()
  }

  function expandAllGroups(): void {
    hiddenGroups.value = new Set()
  }

  function expandIntermediateGroups(): void {
    const source = workspace.value?.tasks ?? []
    const expandable = expandableGroupIds()
    const intermediate = new Set(
      source
        .filter(task => expandable.has(task.id))
        .filter(task => source.some(child => child.parent_id === task.id && child.kind === 'section'))
        .map(task => task.id),
    )
    hiddenGroups.value = new Set([...expandable].filter(id => !intermediate.has(id)))
  }

  function clearTaskFilters(): void {
    search.value = ''
    statusFilters.value = [...workspaceTaskStatuses]
    assigneeFilters.value = []
    periodStart.value = ''
    periodEnd.value = ''
    filterExceptions.value = new Set()
    relationshipFocusTaskId.value = null
  }

  function focusTaskRelations(id: string): void {
    const source = workspace.value?.tasks ?? []
    const task = source.find(current => current.id === id)
    if (!task || task.kind !== 'task') return

    relationshipFocusTaskId.value = null
    search.value = ''
    statusFilters.value = [...workspaceTaskStatuses]
    assigneeFilters.value = []
    periodStart.value = ''
    periodEnd.value = ''
    filterExceptions.value = new Set()
    relationshipFocusTaskId.value = id

    const taskById = new Map(source.map(current => [current.id, current]))
    const focusedIds = relationshipFocusIds(id, source, workspace.value?.dependencies ?? [])
    const next = new Set(hiddenGroups.value)
    for (const focusedId of focusedIds) {
      let current = taskById.get(focusedId)
      while (current?.parent_id) {
        next.delete(current.parent_id)
        current = taskById.get(current.parent_id)
      }
    }
    hiddenGroups.value = next
  }

  function clearFilterExceptions(): void {
    filterExceptions.value = new Set()
  }

  function revealFilterException(id: string): void {
    const taskById = new Map((workspace.value?.tasks ?? []).map(task => [task.id, task]))
    if (!taskById.has(id)) return
    filterExceptions.value = new Set([...filterExceptions.value, id])
    const next = new Set(hiddenGroups.value)
    let current = taskById.get(id)
    while (current?.parent_id) {
      next.delete(current.parent_id)
      current = taskById.get(current.parent_id)
    }
    hiddenGroups.value = next
  }

  function revealTask(id: string): void {
    const taskById = new Map((workspace.value?.tasks ?? []).map(task => [task.id, task]))
    const next = new Set(hiddenGroups.value)
    let current = taskById.get(id)
    while (current?.parent_id) {
      next.delete(current.parent_id)
      current = taskById.get(current.parent_id)
    }
    hiddenGroups.value = next
    clearTaskFilters()
  }

  function revealSearchResults(): void {
    const source = workspace.value?.tasks ?? []
    const taskById = new Map(source.map(task => [task.id, task]))
    const next = new Set(hiddenGroups.value)
    for (const task of source) {
      if (!activeTaskQuery.value.matches(task.title)) continue
      const visited = new Set<string>()
      let parentId = task.parent_id
      while (parentId && !visited.has(parentId)) {
        visited.add(parentId)
        next.delete(parentId)
        parentId = taskById.get(parentId)?.parent_id
      }
    }
    hiddenGroups.value = next
  }

  function setStatusFilters(statuses: readonly TaskStatus[]): void {
    statusFilters.value = workspaceTaskStatuses.filter(status => statuses.includes(status))
  }

  function toggleStatusFilter(status: TaskStatus): void {
    setStatusFilters(statusFilters.value.includes(status)
      ? statusFilters.value.filter(current => current !== status)
      : [...statusFilters.value, status])
  }

  function toggleUnblockedStatusFilters(): void {
    const allSelected = unblockedTaskStatuses.every(status => statusFilters.value.includes(status))
    setStatusFilters(allSelected
      ? statusFilters.value.filter(status => !unblockedTaskStatuses.includes(status))
      : [...statusFilters.value, ...unblockedTaskStatuses])
  }

  function toggleAssigneeFilter(assigneeId: string): void {
    assigneeFilters.value = assigneeFilters.value.includes(assigneeId)
      ? assigneeFilters.value.filter(current => current !== assigneeId)
      : [...assigneeFilters.value, assigneeId]
  }

  function updateTask(task: Task): void {
    if (!workspace.value) return
    workspace.value.tasks = workspace.value.tasks.map(current => current.id === task.id ? task : current)
  }

  function addDependency(dependency: Dependency): void {
    if (!workspace.value || workspace.value.dependencies.some(current => current.id === dependency.id)) return
    workspace.value.dependencies = [...workspace.value.dependencies, dependency]
  }

  function clearWorkspace(): void {
    workspace.value = null
    error.value = ''
    stale.value = false
    activeProjectStorage()?.removeItem(activeProjectStorageKey)
  }

  return { workspace, loading, refreshing, stale, error, search, searchError, statusFilters, assigneeFilters, periodStart, periodEnd, selected, zoom, hiddenGroups, filterExceptions, relationshipFocusTaskId, tasks, empty, load, clearWorkspace, clearTaskFilters, clearFilterExceptions, focusTaskRelations, revealFilterException, toggleSelect, toggleGroup, collapseAllGroups, expandAllGroups, expandIntermediateGroups, revealTask, setStatusFilters, toggleStatusFilter, toggleUnblockedStatusFilters, toggleAssigneeFilter, updateTask, addDependency }
})

function relationshipFocusIds(focusId: string, tasks: readonly Task[], dependencies: readonly Dependency[]): Set<string> {
  const knownIds = new Set(tasks.map(task => task.id))
  if (!knownIds.has(focusId)) return new Set()

  const related = new Map<string, string[]>()
  for (const dependency of dependencies) {
    if (!knownIds.has(dependency.from) || !knownIds.has(dependency.to)) continue
    related.set(dependency.from, [...(related.get(dependency.from) ?? []), dependency.to])
    related.set(dependency.to, [...(related.get(dependency.to) ?? []), dependency.from])
  }

  const visibleIds = new Set<string>()
  const pending = [focusId]
  while (pending.length) {
    const id = pending.pop()!
    if (visibleIds.has(id)) continue
    visibleIds.add(id)
    pending.push(...(related.get(id) ?? []))
  }
  return visibleIds
}
