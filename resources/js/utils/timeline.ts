export function civilDayOffset(from:string|Date,to:string|Date):number {
  const a=typeof from==='string'?new Date(from+'T12:00:00'):from
  const b=typeof to==='string'?new Date(to+'T12:00:00'):to
  return Math.round((b.getTime()-a.getTime())/86_400_000)
}
export function barWidth(start:string|null,finish:string|null,dayWidth:number):number {
  return start&&finish?(civilDayOffset(start,finish)+1)*dayWidth:0
}
