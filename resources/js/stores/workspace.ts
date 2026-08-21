import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import { parseWorkspaceResponse } from '../contracts/workspace-contract'
import type { Task, TaskStatus, Workspace } from '../types'

export type WorkspaceStatusFilter = 'all' | 'unblocked' | TaskStatus

const unblockedStatuses = new Set<TaskStatus>(['opened', 'scheduled', 'late'])

export const useWorkspaceStore = defineStore('workspace', () => {
  const workspace = ref<Workspace | null>(null)
  const loading = ref(true)
  const refreshing = ref(false)
  const stale = ref(false)
  const error = ref('')
  const search = ref('')
  const statusFilter = ref<WorkspaceStatusFilter>('all')
  const selected = ref<string[]>([])
  const zoom = ref<'day' | 'week' | 'month'>('week')
  const hiddenGroups = ref(new Set<string>())
  const tasks = computed(() => (workspace.value?.tasks ?? []).filter(task => {
    if (search.value && !task.title.toLocaleLowerCase('pt-BR').includes(search.value.toLocaleLowerCase('pt-BR'))) return false
    if (task.kind === 'task' && statusFilter.value === 'unblocked' && !unblockedStatuses.has(task.status)) return false
    if (task.kind === 'task' && statusFilter.value !== 'all' && statusFilter.value !== 'unblocked' && task.status !== statusFilter.value) return false
    return true
  }))
  const empty = computed(() => workspace.value !== null && workspace.value.tasks.length === 0)
  let activeLoad: Promise<void> | null = null

  function load(): Promise<void> {
    if (activeLoad) return activeLoad
    activeLoad = performLoad().finally(() => { activeLoad = null })

    return activeLoad
  }

  async function performLoad(): Promise<void> {
    const initialLoad = workspace.value === null
    if (initialLoad) loading.value = true
    else refreshing.value = true
    error.value = ''
    try {
      const response = await fetch('/api/v1/workspace')
      if (useAuthStore().handleUnauthorized(response)) return
      if (!response.ok) throw new Error('Não foi possível carregar o projeto.')
      workspace.value = parseWorkspaceResponse(await response.json())
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

  function revealTask(id: string): void {
    const taskById = new Map((workspace.value?.tasks ?? []).map(task => [task.id, task]))
    const next = new Set(hiddenGroups.value)
    let current = taskById.get(id)
    while (current?.parent_id) {
      next.delete(current.parent_id)
      current = taskById.get(current.parent_id)
    }
    hiddenGroups.value = next
    search.value = ''
    statusFilter.value = 'all'
  }

  function updateTask(task: Task): void {
    if (!workspace.value) return
    workspace.value.tasks = workspace.value.tasks.map(current => current.id === task.id ? task : current)
  }

  return { workspace, loading, refreshing, stale, error, search, statusFilter, selected, zoom, hiddenGroups, tasks, empty, load, toggleSelect, toggleGroup, revealTask, updateTask }
})
