export type TaskStatus = 'completed'|'running'|'not_started'|'late'|'unscheduled'
export interface Task { id:string; title:string; kind:'task'|'group'; level:number; parent_id?:string|null; has_children?:boolean; start:string|null; finish:string|null; progress:number; status:TaskStatus; critical:boolean; priority?:number; assignee?:string }
export interface Dependency { id:string; from:string; to:string; type:'FS'|'SS'|'FF'|'SF'; critical:boolean }
export interface Workspace { project:{id:string;name:string;source:string;sync_status:string;updated_at:string}; tasks:Task[]; dependencies:Dependency[]; stats:{progress:number;completed:number;total:number;critical:number;unscheduled:number} }
