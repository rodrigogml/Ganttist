import { computed, ref, watch } from 'vue'
import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import { parseWorkspaceResponse } from '../contracts/workspace-contract'
import type { Dependency, Task, Workspace } from '../types'
import { parseTaskQuery, type TaskQueryTarget } from '../utils/task-query'
import { preparedManifest, removeProjectOffline } from '../offline/offline-store'
import { apiFetch } from '../lib/api'

export type WorkspaceSortField = 'manual' | 'title' | 'start' | 'finish' | 'status' | 'priority'
export type WorkspaceGroupField = 'none' | 'status' | 'assignee' | 'priority'

const activeProjectStorageKey = 'ganttist.active-project-id'
const activeProjectStorage = () => typeof localStorage === 'undefined' ? null : localStorage
const normalizeQueryValue = (value: string): string => value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('pt-BR').replace(/\s+/g, ' ').trim()

export const useWorkspaceStore = defineStore('workspace', () => {
  const workspace = ref<Workspace | null>(null)
  const loading = ref(false)
  const refreshing = ref(false)
  const stale = ref(false)
  const error = ref('')
  const search = ref('')
  const initialTaskQuery = parseTaskQuery('')
  if (!initialTaskQuery.valid) throw new Error('A consulta vazia deve ser válida.')
  const activeTaskQuery = ref(initialTaskQuery)
  const searchError = ref('')
  const selected = ref<string[]>([])
  const zoom = ref<'day' | 'week' | 'month'>('week')
  const sortBy = ref<WorkspaceSortField>('manual')
  const sortDirection = ref<'asc' | 'desc'>('asc')
  const subsortBy = ref<WorkspaceSortField>('manual')
  const subsortDirection = ref<'asc' | 'desc'>('asc')
  const groupBy = ref<WorkspaceGroupField>('none')
  const subgroupBy = ref<WorkspaceGroupField>('none')
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
  watch(search, () => {
    if (filterExceptions.value.size) filterExceptions.value = new Set()
    if (relationshipFocusTaskId.value) relationshipFocusTaskId.value = null
  }, { flush: 'sync' })
  const currentAssigneeId = computed(() => {
    const userId = useAuthStore().user?.id
    return workspace.value?.people?.find(person => person.linkedUserId === userId)?.id ?? null
  })
  const queryWarnings = computed(() => {
    if (!activeTaskQuery.value.valid) return []
    const people = new Set((workspace.value?.people ?? []).map(person => normalizeQueryValue(person.name)))
    const sections = new Set((workspace.value?.tasks ?? []).filter(task => task.kind === 'section').map(task => normalizeQueryValue(task.title)))
    return activeTaskQuery.value.predicates.flatMap(predicate => {
      if (predicate.field === 'responsavel' && !['eu', 'sem', 'outros'].includes(predicate.value) && !people.has(predicate.value)) return [`Responsável “${predicate.label}” não encontrado neste projeto.`]
      if (predicate.field === 'secao' && predicate.value !== 'sem' && !sections.has(predicate.value)) return [`Seção “${predicate.label}” não encontrada neste projeto.`]
      return []
    })
  })
  const queryPredicates = computed(() => activeTaskQuery.value.valid ? activeTaskQuery.value.predicates : [])
  function taskQueryTarget(task: Task, source: readonly Task[]): TaskQueryTarget {
    const sectionId = task.section_id ?? (task.kind === 'task' ? task.parent_id : null)
    const section = sectionId ? source.find(candidate => candidate.id === sectionId)?.title ?? null : null
    return {
      title: task.title,
      status: task.status,
      assigneeId: task.assignee_id,
      assignee: task.assignee,
      start: task.start,
      finish: task.finish,
      priority: task.priority,
      section,
    }
  }
  const matchesTaskFilters = (task: Task, source: readonly Task[]): boolean => {
    if (!activeTaskQuery.value.matches(taskQueryTarget(task, source), { currentAssigneeId: currentAssigneeId.value })) return false
    return true
  }
  const tasks = computed(() => {
    const source = workspace.value?.tasks ?? []
    const byId = new Map(source.map(task => [task.id, task]))
    const visibleIds = relationshipFocusTaskId.value
      ? relationshipFocusIds(relationshipFocusTaskId.value, source, workspace.value?.dependencies ?? [])
      : new Set([
          ...source.filter(task => matchesTaskFilters(task, source)).map(task => task.id),
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

    const visible = source.filter(task => visibleIds.has(task.id))
    return sortVisibleHierarchy(visible, sortBy.value, sortDirection.value, subsortBy.value, subsortDirection.value, groupBy.value, subgroupBy.value)
  })
  const queryResultCount = computed(() => {
    const source = workspace.value?.tasks ?? []
    return source.filter(task => task.kind === 'task' && matchesTaskFilters(task, source)).length
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
      const response = await apiFetch(`/api/v1/projects/${projectId ?? workspace.value?.project.id}/workspace`)
      if (useAuthStore().handleUnauthorized(response)) return
      if (!response.ok) {
        if ([403, 404].includes(response.status)) {
          activeProjectStorage()?.removeItem(activeProjectStorageKey)
          const userId = useAuthStore().user?.id
          const deniedProjectId = projectId ?? workspace.value?.project.id
          if (userId && deniedProjectId) await removeProjectOffline(userId, deniedProjectId)
          workspace.value = null
          throw Object.assign(new Error('Você não possui mais acesso a este projeto.'), { accessRevoked: true })
        }
        throw new Error('Não foi possível carregar o projeto.')
      }
      workspace.value = parseWorkspaceResponse(await response.json())
      activeProjectStorage()?.setItem(activeProjectStorageKey, workspace.value.project.id)
      stale.value = false
    } catch (exception) {
      const revoked = Boolean((exception as Error & { accessRevoked?: boolean })?.accessRevoked)
      const userId = useAuthStore().user?.id
      const offlineProjectId = projectId ?? workspace.value?.project.id
      const cached = !revoked && userId && offlineProjectId ? await preparedManifest(userId, offlineProjectId) : null
      if (cached) { workspace.value = cached.workspace as Workspace; stale.value = true; error.value = '' }
      else {
        const message = exception instanceof Error ? exception.message : 'Erro inesperado'
        if (initialLoad) error.value = message
        else stale.value = true
      }
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
    filterExceptions.value = new Set()
    relationshipFocusTaskId.value = null
  }

  function focusTaskRelations(id: string): void {
    const source = workspace.value?.tasks ?? []
    const task = source.find(current => current.id === id)
    if (!task || task.kind !== 'task') return

    relationshipFocusTaskId.value = null
    search.value = ''
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
      if (!activeTaskQuery.value.matches(taskQueryTarget(task, source), { currentAssigneeId: currentAssigneeId.value })) continue
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

  return { workspace, loading, refreshing, stale, error, search, searchError, queryPredicates, queryWarnings, queryResultCount, selected, zoom, sortBy, sortDirection, subsortBy, subsortDirection, groupBy, subgroupBy, hiddenGroups, filterExceptions, relationshipFocusTaskId, tasks, empty, load, clearWorkspace, clearTaskFilters, clearFilterExceptions, focusTaskRelations, revealFilterException, toggleSelect, toggleGroup, collapseAllGroups, expandAllGroups, expandIntermediateGroups, revealTask, updateTask, addDependency }
})

function sortVisibleHierarchy(tasks: readonly Task[], field: WorkspaceSortField, direction: 'asc' | 'desc', subsort: WorkspaceSortField, subsortDirection: 'asc' | 'desc', group: WorkspaceGroupField, subgroup: WorkspaceGroupField): Task[] {
  if (field === 'manual' && group === 'none' && subgroup === 'none') return [...tasks]
  const children = new Map<string | null, Task[]>()
  const known = new Set(tasks.map(task => task.id))
  for (const task of tasks) {
    const parent = task.parent_id && known.has(task.parent_id) ? task.parent_id : null
    children.set(parent, [...(children.get(parent) ?? []), task])
  }
  const value = (task: Task): string | number => field === 'title' ? task.title : field === 'start' ? task.start ?? '' : field === 'finish' ? task.finish ?? '' : field === 'status' ? task.status : field === 'priority' ? task.priority ?? 0 : ''
  const groupValue = (task: Task, grouping: WorkspaceGroupField): string | number => grouping === 'status' ? task.status : grouping === 'assignee' ? task.assignee ?? 'Sem responsável' : grouping === 'priority' ? task.priority ?? 0 : ''
  const compareValue = (a: string | number, b: string | number) => typeof a === 'number' && typeof b === 'number' ? a - b : String(a).localeCompare(String(b), 'pt-BR')
  const compare = (left: Task, right: Task) => {
    if (group !== 'none') { const grouped = compareValue(groupValue(left, group), groupValue(right, group)); if (grouped) return grouped }
    if (subgroup !== 'none' && subgroup !== group) { const grouped = compareValue(groupValue(left, subgroup), groupValue(right, subgroup)); if (grouped) return grouped }
    if (field !== 'manual') {
      const a = value(left), b = value(right)
      const order = compareValue(a, b)
      if (order) return direction === 'asc' ? order : -order
    }
    if (subsort === 'manual') return 0
    const subValue = (task: Task): string | number => subsort === 'title' ? task.title : subsort === 'start' ? task.start ?? '' : subsort === 'finish' ? task.finish ?? '' : subsort === 'status' ? task.status : task.priority ?? 0
    const subOrder = compareValue(subValue(left), subValue(right))
    return subsortDirection === 'asc' ? subOrder : -subOrder
  }
  const ordered: Task[] = []
  const visit = (parent: string | null): void => {
    for (const task of [...(children.get(parent) ?? [])].sort(compare)) { ordered.push(task); visit(task.id) }
  }
  visit(null)
  return ordered
}

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
