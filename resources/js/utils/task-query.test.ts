import { describe, expect, it } from 'vitest'
import { parseTaskQuery } from './task-query'

function expectMatches(query: string, title: string, expected: boolean) {
  const result = parseTaskQuery(query)
  expect(result.valid).toBe(true)
  if (result.valid) expect(result.matches(title)).toBe(expected)
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
})
