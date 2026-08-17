import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { Task, Workspace } from '../types'

export const useWorkspaceStore = defineStore('workspace', () => {
  const workspace = ref<Workspace|null>(null), loading = ref(true), error = ref('')
  const search = ref(''), statusFilter = ref('all'), selected = ref<string[]>([]), zoom = ref<'day'|'week'|'month'>('week')
  const hiddenGroups = ref(new Set<string>())
  const tasks = computed(() => (workspace.value?.tasks ?? []).filter(t => {
    if (search.value && !t.title.toLocaleLowerCase('pt-BR').includes(search.value.toLocaleLowerCase('pt-BR'))) return false
    if (statusFilter.value !== 'all' && t.status !== statusFilter.value) return false
    return true
  }))
  async function load() { const initialLoad=workspace.value===null; if(initialLoad)loading.value=true; error.value=''; try { const r=await fetch('/api/v1/workspace'); if(!r.ok) throw new Error('Não foi possível carregar o projeto.'); workspace.value=(await r.json()).data } catch(e) { error.value=e instanceof Error?e.message:'Erro inesperado' } finally { if(initialLoad)loading.value=false } }
  function toggleSelect(id:string, additive=false) { if(!additive) selected.value=[]; selected.value=selected.value.includes(id)?selected.value.filter(v=>v!==id):[...selected.value,id] }
  function toggleGroup(id:string) { const next=new Set(hiddenGroups.value); next.has(id)?next.delete(id):next.add(id); hiddenGroups.value=next }
  function updateTask(task:Task) { if(!workspace.value)return; workspace.value.tasks=workspace.value.tasks.map(t=>t.id===task.id?task:t) }
  return { workspace,loading,error,search,statusFilter,selected,zoom,hiddenGroups,tasks,load,toggleSelect,toggleGroup,updateTask }
})
