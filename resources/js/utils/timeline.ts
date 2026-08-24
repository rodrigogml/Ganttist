export function civilDayOffset(from:string|Date,to:string|Date):number {
  const a=typeof from==='string'?new Date(from+'T12:00:00'):from
  const b=typeof to==='string'?new Date(to+'T12:00:00'):to
  return Math.round((b.getTime()-a.getTime())/86_400_000)
}
export function barWidth(start:string|null,finish:string|null,dayWidth:number):number {
  return start&&finish?(civilDayOffset(start,finish)+1)*dayWidth:0
}

export function civilDate(value:string|null|undefined):string|null {
  if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return null
  const date=new Date(value+'T12:00:00')
  return Number.isNaN(date.getTime())||date.getFullYear()!==Number(value.slice(0,4))||date.getMonth()+1!==Number(value.slice(5,7))||date.getDate()!==Number(value.slice(8,10))?null:value
}

export function visualTaskRange(start:string|null|undefined,finish:string|null|undefined,today:string):{start:string;finish:string} {
  const normalizedStart=civilDate(start)
  if (!normalizedStart) return {start:today,finish:today}
  const normalizedFinish=civilDate(finish)
  return {start:normalizedStart,finish:normalizedFinish&&civilDayOffset(normalizedStart,normalizedFinish)>=0?normalizedFinish:normalizedStart}
}
