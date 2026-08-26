<script setup lang="ts">
import { onMounted, ref } from 'vue'

const props = defineProps<{ projectId: string; role: string }>()
const emit = defineEmits<{ close: []; deleted: []; peopleChanged: [] }>()
type Member = { id: string; user_id: string; name: string; email: string; role: 'owner' | 'editor' | 'reader' }
type Invitation = { id: string; email: string; role: 'editor' | 'reader'; status: string; expires_at: string | null }
type Person = { id: string; name: string; email: string | null }

const members = ref<Member[]>([])
const invitations = ref<Invitation[]>([])
const people = ref<Person[]>([])
const loading = ref(true)
const error = ref('')
const email = ref('')
const invitationRole = ref<'editor' | 'reader'>('editor')
const personName = ref('')
const personEmail = ref('')
const editingPersonId = ref<string | null>(null)
const sending = ref(false)
const savingPerson = ref(false)
const deleting = ref(false)

const canEditPeople = () => ['owner', 'editor'].includes(props.role)
const csrfHeaders = (): Record<string, string> => {
  const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
  return token ? { 'X-CSRF-TOKEN': token } : {}
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await fetch(`/api/v1/projects/${props.projectId}/members`, { headers: { Accept: 'application/json' } })
    if (!response.ok) throw new Error('Não foi possível carregar a equipe.')
    const data = (await response.json()).data
    members.value = data.members
    invitations.value = data.invitations
    people.value = data.people
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Erro inesperado'
  } finally {
    loading.value = false
  }
}

async function invite() {
  if (!email.value.trim() || sending.value || props.role !== 'owner') return
  sending.value = true
  error.value = ''
  try {
    const response = await fetch(`/api/v1/projects/${props.projectId}/invitations`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() }, body: JSON.stringify({ email: email.value.trim(), role: invitationRole.value }) })
    if (!response.ok) throw new Error('Não foi possível enviar o convite.')
    email.value = ''
    await load()
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Erro inesperado'
  } finally {
    sending.value = false
  }
}

async function savePerson() {
  if (!personName.value.trim() || savingPerson.value || !canEditPeople()) return
  savingPerson.value = true
  error.value = ''
  try {
    const method = editingPersonId.value ? 'PUT' : 'POST'
    const suffix = editingPersonId.value ? `/${editingPersonId.value}` : ''
    const response = await fetch(`/api/v1/projects/${props.projectId}/people${suffix}`, { method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() }, body: JSON.stringify({ name: personName.value.trim(), email: personEmail.value.trim() || null }) })
    if (!response.ok) throw new Error('Não foi possível salvar a pessoa.')
    personName.value = ''
    personEmail.value = ''
    editingPersonId.value = null
    await load()
    emit('peopleChanged')
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Erro inesperado'
  } finally {
    savingPerson.value = false
  }
}

function editPerson(person: Person) {
  editingPersonId.value = person.id
  personName.value = person.name
  personEmail.value = person.email ?? ''
}

function cancelPersonEdit() {
  editingPersonId.value = null
  personName.value = ''
  personEmail.value = ''
}

async function removePerson(person: Person) {
  if (!confirm(`Excluir ${person.name}? As tarefas dela ficarão sem responsável.`)) return
  const response = await fetch(`/api/v1/projects/${props.projectId}/people/${person.id}`, { method: 'DELETE', headers: { Accept: 'application/json', ...csrfHeaders() } })
  if (!response.ok) { error.value = 'Não foi possível excluir a pessoa.'; return }
  await load()
  emit('peopleChanged')
}

async function changeRole(member: Member) {
  if (member.role === 'owner') return
  const response = await fetch(`/api/v1/projects/${props.projectId}/members/${member.id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() }, body: JSON.stringify({ role: member.role }) })
  if (!response.ok) error.value = 'Não foi possível atualizar o acesso.'
}

async function removeMember(member: Member) {
  if (!confirm(`Remover ${member.name} do projeto?`)) return
  const response = await fetch(`/api/v1/projects/${props.projectId}/members/${member.id}`, { method: 'DELETE', headers: { Accept: 'application/json', ...csrfHeaders() } })
  if (!response.ok) { error.value = 'Não foi possível remover o membro.'; return }
  await load()
}

async function deleteProject() {
  if (!confirm('Excluir este projeto e todo o seu conteúdo? Esta ação não pode ser desfeita.')) return
  deleting.value = true
  try {
    const response = await fetch(`/api/v1/projects/${props.projectId}`, { method: 'DELETE', headers: { Accept: 'application/json', ...csrfHeaders() } })
    if (!response.ok) throw new Error('Não foi possível excluir o projeto.')
    emit('deleted')
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Erro inesperado'
  } finally {
    deleting.value = false
  }
}

onMounted(load)
</script>

<template>
  <Teleport to="body">
    <div class="panel-backdrop" @click.self="emit('close')">
      <section class="calendar-panel" role="dialog" aria-modal="true" aria-label="Pessoas e acesso do projeto">
        <header><div><p class="eyebrow">PESSOAS E ACESSO</p><h2>Equipe do projeto</h2></div><button class="drawer-close" aria-label="Fechar" @click="emit('close')">×</button></header>
        <p v-if="error" class="workspace-state">{{ error }}</p>
        <p v-if="loading" class="loading">Carregando equipe…</p>
        <template v-else>
          <section class="calendar-section">
            <h3>Responsáveis</h3>
            <form v-if="canEditPeople()" class="create-task" @submit.prevent="savePerson"><input v-model="personName" placeholder="Nome" aria-label="Nome da pessoa"><input v-model="personEmail" type="email" placeholder="E-mail (opcional)" aria-label="E-mail da pessoa"><button class="primary" :disabled="savingPerson || !personName.trim()">{{ savingPerson ? 'Salvando…' : editingPersonId ? 'Salvar pessoa' : 'Adicionar pessoa' }}</button><button v-if="editingPersonId" class="soft-btn" type="button" @click="cancelPersonEdit">Cancelar</button></form>
            <p v-if="!people.length" class="dependency-empty">Cadastre responsáveis mesmo que não usem o Ganttist.</p>
            <div v-for="person in people" :key="person.id" class="task-comment"><b>{{ person.name }}</b><small>{{ person.email || 'Sem e-mail' }}</small><button v-if="canEditPeople()" class="soft-btn" @click="editPerson(person)">Editar</button><button v-if="canEditPeople()" class="danger-btn" @click="removePerson(person)">Excluir</button></div>
          </section>
          <section v-if="role === 'owner'" class="calendar-section"><h3>Convidar para acesso</h3><form class="create-task" @submit.prevent="invite"><input v-model="email" type="email" placeholder="E-mail para convite" aria-label="E-mail para convite"><select v-model="invitationRole" aria-label="Acesso"><option value="editor">Pode alterar</option><option value="reader">Somente leitura</option></select><button class="primary" :disabled="sending || !email.trim()">{{ sending ? 'Enviando…' : 'Convidar' }}</button></form></section>
          <section class="calendar-section"><h3>Membros</h3><p v-if="!members.length" class="dependency-empty">Nenhum membro.</p><div v-for="member in members" :key="member.id" class="task-comment"><b>{{ member.name }}</b><small>{{ member.email }}</small><select v-if="role === 'owner' && member.role !== 'owner'" v-model="member.role" @change="changeRole(member)"><option value="editor">Pode alterar</option><option value="reader">Somente leitura</option></select><small v-else>{{ member.role === 'owner' ? 'Proprietário' : member.role === 'editor' ? 'Pode alterar' : 'Somente leitura' }}</small><button v-if="role === 'owner' && member.role !== 'owner'" class="danger-btn" @click="removeMember(member)">Remover</button></div></section>
          <section class="calendar-section"><h3>Convites pendentes</h3><p v-if="!invitations.length" class="dependency-empty">Nenhum convite pendente.</p><div v-for="invitation in invitations" :key="invitation.id" class="task-comment"><b>{{ invitation.email }}</b><small>{{ invitation.role === 'editor' ? 'Pode alterar' : 'Somente leitura' }} · aguardando aceite</small></div></section>
          <footer v-if="role === 'owner'"><button class="danger-btn" :disabled="deleting" @click="deleteProject">{{ deleting ? 'Excluindo…' : 'Excluir projeto' }}</button></footer>
        </template>
      </section>
    </div>
  </Teleport>
</template>
