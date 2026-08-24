<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue'

type Exception = { date: string; type: 'NON_WORKING' | 'WORKING'; description: string | null }
type Calendar = { version: number; workingDays: string[]; reschedulingMode: 'MANUAL' | 'AUTOMATIC'; projectionPolicy: 'PRESERVE_DURATION' | 'PRESERVE_DEADLINE'; deadlinePolicy: 'ANTERIOR' | 'POSTERIOR'; allowUnscheduledTasks: boolean; exceptions: Exception[] }
type Impact = { task_id: string; before: { start: string | null; finish: string | null }; after: { start: string | null; finish: string | null } }
type AutomationSettings = { version: number; autoScheduleBlockedTasks: boolean; clearParentTaskDates: boolean }
type AutomationHelp = 'blocked' | 'parents'
type Notification = { kind: 'success' | 'error'; message: string }
type SettingsTab = 'calendar' | 'automation'
type PendingNavigation = { kind: 'close' } | { kind: 'tab'; target: SettingsTab }

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: []; saved: []; notify: [notification: Notification] }>()
const days = [{ id: 'monday', label: 'Seg' }, { id: 'tuesday', label: 'Ter' }, { id: 'wednesday', label: 'Qua' }, { id: 'thursday', label: 'Qui' }, { id: 'friday', label: 'Sex' }, { id: 'saturday', label: 'Sáb' }, { id: 'sunday', label: 'Dom' }]
const calendar = reactive<Calendar>({ version: 1, workingDays: [], reschedulingMode: 'MANUAL', projectionPolicy: 'PRESERVE_DURATION', deadlinePolicy: 'ANTERIOR', allowUnscheduledTasks: true, exceptions: [] })
const baseline = ref<Calendar | null>(null)
const automation = reactive<AutomationSettings>({ version: 1, autoScheduleBlockedTasks: false, clearParentTaskDates: false })
const automationBaseline = ref<AutomationSettings | null>(null)
const activeTab = ref<SettingsTab>('calendar')
const loading = ref(false), saving = ref(false), simulating = ref(false), error = ref(''), impacts = ref<Impact[] | null>(null)
const automationLoading = ref(false), automationSaving = ref(false), automationError = ref(''), automationHelp = ref<AutomationHelp | null>(null)
const dialog = ref<HTMLElement | null>(null), closeButton = ref<HTMLButtonElement | null>(null), discardButton = ref<HTMLButtonElement | null>(null)
const blockedHelpButton = ref<HTMLButtonElement | null>(null), parentDatesHelpButton = ref<HTMLButtonElement | null>(null)
const pendingNavigation = ref<PendingNavigation | null>(null)
let returnFocus: HTMLElement | null = null

const cloneCalendar = (value: Calendar): Calendar => JSON.parse(JSON.stringify(value)) as Calendar
const editableSnapshot = (value: Calendar): string => JSON.stringify({ workingDays: value.workingDays, reschedulingMode: value.reschedulingMode, projectionPolicy: value.projectionPolicy, deadlinePolicy: value.deadlinePolicy, allowUnscheduledTasks: value.allowUnscheduledTasks, exceptions: value.exceptions })
const calendarDirty = computed(() => baseline.value !== null && editableSnapshot(calendar) !== editableSnapshot(baseline.value))
const automationDirty = computed(() => automationBaseline.value !== null && (automation.autoScheduleBlockedTasks !== automationBaseline.value.autoScheduleBlockedTasks || automation.clearParentTaskDates !== automationBaseline.value.clearParentTaskDates))
const currentTabDirty = computed(() => activeTab.value === 'calendar' ? calendarDirty.value : automationDirty.value)

function headers(): HeadersInit { const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content; return { 'Content-Type': 'application/json', Accept: 'application/json', ...(token ? { 'X-CSRF-TOKEN': token } : {}) } }
function replace(data: Calendar, remember = true): void { calendar.version = data.version; calendar.workingDays = [...data.workingDays]; calendar.reschedulingMode = data.reschedulingMode; calendar.projectionPolicy = data.projectionPolicy; calendar.deadlinePolicy = data.deadlinePolicy; calendar.allowUnscheduledTasks = data.allowUnscheduledTasks; calendar.exceptions = data.exceptions.map(item => ({ ...item })); impacts.value = null; if (remember) baseline.value = cloneCalendar(calendar) }
async function load(): Promise<void> { loading.value = true; error.value = ''; baseline.value = null; try { const response = await fetch('/api/v1/calendar', { headers: headers() }); if (!response.ok) throw new Error('Não foi possível carregar o calendário.'); replace((await response.json()).data) } catch (exception) { error.value = exception instanceof Error ? exception.message : 'Não foi possível carregar o calendário.' } finally { loading.value = false } }
function addException(): void { calendar.exceptions.push({ date: '', type: 'NON_WORKING', description: null }) }
function removeException(index: number): void { calendar.exceptions.splice(index, 1) }
function payload(confirmed = false): object { return { commandId: crypto.randomUUID(), expectedVersion: calendar.version, workingDays: calendar.workingDays, reschedulingMode: calendar.reschedulingMode, projectionPolicy: calendar.projectionPolicy, deadlinePolicy: calendar.deadlinePolicy, allowUnscheduledTasks: calendar.allowUnscheduledTasks, exceptions: calendar.exceptions, confirmed } }
async function simulate(): Promise<void> { if (calendar.workingDays.length === 0) { error.value = 'Selecione pelo menos um dia útil.'; return }; simulating.value = true; error.value = ''; try { const response = await fetch('/api/v1/calendar/simulate', { method: 'POST', headers: headers(), body: JSON.stringify(payload()) }); if (!response.ok) throw new Error('Não foi possível calcular o impacto do calendário.'); impacts.value = (await response.json()).data.changes } catch (exception) { error.value = exception instanceof Error ? exception.message : 'Não foi possível calcular o impacto.' } finally { simulating.value = false } }
async function save(): Promise<void> { if (calendar.reschedulingMode === 'MANUAL' && impacts.value === null) { await simulate(); return }; saving.value = true; error.value = ''; try { const response = await fetch('/api/v1/calendar', { method: 'PUT', headers: headers(), body: JSON.stringify(payload(calendar.reschedulingMode !== 'MANUAL' || impacts.value !== null)) }); if (!response.ok) throw new Error(response.status === 409 ? 'O calendário mudou em outro contexto. Recarregue antes de salvar.' : 'Não foi possível salvar o calendário.'); replace((await response.json()).data); emit('saved') } catch (exception) { error.value = exception instanceof Error ? exception.message : 'Não foi possível salvar o calendário.' } finally { saving.value = false } }
function replaceAutomation(data: AutomationSettings): void { automation.version = data.version; automation.autoScheduleBlockedTasks = data.autoScheduleBlockedTasks; automation.clearParentTaskDates = data.clearParentTaskDates; automationBaseline.value = { ...automation }; automationHelp.value = null }
async function loadAutomation(): Promise<void> { automationLoading.value = true; automationError.value = ''; automationBaseline.value = null; try { const response = await fetch('/api/v1/settings/automation', { headers: headers() }); if (!response.ok) throw new Error('Não foi possível carregar as configurações de automação.'); replaceAutomation((await response.json()).data) } catch (exception) { automationError.value = exception instanceof Error ? exception.message : 'Não foi possível carregar as configurações de automação.' } finally { automationLoading.value = false } }
async function saveAutomation(): Promise<void> { automationSaving.value = true; automationError.value = ''; try { const response = await fetch('/api/v1/settings/automation', { method: 'PUT', headers: headers(), body: JSON.stringify({ commandId: crypto.randomUUID(), expectedVersion: automation.version, autoScheduleBlockedTasks: automation.autoScheduleBlockedTasks, clearParentTaskDates: automation.clearParentTaskDates }) }); if (!response.ok) { const payload = await response.json().catch(() => null); throw new Error(response.status === 409 ? 'As configurações de automação mudaram em outro contexto. Recarregue antes de salvar.' : payload?.message ?? 'Não foi possível salvar as configurações de automação.') }; replaceAutomation((await response.json()).data); emit('saved'); emit('notify', { kind: 'success', message: 'Configurações de automação salvas.' }) } catch (exception) { automationError.value = exception instanceof Error ? exception.message : 'Não foi possível salvar as configurações de automação.'; emit('notify', { kind: 'error', message: automationError.value }) } finally { automationSaving.value = false } }

function requestClose(): void { if (currentTabDirty.value) { pendingNavigation.value = { kind: 'close' }; void nextTick(() => discardButton.value?.focus()); return } emit('close') }
function requestTab(target: SettingsTab): void { if (target === activeTab.value) return; if (currentTabDirty.value) { pendingNavigation.value = { kind: 'tab', target }; void nextTick(() => discardButton.value?.focus()); return } automationHelp.value = null; activeTab.value = target; void nextTick(focusActiveTab) }
function continueEditing(): void { pendingNavigation.value = null; void nextTick(() => closeButton.value?.focus()) }
function discardChanges(): void {
  if (activeTab.value === 'calendar' && baseline.value) replace(baseline.value)
  if (activeTab.value === 'automation' && automationBaseline.value) replaceAutomation(automationBaseline.value)
  const navigation = pendingNavigation.value
  pendingNavigation.value = null
  if (navigation?.kind === 'tab') { activeTab.value = navigation.target; void nextTick(focusActiveTab) }
  else if (navigation?.kind === 'close') emit('close')
}
function focusActiveTab(): void { dialog.value?.querySelector<HTMLElement>('[role="tab"][aria-selected="true"]')?.focus() }
function toggleAutomationHelp(help: AutomationHelp): void { automationHelp.value = automationHelp.value === help ? null : help }
function closeAutomationHelp(): void { const active = automationHelp.value; automationHelp.value = null; void nextTick(() => (active === 'blocked' ? blockedHelpButton.value : parentDatesHelpButton.value)?.focus()) }
function handleAutomationHelpPointerDown(event: PointerEvent): void { if (automationHelp.value === null) return; if (event.target instanceof Element && event.target.closest('.automation-help-wrap')) return; automationHelp.value = null }
function handleTabKey(event: KeyboardEvent): void {
  const focusRoot = pendingNavigation.value ? dialog.value?.querySelector<HTMLElement>('.settings-unsaved-confirm') : dialog.value
  const focusable = [...(focusRoot?.querySelectorAll<HTMLElement>('button:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])') ?? [])].filter(element => element.offsetParent !== null || element === document.activeElement)
  if (!focusable.length) return
  const first = focusable[0], last = focusable.at(-1)!
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
}
function handleDialogKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') { event.preventDefault(); if (automationHelp.value !== null) closeAutomationHelp(); else pendingNavigation.value ? continueEditing() : requestClose() }
  else if (event.key === 'Tab') handleTabKey(event)
}
function handleTabArrows(event: KeyboardEvent): void {
  if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return
  event.preventDefault()
  requestTab(activeTab.value === 'calendar' ? 'automation' : 'calendar')
}

watch(() => props.open, async open => {
  if (open) { returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null; activeTab.value = 'calendar'; pendingNavigation.value = null; await Promise.all([load(), loadAutomation()]); await nextTick(); closeButton.value?.focus() }
  else { if (baseline.value) replace(baseline.value); if (automationBaseline.value) replaceAutomation(automationBaseline.value); pendingNavigation.value = null; returnFocus?.focus(); returnFocus = null }
})
watch(() => editableSnapshot(calendar), () => { impacts.value = null })
</script>

<template>
  <div v-if="open" class="settings-modal-scrim" @keydown="handleDialogKeydown" @pointerdown="handleAutomationHelpPointerDown">
      <section ref="dialog" class="settings-modal" role="dialog" aria-modal="true" aria-labelledby="settings-title">
        <header class="settings-modal-header"><div><span class="eyebrow">CONFIGURAÇÕES</span><h2 id="settings-title">Configurações do projeto</h2></div><button ref="closeButton" type="button" aria-label="Fechar configurações" @click="requestClose">×</button></header>
        <nav class="settings-tabs" role="tablist" aria-label="Categorias de configuração" @keydown="handleTabArrows">
          <button id="settings-tab-calendar" type="button" role="tab" :aria-selected="activeTab==='calendar'" aria-controls="settings-panel-calendar" :tabindex="activeTab==='calendar'?0:-1" @click="requestTab('calendar')">Calendário</button>
          <button id="settings-tab-automation" type="button" role="tab" :aria-selected="activeTab==='automation'" aria-controls="settings-panel-automation" :tabindex="activeTab==='automation'?0:-1" @click="requestTab('automation')">Automação</button>
        </nav>
        <div v-show="activeTab==='calendar'" id="settings-panel-calendar" class="settings-tab-panel calendar-settings" role="tabpanel" aria-labelledby="settings-tab-calendar">
          <section v-if="loading" class="account-muted settings-loading">Carregando calendário…</section>
          <section v-else class="calendar-settings-body">
            <p>Defina os dias úteis e exceções que o planejamento deve respeitar.</p>
            <fieldset><legend>Semana útil</legend><label v-for="day in days" :key="day.id"><input v-model="calendar.workingDays" type="checkbox" :value="day.id"> {{ day.label }}</label></fieldset>
            <label>Modo de reagendamento<select v-model="calendar.reschedulingMode"><option value="MANUAL">Manual: confirmar impactos</option><option value="AUTOMATIC">Automático</option></select></label>
            <label>Projeção após desbloqueio<select v-model="calendar.projectionPolicy"><option value="PRESERVE_DURATION">Preservar duração prevista</option><option value="PRESERVE_DEADLINE">Preservar prazo de entrega</option></select><small>Afeta somente a exibição calculada; não altera datas no Todoist.</small></label>
            <label>Deadline em dia bloqueado<select v-model="calendar.deadlinePolicy"><option value="ANTERIOR">Dia útil anterior</option><option value="POSTERIOR">Próximo dia útil</option></select></label>
            <label class="calendar-toggle"><input v-model="calendar.allowUnscheduledTasks" type="checkbox"> Permitir tarefas sem data</label>
            <div class="calendar-exceptions"><b>Exceções</b><button type="button" class="soft-btn" @click="addException">Adicionar</button><p v-if="!calendar.exceptions.length" class="account-muted">Sem exceções cadastradas.</p><div v-for="(exception,index) in calendar.exceptions" :key="index" class="exception-row"><input v-model="exception.date" type="date" aria-label="Data da exceção"><select v-model="exception.type" aria-label="Tipo da exceção"><option value="NON_WORKING">Não útil</option><option value="WORKING">Útil</option></select><button type="button" aria-label="Remover exceção" @click="removeException(index)">×</button></div></div>
            <section v-if="impacts!==null" class="calendar-impact" role="status"><b>{{impacts.length?'Impacto previsto':'Sem tarefas afetadas'}}</b><ul v-if="impacts.length"><li v-for="impact in impacts" :key="impact.task_id">{{impact.task_id}}: {{impact.before.start}}–{{impact.before.finish}} → {{impact.after.start}}–{{impact.after.finish}}</li></ul><button type="button" class="soft-btn" @click="impacts=null">Descartar prévia</button></section>
            <p v-if="error" class="account-error" role="alert">{{ error }}</p>
          </section>
          <footer class="settings-tab-actions"><button type="button" class="soft-btn" @click="requestClose">Cancelar</button><button type="button" class="primary" :disabled="saving||simulating||loading" @click="save">{{saving?'Salvando…':simulating?'Calculando…':calendar.reschedulingMode==='MANUAL'&&impacts===null?'Simular impacto':'Confirmar calendário'}}</button></footer>
        </div>
        <div v-show="activeTab==='automation'" id="settings-panel-automation" class="settings-tab-panel automation-settings" role="tabpanel" aria-labelledby="settings-tab-automation">
          <section v-if="automationLoading" class="account-muted settings-loading">Carregando automações…</section>
          <section v-else class="automation-settings-body">
            <div class="automation-options">
              <article class="automation-option">
                <input id="auto-schedule-blocked" v-model="automation.autoScheduleBlockedTasks" type="checkbox" :disabled="automationBaseline===null">
                <label for="auto-schedule-blocked">Definir automaticamente o início de tarefas bloqueadas na data prevista de desbloqueio</label>
                <div class="automation-help-wrap"><button ref="blockedHelpButton" type="button" class="help-button" aria-label="Entender a atualização automática de tarefas bloqueadas" aria-controls="blocked-task-automation-help" :aria-expanded="automationHelp==='blocked'" @click="toggleAutomationHelp('blocked')">?</button>
                  <aside v-if="automationHelp==='blocked'" id="blocked-task-automation-help" class="automation-help-popover" role="dialog" aria-labelledby="blocked-task-help-title"><header><b id="blocked-task-help-title">Como essa automação funciona?</b><button type="button" aria-label="Fechar ajuda" @click="closeAutomationHelp">×</button></header><p>Quando uma tarefa estiver bloqueada por uma predecessora do tipo FS, o Ganttist gravará no Todoist a data em que ela realmente poderá começar.</p><div class="automation-example"><b>Exemplo</b><span>Se a predecessora termina em 10/09 e 11/09 é um dia útil, uma tarefa bloqueada passa a iniciar em 11/09.</span></div><small>O deadline existente é preservado. Se ele ficar anterior ao novo início, será ajustado para o mesmo dia para manter um intervalo válido.</small></aside>
                </div>
              </article>
              <article class="automation-option">
                <input id="clear-parent-task-dates" v-model="automation.clearParentTaskDates" type="checkbox" :disabled="automationBaseline===null">
                <label for="clear-parent-task-dates">Manter sem datas no Todoist as tarefas que possuem subtarefas</label>
                <div class="automation-help-wrap"><button ref="parentDatesHelpButton" type="button" class="help-button" aria-label="Entender a limpeza de datas de tarefas com subtarefas" aria-controls="parent-task-dates-automation-help" :aria-expanded="automationHelp==='parents'" @click="toggleAutomationHelp('parents')">?</button>
                  <aside v-if="automationHelp==='parents'" id="parent-task-dates-automation-help" class="automation-help-popover" role="dialog" aria-labelledby="parent-task-dates-help-title"><header><b id="parent-task-dates-help-title">Como essa automação funciona?</b><button type="button" aria-label="Fechar ajuda" @click="closeAutomationHelp">×</button></header><p>O Ganttist removerá do Todoist a data e o deadline de toda tarefa que possuir subtarefas. Ele nunca preencherá esses campos automaticamente.</p><div class="automation-example"><b>Exemplo</b><span>Se “Lançamento” agrupa tarefas de 10/09 a 15/09, ela fica sem datas no Todoist, mas seu colchete continua cobrindo esse período no Gantt.</span></div><small>O intervalo do agrupador é sempre calculado a partir das tarefas filhas e permanece apenas na visualização do Ganttist.</small></aside>
                </div>
              </article>
            </div>
            <p v-if="automationError" class="account-error" role="alert">{{ automationError }}</p>
          </section>
          <footer class="settings-tab-actions"><button type="button" class="soft-btn" @click="requestClose">Cancelar</button><button type="button" class="primary" :disabled="automationSaving||automationLoading||!automationDirty" @click="saveAutomation">{{automationSaving?'Salvando…':'Salvar automação'}}</button></footer>
        </div>
        <section v-if="pendingNavigation" class="settings-unsaved-confirm" role="alertdialog" aria-modal="true" aria-labelledby="settings-unsaved-title" aria-describedby="settings-unsaved-description"><div><b id="settings-unsaved-title">Alterações não salvas</b><p id="settings-unsaved-description">As alterações desta aba ainda não foram salvas. Deseja descartá-las?</p><footer><button ref="discardButton" type="button" class="danger-btn" @click="discardChanges">Descartar alterações</button><button type="button" class="primary" @click="continueEditing">Voltar</button></footer></div></section>
      </section>
  </div>
</template>
