import { describe, expect, it } from 'vitest'
import { parseTaskQuery, replaceTaskQueryField, type TaskQueryTarget } from './task-query'

function expectMatches(query: string, title: string, expected: boolean) {
  const result = parseTaskQuery(query)
  expect(result.valid).toBe(true)
  if (result.valid) expect(result.matches(title)).toBe(expected)
}

function expectTaskMatches(query: string, task: TaskQueryTarget, expected: boolean, currentAssigneeId = 'me') {
  const result = parseTaskQuery(query)
  expect(result.valid).toBe(true)
  if (result.valid) expect(result.matches(task, { currentAssigneeId, today: '2026-09-22' })).toBe(expected)
}

describe('task query parser', () => {
  it('searches direct text as a phrase without requiring quotes', () => {
    expectMatches('Bancada cozinha', 'Instalar bancada cozinha principal', true)
    expectMatches('Bancada cozinha', 'Instalar cozinha com bancada', false)
  })

  it('supports negation, conjunction, disjunction and parentheses', () => {
    expectMatches('!Bancada cozinha', 'Pintar cozinha', true)
    expectMatches('!Bancada cozinha', 'Instalar Bancada cozinha', false)
    expectMatches('!Bancada & cozinha', 'Pintar cozinha', true)
    expectMatches('!Bancada & cozinha', 'Instalar bancada na cozinha', false)
    expectMatches('(Bancada)|(Granito)', 'Polir granito', true)
    expectMatches('(Bancada)|(Granito)', 'Pintar cozinha', false)
    expectMatches('(Bancada|Granito)&!cozinha', 'Polir granito', true)
  })

  it('supports wildcard masks and accent-insensitive matching', () => {
    expectMatches('Bancada*Granito', 'Instalar bancada de granito', true)
    expectMatches('Bancada*Granito', 'Instalar granito na bancada', false)
    expectMatches('revisao', 'Revisão elétrica', true)
    expectMatches('R\\&D', 'Planejamento R&D', true)
  })

  it('reports invalid syntax with its position', () => {
    const unclosed = parseTaskQuery('(Bancada|Granito')
    const incomplete = parseTaskQuery('Bancada &')

    expect(unclosed).toMatchObject({ valid: false, error: { message: 'Parêntese de fechamento ausente.', position: 16 } })
    expect(incomplete).toMatchObject({ valid: false, error: { message: 'Esperado texto após “&”.', position: 9 } })
  })

  it('evaluates canonical status, responsible, priority and section predicates', () => {
    const task = { title: 'Preparar lançamento', status: 'opened', assigneeId: 'me', assignee: 'Ana Silva', priority: 4, section: 'Lançamento' }

    expectTaskMatches('status:aberta & responsavel:eu & prioridade:alta & secao:"Lançamento"', task, true)
    expectTaskMatches('responsavel:outros | responsavel:sem', task, false)
    expectTaskMatches('status:em-andamento | prioridade:3', task, false)
    expectTaskMatches('responsavel:"Ana Silva"', task, true)
  })

  it('evaluates relative and fixed inclusive date ranges', () => {
    const task = { title: 'Entrega', start: '2026-09-21', finish: '2026-09-24' }

    expectTaskMatches('data:hoje', task, true)
    expectTaskMatches('data:amanha', task, true)
    expectTaskMatches('data:proximos-7-dias', task, true)
    expectTaskMatches('data:2026-09-24..2026-09-24', task, true)
    expectTaskMatches('data:2026-09-25..2026-09-26', task, false)
  })

  it('keeps unknown predicate values valid and reports malformed predicate values', () => {
    expectTaskMatches('responsavel:"Pessoa ausente"', { title: 'Entrega', assignee: 'Outra pessoa' }, false)

    const missingValue = parseTaskQuery('status:')
    const unclosedQuote = parseTaskQuery('responsavel:"Ana')
    expect(missingValue).toMatchObject({ valid: false, error: { message: 'Esperado valor para “status:”.', position: 7 } })
    expect(unclosedQuote).toMatchObject({ valid: false, error: { message: 'Aspa de fechamento ausente.', position: 12 } })
  })

  it('replaces one funnel field while retaining unrelated query criteria', () => {
    expect(replaceTaskQueryField('cozinha & responsavel:"Ana Silva" & status:aberta', 'status', ['atrasada', 'bloqueada']))
      .toBe('((cozinha & responsavel:"Ana Silva") & (status:atrasada | status:bloqueada))')
    expect(replaceTaskQueryField('cozinha & status:aberta', 'status', [])).toBe('cozinha')
    expect(replaceTaskQueryField('(cozinha', 'status', ['aberta'])).toBeNull()
  })
})
