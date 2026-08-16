import { describe,expect,it } from 'vitest'
import { barWidth,civilDayOffset } from './timeline'
describe('timeline civil-date geometry',()=>{
  it('uses inclusive whole-day bars',()=>expect(barWidth('2026-08-17','2026-08-17',42)).toBe(42))
  it('does not divide timezone timestamps',()=>expect(civilDayOffset('2026-08-17','2026-08-24')).toBe(7))
  it('does not render an unscheduled bar',()=>expect(barWidth(null,null,42)).toBe(0))
})
