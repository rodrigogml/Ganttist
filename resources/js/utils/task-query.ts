type QueryNode =
  | { type: 'term'; parts: string[] }
  | { type: 'predicate'; field: TaskQueryField; value: string; label: string }
  | { type: 'not'; child: QueryNode }
  | { type: 'and'; left: QueryNode; right: QueryNode }
  | { type: 'or'; left: QueryNode; right: QueryNode }

export type TaskQueryField = 'status' | 'responsavel' | 'data' | 'prioridade' | 'secao'

export type TaskQueryTarget = { title: string; status?: string | null; assigneeId?: string | null; assignee?: string | null; start?: string | null; finish?: string | null; priority?: number | null; section?: string | null }
export type TaskQueryContext = { currentAssigneeId?: string | null; today?: string }
export type TaskQueryError = { message: string; position: number }
export type TaskQueryPredicate = { field: TaskQueryField; value: string; label: string }
export type TaskQueryResult =
  | { valid: true; matches: (target: string | TaskQueryTarget, context?: TaskQueryContext) => boolean; predicates: readonly TaskQueryPredicate[] }
  | { valid: false; error: TaskQueryError }

const fields: Readonly<Record<string, TaskQueryField>> = { status: 'status', responsavel: 'responsavel', data: 'data', prioridade: 'prioridade', secao: 'secao' }
const statuses: Readonly<Record<string, string>> = { aberta: 'opened', 'em-andamento': 'in_progress', agendada: 'scheduled', atrasada: 'late', bloqueada: 'blocked', concluida: 'completed' }
const priorities: Readonly<Record<string, number>> = { alta: 4, media: 3, baixa: 2, nenhuma: 1 }

const normalize = (value: string): string => value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('pt-BR').replace(/\s+/g, ' ').trim()

class QuerySyntaxError extends Error {
  constructor(message: string, readonly position: number) { super(message) }
}

class TaskQueryParser {
  private position = 0
  constructor(private readonly source: string) {}

  parse(): QueryNode | null {
    this.skipWhitespace()
    if (this.atEnd()) return null
    const node = this.parseOr()
    this.skipWhitespace()
    if (!this.atEnd()) throw new QuerySyntaxError('Operador ou texto inesperado.', this.position)
    return node
  }

  private parseOr(): QueryNode {
    let node = this.parseAnd()
    while (this.consume('|')) node = { type: 'or', left: node, right: this.parseRequiredExpression('“|”') }
    return node
  }

  private parseAnd(): QueryNode {
    let node = this.parseUnary()
    while (this.consume('&')) node = { type: 'and', left: node, right: this.parseRequiredExpression('“&”') }
    return node
  }

  private parseRequiredExpression(operator: string): QueryNode {
    this.skipWhitespace()
    if (this.atEnd() || this.peek() === ')') throw new QuerySyntaxError(`Esperado texto após ${operator}.`, this.position)
    return this.parseUnary()
  }

  private parseUnary(): QueryNode {
    this.skipWhitespace()
    if (this.consume('!')) {
      this.skipWhitespace()
      if (this.atEnd() || this.peek() === ')') throw new QuerySyntaxError('Esperado texto após “!”.', this.position)
      return { type: 'not', child: this.parseUnary() }
    }
    if (this.consume('(')) {
      this.skipWhitespace()
      if (this.consume(')')) throw new QuerySyntaxError('Parênteses não podem estar vazios.', this.position - 1)
      const node = this.parseOr()
      this.skipWhitespace()
      if (!this.consume(')')) throw new QuerySyntaxError('Parêntese de fechamento ausente.', this.position)
      return node
    }
    if (this.atEnd() || this.peek() === ')' || this.peek() === '&' || this.peek() === '|') throw new QuerySyntaxError('Esperado texto ou “(”.', this.position)
    return this.parsePredicate() ?? this.parseTerm()
  }

  private parsePredicate(): QueryNode | null {
    const start = this.position
    let name = ''
    while (!this.atEnd() && /[\p{L}]/u.test(this.peek())) { name += this.peek(); this.position += 1 }
    const field = fields[normalize(name)]
    if (!field || this.peek() !== ':') { this.position = start; return null }
    this.position += 1
    const valuePosition = this.position
    const value = this.peek() === '"' ? this.parseQuotedValue(valuePosition) : this.parseUnquotedValue(valuePosition)
    if (!value) throw new QuerySyntaxError(`Esperado valor para “${field}:”.`, valuePosition)
    return { type: 'predicate', field, value: normalize(value), label: value.trim() }
  }

  private parseQuotedValue(valuePosition: number): string {
    this.position += 1
    let value = '', escaped = false
    while (!this.atEnd()) {
      const character = this.peek(); this.position += 1
      if (escaped) { value += character; escaped = false }
      else if (character === '\\') escaped = true
      else if (character === '"') return value
      else value += character
    }
    if (escaped) throw new QuerySyntaxError('Escape incompleto.', this.position - 1)
    throw new QuerySyntaxError('Aspa de fechamento ausente.', valuePosition)
  }

  private parseUnquotedValue(valuePosition: number): string {
    let value = '', escaped = false
    while (!this.atEnd()) {
      const character = this.peek()
      if (escaped) { value += character; this.position += 1; escaped = false; continue }
      if (character === '\\') { this.position += 1; if (this.atEnd()) throw new QuerySyntaxError('Escape incompleto.', this.position - 1); escaped = true; continue }
      if (/\s/.test(character) || '()!&|'.includes(character)) break
      value += character; this.position += 1
    }
    if (!value && this.position === valuePosition) return ''
    return value
  }

  private parseTerm(): QueryNode {
    const parts: string[] = ['']
    let escaped = false
    while (!this.atEnd()) {
      const character = this.peek()
      if (escaped) { parts[parts.length - 1] += character; this.position += 1; escaped = false; continue }
      if (character === '\\') { this.position += 1; if (this.atEnd()) throw new QuerySyntaxError('Escape incompleto.', this.position - 1); escaped = true; continue }
      if (character === '*') { parts.push(''); this.position += 1; continue }
      if ('()!&|'.includes(character)) break
      parts[parts.length - 1] += character; this.position += 1
    }
    const normalizedParts = parts.map(normalize)
    if (normalizedParts.every(part => !part)) throw new QuerySyntaxError('Esperado texto para a busca.', this.position)
    return { type: 'term', parts: normalizedParts }
  }

  private consume(character: string): boolean { this.skipWhitespace(); if (this.peek() !== character) return false; this.position += 1; return true }
  private skipWhitespace(): void { while (/\s/.test(this.peek())) this.position += 1 }
  private peek(): string { return this.source[this.position] ?? '' }
  private atEnd(): boolean { return this.position >= this.source.length }
}

function matchesTerm(parts: readonly string[], title: string): boolean {
  let position = 0
  for (const part of parts) {
    if (!part) continue
    position = title.indexOf(part, position)
    if (position < 0) return false
    position += part.length
  }
  return true
}

function overlapsRange(target: TaskQueryTarget, start: string, finish: string): boolean {
  const taskStart = target.start ?? target.finish
  const taskFinish = target.finish ?? target.start
  return Boolean(taskStart && taskFinish && taskStart <= finish && taskFinish >= start)
}

function dateAfter(date: string, days: number): string {
  const value = new Date(`${date}T00:00:00Z`)
  value.setUTCDate(value.getUTCDate() + days)
  return value.toISOString().slice(0, 10)
}

function matchesPredicate(field: TaskQueryField, value: string, target: TaskQueryTarget, context: TaskQueryContext): boolean {
  if (field === 'status') return target.status === statuses[value]
  if (field === 'responsavel') {
    if (value === 'sem') return !target.assigneeId
    if (value === 'eu') return Boolean(context.currentAssigneeId && target.assigneeId === context.currentAssigneeId)
    if (value === 'outros') return Boolean(target.assigneeId && target.assigneeId !== context.currentAssigneeId)
    return normalize(target.assignee ?? '') === value
  }
  if (field === 'prioridade') return target.priority === (/^\d$/.test(value) ? Number(value) : priorities[value])
  if (field === 'secao') return value === 'sem' ? !target.section : normalize(target.section ?? '') === value
  const today = context.today ?? new Date().toISOString().slice(0, 10)
  if (value === 'hoje') return overlapsRange(target, today, today)
  if (value === 'amanha') return overlapsRange(target, dateAfter(today, 1), dateAfter(today, 1))
  if (value === 'proximos-7-dias') return overlapsRange(target, today, dateAfter(today, 6))
  const range = /^(\d{4}-\d{2}-\d{2})\.\.(\d{4}-\d{2}-\d{2})$/.exec(value)
  return Boolean(range && range[1] <= range[2] && overlapsRange(target, range[1], range[2]))
}

function matchesNode(node: QueryNode, target: TaskQueryTarget, context: TaskQueryContext): boolean {
  if (node.type === 'term') return matchesTerm(node.parts, normalize(target.title))
  if (node.type === 'predicate') return matchesPredicate(node.field, node.value, target, context)
  if (node.type === 'not') return !matchesNode(node.child, target, context)
  if (node.type === 'and') return matchesNode(node.left, target, context) && matchesNode(node.right, target, context)
  return matchesNode(node.left, target, context) || matchesNode(node.right, target, context)
}

function predicatesFor(node: QueryNode | null): TaskQueryPredicate[] {
  if (!node) return []
  if (node.type === 'predicate') return [{ field: node.field, value: node.value, label: node.label }]
  if (node.type === 'term') return []
  if (node.type === 'not') return predicatesFor(node.child)
  return [...predicatesFor(node.left), ...predicatesFor(node.right)]
}

function removeField(node: QueryNode | null, field: TaskQueryField): QueryNode | null {
  if (!node || (node.type === 'predicate' && node.field === field)) return null
  if (node.type === 'term' || node.type === 'predicate') return node
  if (node.type === 'not') {
    const child = removeField(node.child, field)
    return child ? { type: 'not', child } : null
  }
  const left = removeField(node.left, field)
  const right = removeField(node.right, field)
  if (!left) return right
  if (!right) return left
  return { type: node.type, left, right }
}

function fieldNode(field: TaskQueryField, values: readonly string[]): QueryNode | null {
  const predicates = values.map(value => ({ type: 'predicate' as const, field, value: normalize(value), label: value.trim() }))
  return predicates.reduce<QueryNode | null>((node, predicate) => node ? { type: 'or', left: node, right: predicate } : predicate, null)
}

function stringifyNode(node: QueryNode): string {
  if (node.type === 'term') return node.parts.join('*')
  if (node.type === 'predicate') return `${node.field}:${/^[\p{L}\p{N}_.-]+$/u.test(node.label) ? node.label : JSON.stringify(node.label)}`
  if (node.type === 'not') return `!(${stringifyNode(node.child)})`
  return `(${stringifyNode(node.left)} ${node.type === 'and' ? '&' : '|'} ${stringifyNode(node.right)})`
}

/** Replaces every predicate for one funnel field while retaining the remaining expression. */
export function replaceTaskQueryField(source: string, field: TaskQueryField, values: readonly string[]): string | null {
  try {
    const node = new TaskQueryParser(source).parse()
    const remaining = removeField(node, field)
    const nextField = fieldNode(field, values)
    if (!remaining) return nextField ? stringifyNode(nextField) : ''
    if (!nextField) return stringifyNode(remaining)
    return stringifyNode({ type: 'and', left: remaining, right: nextField })
  } catch (error) {
    if (error instanceof QuerySyntaxError) return null
    throw error
  }
}

export function parseTaskQuery(source: string): TaskQueryResult {
  try {
    const node = new TaskQueryParser(source).parse()
    return { valid: true, predicates: predicatesFor(node), matches: (value, context = {}) => node === null || matchesNode(node, typeof value === 'string' ? { title: value } : value, context) }
  } catch (error) {
    if (error instanceof QuerySyntaxError) return { valid: false, error: { message: error.message, position: error.position } }
    throw error
  }
}
