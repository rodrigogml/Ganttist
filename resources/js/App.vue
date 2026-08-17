<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AuthGate from './AuthGate.vue'
import TodoistSetup from './TodoistSetup.vue'
import { useAuthStore } from './stores/auth'
import { useWorkspaceStore } from './stores/workspace'
import type { Task } from './types'
import { barWidth, civilDayOffset } from './utils/timeline'
const store=useWorkspaceStore(); const auth=useAuthStore(); const needsTodoist=ref(false); onMounted(async()=>{await auth.bootstrap();if(auth.user){const response=await fetch('/api/v1/todoist/status',{headers:{Accept:'application/json'}});const status=await response.json();needsTodoist.value=!status.connected||!status.project;if(!needsTodoist.value)store.load()}})
const drawer=ref(false), notices=ref(false), filters=ref(false), simulating=ref(false), toast=ref('')
const activeTask=computed(()=>store.workspace?.tasks.find(t=>t.id===store.selected.at(-1)) ?? null)
const start=new Date('2026-08-17T12:00:00'), end=new Date('2026-09-19T12:00:00')
const days=computed(()=>{ const r:Date[]=[]; for(let d=new Date(start);d<end;d.setDate(d.getDate()+1))r.push(new Date(d)); return r })
const dayWidth=computed(()=>store.zoom==='day'?64:store.zoom==='week'?42:24)
const px=(date:string|null)=>date?civilDayOffset(start,new Date(date+'T12:00:00'))*dayWidth.value:0
const width=(task:Task)=>barWidth(task.start,task.finish,dayWidth.value)
const visibleTasks=computed(()=>store.tasks.filter((task,i,all)=>{ if(task.level===0)return true; const group=[...all.slice(0,i)].reverse().find(t=>t.kind==='group'); return !group||!store.hiddenGroups.has(group.id) }))
const monthSegments=computed(()=>{const out:{label:string;span:number}[]=[];days.value.forEach(d=>{const label=d.toLocaleDateString('pt-BR',{month:'long',year:'numeric'});const last=out.at(-1);last?.label===label?last.span++:out.push({label,span:1})});return out})
const pathFor=(from:string,to:string)=>{const a=visibleTasks.value.findIndex(t=>t.id===from),b=visibleTasks.value.findIndex(t=>t.id===to),ft=visibleTasks.value[a],tt=visibleTasks.value[b];if(a<0||b<0||!ft?.finish||!tt?.start)return '';const x1=px(ft.finish)+dayWidth.value-6,x2=px(tt.start)+4,y1=a*54+27,y2=b*54+27,mid=x1+Math.max(18,(x2-x1)/2);return `M${x1},${y1} H${mid} V${y2} H${x2}`}
function select(task:Task,e:MouseEvent){store.toggleSelect(task.id,e.ctrlKey||e.metaKey);drawer.value=true}
function simulate(){simulating.value=true;setTimeout(()=>{simulating.value=false;toast.value='Simulação pronta: 4 tarefas seriam ajustadas';setTimeout(()=>toast.value='',3500)},900)}
function saveTask(){if(activeTask.value)store.updateTask({...activeTask.value});drawer.value=false;toast.value='Alterações salvas e enfileiradas para sincronização';setTimeout(()=>toast.value='',3500)}
function statusLabel(s:string){return ({completed:'Concluída',running:'Em execução',not_started:'Não iniciada',late:'Atrasada',unscheduled:'Sem data'} as Record<string,string>)[s]}
</script>

<template>
<main v-if="auth.loading" class="loading"><div class="loader-logo">G</div><p>Verificando seu acesso…</p></main>
<AuthGate v-else-if="!auth.user" :auth="auth" />
<TodoistSetup v-else-if="needsTodoist" @ready="needsTodoist=false;store.load()" />
<div v-else class="app-shell">
  <header class="topbar">
    <div class="brand"><span class="brand-mark"><i></i><i></i><i></i></span><strong>Ganttist</strong></div>
    <div class="project-switcher"><span class="eyebrow">PROJETO TODOIST</span><button><span class="project-dot"></span>{{store.workspace?.project.name||'Carregando…'}} <span class="chevron">⌄</span></button></div>
    <div class="top-actions">
      <div class="sync-pill"><span class="pulse"></span><span><b>Sincronizado</b><small>agora mesmo</small></span></div>
      <button class="icon-btn" aria-label="Notificações" @click="notices=!notices">◴<em>3</em></button>
      <button class="avatar" title="Sair" @click="auth.logout">{{(auth.user.name||auth.user.email).slice(0,2).toUpperCase()}}</button>
    </div>
  </header>

  <main v-if="store.loading" class="loading"><div class="loader-logo">G</div><p>Organizando seu planejamento…</p></main>
  <main v-else-if="store.error" class="loading"><p>{{store.error}}</p><button class="primary" @click="store.load">Tentar novamente</button></main>
  <main v-else class="main">
    <section class="commandbar">
      <div class="title-block"><div><span class="eyebrow">VISÃO DE PLANEJAMENTO</span><h1>{{store.workspace?.project.name}}</h1></div><span class="demo-badge">AMBIENTE DEMO</span></div>
      <div class="commands">
        <label class="search"><span>⌕</span><input v-model="store.search" placeholder="Buscar tarefa…"><kbd>⌘ K</kbd></label>
        <div class="segmented"><button :class="{active:store.zoom==='day'}" @click="store.zoom='day'">Dia</button><button :class="{active:store.zoom==='week'}" @click="store.zoom='week'">Semana</button><button :class="{active:store.zoom==='month'}" @click="store.zoom='month'">Mês</button></div>
        <button class="soft-btn" @click="filters=!filters">≡ Filtros <span v-if="store.statusFilter!=='all'" class="count">1</span></button>
        <button class="primary" @click="simulate"><span v-if="simulating" class="spinner"></span><span v-else>✦</span> {{simulating?'Calculando…':'Simular cenário'}}</button>
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
        <div v-for="task in visibleTasks" :key="task.id" class="task-row" :class="[{group:task.kind==='group',selected:store.selected.includes(task.id)},task.status]" @click="select(task,$event)">
          <div class="task-name" :style="{paddingLeft:(task.level*24+14)+'px'}"><button v-if="task.kind==='group'" class="collapse" @click.stop="store.toggleGroup(task.id)">{{store.hiddenGroups.has(task.id)?'›':'⌄'}}</button><span v-else class="status-dot"></span><div><b>{{task.title}}</b><small v-if="task.kind==='task'">#{{task.id.toUpperCase()}} · P{{task.priority}}</small></div></div>
          <div><span v-if="task.assignee" class="mini-avatar">{{task.assignee}}</span><span v-else>—</span></div>
          <div><span class="status-label"><i></i>{{statusLabel(task.status)}}</span></div>
        </div>
      </div>
      <div class="timeline-scroll">
        <div class="timeline-body" :style="{width:days.length*dayWidth+'px',height:visibleTasks.length*54+'px'}">
          <div v-for="(d,i) in days" class="day-column" :class="{weekend:[0,6].includes(d.getDay())}" :style="{left:i*dayWidth+'px',width:dayWidth+'px'}"></div>
          <div class="today-line" :style="{left:px('2026-08-17')+'px'}"><span>HOJE</span></div>
          <svg class="dependencies" :width="days.length*dayWidth" :height="visibleTasks.length*54"><defs><marker id="arrow" markerWidth="6" markerHeight="6" refX="5" refY="3" orient="auto"><path d="M0 0 L6 3 L0 6Z"/></marker><marker id="arrow-critical" markerWidth="6" markerHeight="6" refX="5" refY="3" orient="auto"><path d="M0 0 L6 3 L0 6Z"/></marker></defs><path v-for="dep in store.workspace?.dependencies" :d="pathFor(dep.from,dep.to)" :class="{critical:dep.critical}" marker-end="url(#arrow)"/></svg>
          <div v-for="(task,i) in visibleTasks" class="bar-lane" :style="{top:i*54+'px'}">
            <div v-if="task.start" class="task-bar" :class="[task.kind,task.status,{critical:task.critical}]" :style="{left:px(task.start)+'px',width:width(task)+'px'}" @click.stop="select(task,$event)">
              <template v-if="task.kind==='group'"><i class="group-line"></i><i class="group-left"></i><i class="group-right"></i></template>
              <template v-else><i class="progress-fill" :style="{width:task.progress+'%'}"></i><span class="bar-label">{{task.title}} <small v-if="task.progress">{{task.progress}}%</small></span><i class="handle left"></i><i class="handle right"></i></template>
            </div>
            <div v-else class="unscheduled-chip"><span>＋</span> Definir data</div>
          </div>
        </div>
      </div>
      <div class="gantt-footer"><span><i class="legend running"></i> Em execução</span><span><i class="legend critical"></i> Caminho crítico</span><span><i class="legend completed"></i> Concluída</span><span><i class="legend unscheduled"></i> Sem data</span><div class="footer-right">Fuso: América/São Paulo · Dias úteis visíveis <button>?</button></div></div>
    </section>
  </main>

  <aside class="drawer" :class="{open:drawer&&activeTask}"><template v-if="activeTask"><header><div><span class="eyebrow">DETALHES DA TAREFA</span><h2>{{activeTask.title}}</h2></div><button @click="drawer=false">×</button></header><div class="drawer-body"><div class="source-line"><span class="todoist-mark">✓</span><div><b>Sincronizada com Todoist</b><small>Campos nativos são atualizados na origem</small></div></div><label>Título<input v-model="activeTask.title"></label><div class="form-grid"><label>Data inicial<input v-model="activeTask.start" type="date"></label><label>Data final<input v-model="activeTask.finish" type="date"></label></div><label>Estado<select v-model="activeTask.status"><option value="not_started">Não iniciada</option><option value="running">Em execução</option><option value="completed">Concluída</option></select></label><div class="dependency-box"><div><span>⌁</span><b>Dependências</b></div><p>Esta tarefa participa de {{store.workspace?.dependencies.filter(d=>d.from===activeTask?.id||d.to===activeTask?.id).length}} relações de precedência.</p><button>Gerenciar relações →</button></div></div><footer><button class="soft-btn" @click="drawer=false">Cancelar</button><button class="primary" @click="saveTask">Salvar alterações</button></footer></template></aside>
  <div v-if="drawer" class="scrim" @click="drawer=false"></div>
  <div v-if="toast" class="toast"><span>✓</span>{{toast}}</div>
</div>
</template>
