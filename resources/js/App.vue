<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import AuthGate from './AuthGate.vue'
import TodoistSetup from './TodoistSetup.vue'
import { useAuthStore } from './stores/auth'
import { useWorkspaceStore } from './stores/workspace'
import type { Task } from './types'
import { barWidth, civilDayOffset } from './utils/timeline'
const store=useWorkspaceStore(); const auth=useAuthStore(); const needsTodoist=ref(false); const appearance=ref(false), textScale=ref<'compact'|'comfortable'|'large'>('comfortable'), spacing=ref<'compact'|'comfortable'|'spacious'>('comfortable'); let eventSource:EventSource|null=null; let eventReconnect:ReturnType<typeof setTimeout>|null=null
const csrfHeaders=()=>{const token=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;return token?{'X-CSRF-TOKEN':token}:{}}
function connectEvents(){eventSource?.close();eventSource=new EventSource('/api/v1/events');eventSource.addEventListener('workspace.updated',()=>store.load());eventSource.onerror=()=>{eventSource?.close();eventReconnect=setTimeout(connectEvents,5000)}}
let initializedUserId:string|null=null
async function initializeWorkspace(){if(!auth.user||initializedUserId===auth.user.id)return;initializedUserId=auth.user.id;try{const response=await fetch('/api/v1/todoist/status',{headers:{Accept:'application/json'}});if(!response.ok)throw new Error('Não foi possível verificar a conexão com o Todoist.');const status=await response.json();needsTodoist.value=!status.connected||!status.project;if(!needsTodoist.value)await store.load();connectEvents()}catch(error){needsTodoist.value=true;toast.value=error instanceof Error?error.message:'Não foi possível carregar sua configuração.'}}
watch(()=>auth.user?.id,()=>{if(auth.user)initializeWorkspace();else{initializedUserId=null;eventSource?.close()}})
onMounted(async()=>{const savedText=localStorage.getItem('ganttist.text-scale'),savedSpacing=localStorage.getItem('ganttist.spacing');if(savedText==='compact'||savedText==='comfortable'||savedText==='large')textScale.value=savedText;if(savedSpacing==='compact'||savedSpacing==='comfortable'||savedSpacing==='spacious')spacing.value=savedSpacing;await auth.bootstrap();await initializeWorkspace()})
onUnmounted(()=>{eventSource?.close();if(eventReconnect)clearTimeout(eventReconnect)})
watch([textScale,spacing],()=>{localStorage.setItem('ganttist.text-scale',textScale.value);localStorage.setItem('ganttist.spacing',spacing.value)})
const drawer=ref(false), notices=ref(false), filters=ref(false), simulating=ref(false), toast=ref(''), simulation=ref<{changes:{task_id:string;start:string;finish:string}[]}|null>(null)
const dependencyTarget=ref(''), dependencyType=ref<'FS'|'SS'|'FF'|'SF'>('FS')
const drag=ref<{taskId:string;mode:'move'|'left'|'right';originX:number;start:string;finish:string}|null>(null)
const activeTask=computed(()=>store.workspace?.tasks.find(t=>t.id===store.selected.at(-1)) ?? null)
const start=new Date('2026-08-17T12:00:00'), end=new Date('2026-09-19T12:00:00')
const days=computed(()=>{ const r:Date[]=[]; for(let d=new Date(start);d<end;d.setDate(d.getDate()+1))r.push(new Date(d)); return r })
const dayWidth=computed(()=>store.zoom==='day'?64:store.zoom==='week'?42:24)
const rowHeight=computed(()=>spacing.value==='compact'?44:spacing.value==='comfortable'?49:54)
const px=(date:string|null)=>date?civilDayOffset(start,new Date(date+'T12:00:00'))*dayWidth.value:0
const width=(task:Task)=>barWidth(task.start,task.finish,dayWidth.value)
const visibleTasks=computed(()=>{const all=new Map(store.tasks.map(task=>[task.id,task]));return store.tasks.filter(task=>{let parentId=task.parent_id;while(parentId){if(store.hiddenGroups.has(parentId))return false;parentId=all.get(parentId)?.parent_id}return true})})
const monthSegments=computed(()=>{const out:{label:string;span:number}[]=[];days.value.forEach(d=>{const label=d.toLocaleDateString('pt-BR',{month:'long',year:'numeric'});const last=out.at(-1);last?.label===label?last.span++:out.push({label,span:1})});return out})
const pathFor=(from:string,to:string)=>{const a=visibleTasks.value.findIndex(t=>t.id===from),b=visibleTasks.value.findIndex(t=>t.id===to),ft=visibleTasks.value[a],tt=visibleTasks.value[b];if(a<0||b<0||!ft?.finish||!tt?.start)return '';const x1=px(ft.finish)+dayWidth.value-6,x2=px(tt.start)+4,y1=a*rowHeight.value+rowHeight.value/2,y2=b*rowHeight.value+rowHeight.value/2,mid=x1+Math.max(18,(x2-x1)/2);return `M${x1},${y1} H${mid} V${y2} H${x2}`}
function select(task:Task,e:MouseEvent){store.toggleSelect(task.id,e.ctrlKey||e.metaKey);drawer.value=true}
function schedulePayload(){const tasks=store.workspace?.tasks.filter(task=>task.kind==='task').map(task=>({id:task.id,title:task.title,start:task.start,duration:task.start&&task.finish?Math.max(1,Math.round((Date.parse(task.finish)-Date.parse(task.start))/86400000)+1):1,completed:task.status==='completed'}))??[];return {today:new Date().toISOString().slice(0,10),tasks,dependencies:store.workspace?.dependencies.map(dependency=>({from:dependency.from,to:dependency.to,type:dependency.type}))??[]}}
async function simulate(){simulating.value=true;simulation.value=null;try{const response=await fetch('/api/v1/schedule/simulate',{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json',...csrfHeaders()},body:JSON.stringify(schedulePayload())});if(!response.ok)throw new Error('Não foi possível calcular o cenário.');simulation.value=(await response.json()).data;toast.value=`Simulação pronta: ${simulation.value?.changes.length??0} tarefa(s) seriam ajustadas`}catch(error){toast.value=error instanceof Error?error.message:'Não foi possível calcular o cenário.'}finally{simulating.value=false;setTimeout(()=>toast.value='',3500)}}
async function applySimulation(){if(!simulation.value)return;try{const response=await fetch('/api/v1/schedule/apply',{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json',...csrfHeaders()},body:JSON.stringify(schedulePayload())});if(!response.ok)throw new Error('Não foi possível aplicar o cenário no Todoist.');simulation.value=null;await store.load();toast.value='Cenário aplicado no Todoist'}catch(error){toast.value=error instanceof Error?error.message:'Não foi possível aplicar o cenário.'}setTimeout(()=>toast.value='',4500)}
async function addDependency(){if(!activeTask.value||!dependencyTarget.value)return;try{const response=await fetch('/api/v1/dependencies',{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json',...csrfHeaders()},body:JSON.stringify({from:activeTask.value.id,to:dependencyTarget.value,type:dependencyType.value})});if(!response.ok)throw new Error('Não foi possível criar a dependência.');await store.load();dependencyTarget.value='';toast.value='Dependência adicionada'}catch(error){toast.value=error instanceof Error?error.message:'Não foi possível criar a dependência.'}setTimeout(()=>toast.value='',4000)}
async function removeDependency(id:string){try{const response=await fetch(`/api/v1/dependencies/${id}`,{method:'DELETE',headers:{Accept:'application/json',...csrfHeaders()}});if(!response.ok)throw new Error('Não foi possível remover a dependência.');await store.load();toast.value='Dependência removida'}catch(error){toast.value=error instanceof Error?error.message:'Não foi possível remover a dependência.'}setTimeout(()=>toast.value='',4000)}
function shiftDate(value:string,daysToAdd:number){const date=new Date(value+'T12:00:00');date.setDate(date.getDate()+daysToAdd);return date.toISOString().slice(0,10)}
function startDrag(task:Task,event:PointerEvent,mode:'move'|'left'|'right'){if(!task.start||!task.finish)return;event.preventDefault();drag.value={taskId:task.id,mode,originX:event.clientX,start:task.start,finish:task.finish};window.addEventListener('pointermove',dragMove);window.addEventListener('pointerup',endDrag,{once:true})}
function dragMove(event:PointerEvent){if(!drag.value)return;const task=store.workspace?.tasks.find(item=>item.id===drag.value?.taskId);if(!task)return;const delta=Math.round((event.clientX-drag.value.originX)/dayWidth.value);if(drag.value.mode==='move'){task.start=shiftDate(drag.value.start,delta);task.finish=shiftDate(drag.value.finish,delta)}else if(drag.value.mode==='left'){task.start=shiftDate(drag.value.start,delta);if(task.start>task.finish)task.start=task.finish}else{task.finish=shiftDate(drag.value.finish,delta);if(task.finish<task.start)task.finish=task.start}}
async function endDrag(){window.removeEventListener('pointermove',dragMove);const task=drag.value&&store.workspace?.tasks.find(item=>item.id===drag.value?.taskId);drag.value=null;if(task){await persistTask(task);toast.value='Datas atualizadas no Todoist';setTimeout(()=>toast.value='',3000)}}
async function persistTask(task:Task){const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;const response=await fetch(`/api/v1/tasks/${task.id}`,{method:'PUT',headers:{'Content-Type':'application/json',Accept:'application/json',...(csrf?{'X-CSRF-TOKEN':csrf}:{})},body:JSON.stringify({title:task.title,start:task.start,finish:task.finish,priority:task.priority??1,completed:task.status==='completed'})});if(!response.ok)throw new Error('Não foi possível salvar a tarefa no Todoist.');store.updateTask(task)}
async function saveTask(){if(!activeTask.value)return;const task={...activeTask.value};try{await persistTask(task);drawer.value=false;toast.value='Alterações salvas no Todoist';setTimeout(()=>toast.value='',3500)}catch(error){toast.value=error instanceof Error?error.message:'Não foi possível salvar as alterações';setTimeout(()=>toast.value='',4500)}}
function statusLabel(s:string){return ({completed:'Concluída',running:'Em execução',not_started:'Não iniciada',late:'Atrasada',unscheduled:'Sem data'} as Record<string,string>)[s]}
</script>

<template>
<main v-if="auth.loading" class="loading"><div class="loader-logo">G</div><p>Verificando seu acesso…</p></main>
<AuthGate v-else-if="!auth.user" :auth="auth" />
<TodoistSetup v-else-if="needsTodoist" @ready="needsTodoist=false;store.load()" />
<div v-else class="app-shell" :class="[`text-${textScale}`, `space-${spacing}`]">
  <header class="topbar">
    <div class="brand"><span class="brand-mark"><i></i><i></i><i></i></span><strong>Ganttist</strong></div>
    <div class="project-switcher"><span class="eyebrow">PROJETO TODOIST</span><button><span class="project-dot"></span>{{store.workspace?.project.name||'Carregando…'}} <span class="chevron">⌄</span></button></div>
    <div class="top-actions">
      <div class="appearance-wrap"><button class="icon-btn appearance-btn" aria-label="Aparência" title="Aparência" @click="appearance=!appearance">A<span>a</span></button><div v-if="appearance" class="appearance-menu"><b>Aparência</b><label>Tamanho do texto<select v-model="textScale"><option value="compact">Menor</option><option value="comfortable">Confortável</option><option value="large">Maior</option></select></label><label>Espaçamento<select v-model="spacing"><option value="compact">Compacto</option><option value="comfortable">Confortável</option><option value="spacious">Espaçoso</option></select></label></div></div>
      <div class="sync-pill"><span class="pulse"></span><span><b>Sincronizado</b><small>agora mesmo</small></span></div>
      <button class="icon-btn" aria-label="Notificações" @click="notices=!notices">◴<em>3</em></button>
      <button class="avatar" title="Sair" @click="auth.logout">{{(auth.user.name||auth.user.email).slice(0,2).toUpperCase()}}</button>
    </div>
  </header>

  <main v-if="store.loading" class="loading"><div class="loader-logo">G</div><p>Organizando seu planejamento…</p></main>
  <main v-else-if="store.error" class="loading"><p>{{store.error}}</p><button class="primary" @click="store.load">Tentar novamente</button></main>
  <main v-else class="main">
    <section class="commandbar">
      <div class="title-block"><div><span class="eyebrow">VISÃO DE PLANEJAMENTO</span><h1>{{store.workspace?.project.name}}</h1></div><span v-if="store.workspace?.project.id==='demo-product-launch'" class="demo-badge">AMBIENTE DEMO</span></div>
      <div class="commands">
        <label class="search"><span>⌕</span><input v-model="store.search" placeholder="Buscar tarefa…"><kbd>⌘ K</kbd></label>
        <div class="segmented"><button :class="{active:store.zoom==='day'}" @click="store.zoom='day'">Dia</button><button :class="{active:store.zoom==='week'}" @click="store.zoom='week'">Semana</button><button :class="{active:store.zoom==='month'}" @click="store.zoom='month'">Mês</button></div>
        <button class="soft-btn" @click="filters=!filters">≡ Filtros <span v-if="store.statusFilter!=='all'" class="count">1</span></button>
        <button class="primary" @click="simulate"><span v-if="simulating" class="spinner"></span><span v-else>✦</span> {{simulating?'Calculando…':'Simular cenário'}}</button><button v-if="simulation?.changes.length" class="soft-btn" @click="applySimulation">Aplicar {{simulation.changes.length}} alterações</button>
      </div>
    </section>

    <section class="stats-row">
      <article><span class="stat-icon violet">◔</span><div><small>PROGRESSO GERAL</small><b>{{store.workspace?.stats.progress}}%</b></div><div class="mini-progress"><i :style="{width:store.workspace?.stats.progress+'%'}"></i></div></article>
      <article><span class="stat-icon green">✓</span><div><small>CONCLUÍDAS</small><b>{{store.workspace?.stats.completed}} <em>/ {{store.workspace?.stats.total}}</em></b></div><span class="trend">+2 esta semana</span></article>
      <article><span class="stat-icon coral">⌁</span><div><small>CAMINHO CRÍTICO</small><b>{{store.workspace?.stats.critical}} <em>tarefas</em></b></div><span class="risk">ATENÇÃO</span></article>
      <article><span class="stat-icon amber">○</span><div><small>SEM PLANEJAMENTO</small><b>{{store.workspace?.stats.unscheduled}} <em>tarefa</em></b></div><button @click="store.statusFilter='unscheduled'">Revisar →</button></article>
    </section>

    <div v-if="filters" class="filter-popover"><b>Filtrar por estado</b><button v-for="f in [['all','Todas'],['running','Em execução'],['completed','Concluídas'],['unscheduled','Sem data']]" :class="{active:store.statusFilter===f[0]}" @click="store.statusFilter=f[0]">{{f[1]}}</button></div>

    <section class="gantt-card">
      <div class="gantt-head-left"><span>TAREFA</span><span>RESP.</span><span>STATUS</span></div>
      <div class="timeline-head" :style="{width:days.length*dayWidth+'px'}">
        <div class="months"><span v-for="m in monthSegments" :style="{width:m.span*dayWidth+'px'}">{{m.label}}</span></div>
        <div class="day-heads"><span v-for="d in days" :class="{weekend:[0,6].includes(d.getDay()),today:d.toISOString().slice(0,10)==='2026-08-16'}" :style="{width:dayWidth+'px'}"><b>{{d.toLocaleDateString('pt-BR',{weekday:'short'}).slice(0,3)}}</b>{{d.getDate()}}</span></div>
      </div>
      <div class="rows-left">
        <div v-for="task in visibleTasks" :key="task.id" class="task-row" :class="[{group:task.kind==='group',parent:task.has_children,selected:store.selected.includes(task.id)},task.status]" :style="{height:rowHeight+'px'}" @click="select(task,$event)">
          <div class="task-name" :style="{paddingLeft:(task.level*24+14)+'px'}"><button v-if="task.kind==='group'||task.has_children" class="collapse" @click.stop="store.toggleGroup(task.id)">{{store.hiddenGroups.has(task.id)?'›':'⌄'}}</button><span v-else class="status-dot"></span><div><b>{{task.title}}</b><small v-if="task.kind==='task'">#{{task.id.toUpperCase()}} · P{{task.priority}}</small></div></div>
          <div><span v-if="task.assignee" class="mini-avatar">{{task.assignee}}</span><span v-else>—</span></div>
          <div><span class="status-label"><i></i>{{statusLabel(task.status)}}</span></div>
        </div>
      </div>
      <div class="timeline-scroll">
        <div class="timeline-body" :style="{width:days.length*dayWidth+'px',height:visibleTasks.length*rowHeight+'px'}">
          <div v-for="(d,i) in days" class="day-column" :class="{weekend:[0,6].includes(d.getDay())}" :style="{left:i*dayWidth+'px',width:dayWidth+'px'}"></div>
          <div class="today-line" :style="{left:px('2026-08-17')+'px'}"><span>HOJE</span></div>
          <svg class="dependencies" :width="days.length*dayWidth" :height="visibleTasks.length*rowHeight"><defs><marker id="arrow" markerWidth="6" markerHeight="6" refX="5" refY="3" orient="auto"><path d="M0 0 L6 3 L0 6Z"/></marker><marker id="arrow-critical" markerWidth="6" markerHeight="6" refX="5" refY="3" orient="auto"><path d="M0 0 L6 3 L0 6Z"/></marker></defs><path v-for="dep in store.workspace?.dependencies" :d="pathFor(dep.from,dep.to)" :class="{critical:dep.critical}" marker-end="url(#arrow)"/></svg>
          <div v-for="(task,i) in visibleTasks" class="bar-lane" :style="{top:i*rowHeight+'px',height:rowHeight+'px'}">
            <div v-if="task.start" class="task-bar" :class="[task.kind,task.status,{critical:task.critical,parent:task.has_children}]" :style="{left:px(task.start)+'px',width:width(task)+'px'}" @click.stop="select(task,$event)" @pointerdown.stop="startDrag(task,$event,'move')">
              <template v-if="task.kind==='group'"><i class="group-line"></i><i class="group-left"></i><i class="group-right"></i></template>
              <template v-else><i class="progress-fill" :style="{width:task.progress+'%'}"></i><span class="bar-label">{{task.title}} <small v-if="task.progress">{{task.progress}}%</small></span><i class="handle left" @pointerdown.stop="startDrag(task,$event,'left')"></i><i class="handle right" @pointerdown.stop="startDrag(task,$event,'right')"></i></template>
            </div>
            <div v-else class="unscheduled-chip"><span>＋</span> Definir data</div>
          </div>
        </div>
      </div>
      <div class="gantt-footer"><span><i class="legend running"></i> Em execução</span><span><i class="legend critical"></i> Caminho crítico</span><span><i class="legend completed"></i> Concluída</span><span><i class="legend unscheduled"></i> Sem data</span><div class="footer-right">Fuso: América/São Paulo · Dias úteis visíveis <button>?</button></div></div>
    </section>
  </main>

  <aside class="drawer" :class="{open:drawer&&activeTask}"><template v-if="activeTask"><header><div><span class="eyebrow">DETALHES DA TAREFA</span><h2>{{activeTask.title}}</h2></div><button @click="drawer=false">×</button></header><div class="drawer-body"><div class="source-line"><span class="todoist-mark">✓</span><div><b>Sincronizada com Todoist</b><small>Campos nativos são atualizados na origem</small></div></div><label>Título<input v-model="activeTask.title"></label><div class="form-grid"><label>Data inicial<input v-model="activeTask.start" type="date"></label><label>Data final<input v-model="activeTask.finish" type="date"></label></div><label>Estado<select v-model="activeTask.status"><option value="not_started">Não iniciada</option><option value="running">Em execução</option><option value="completed">Concluída</option></select></label><div class="dependency-box"><div><span>⌁</span><b>Dependências</b></div><p>Esta tarefa participa de {{store.workspace?.dependencies.filter(d=>d.from===activeTask?.id||d.to===activeTask?.id).length}} relações de precedência.</p><div v-for="dependency in store.workspace?.dependencies.filter(d=>d.from===activeTask?.id||d.to===activeTask?.id)" :key="dependency.id" class="dependency-item"><span>{{dependency.from===activeTask?.id?'→':'←'}} {{dependency.type}}</span><button @click="removeDependency(dependency.id)">Remover</button></div><div class="dependency-form"><select v-model="dependencyTarget"><option value="">Outra tarefa…</option><option v-for="task in store.tasks.filter(t=>t.kind==='task'&&t.id!==activeTask?.id)" :key="task.id" :value="task.id">{{task.title}}</option></select><select v-model="dependencyType"><option value="FS">FS</option><option value="SS">SS</option><option value="FF">FF</option><option value="SF">SF</option></select><button @click="addDependency">Adicionar</button></div></div></div><footer><button class="soft-btn" @click="drawer=false">Cancelar</button><button class="primary" @click="saveTask">Salvar alterações</button></footer></template></aside>
  <div v-if="drawer" class="scrim" @click="drawer=false"></div>
  <div v-if="toast" class="toast"><span>✓</span>{{toast}}</div>
</div>
</template>
