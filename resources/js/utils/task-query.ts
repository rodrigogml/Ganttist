type QueryNode =
  | { type: 'term'; parts: string[] }
  | { type: 'not'; child: QueryNode }
  | { type: 'and'; left: QueryNode; right: QueryNode }
  | { type: 'or'; left: QueryNode; right: QueryNode }

export type TaskQueryError = { message: string; position: number }
export type TaskQueryResult =
  | { valid: true; matches: (title: string) => boolean }
  | { valid: false; error: TaskQueryError }

const normalize = (value: string): string => value
  .normalize('NFD')
  .replace(/\p{Diacritic}/gu, '')
  .toLocaleLowerCase('pt-BR')
  .replace(/\s+/g, ' ')
  .trim()

class QuerySyntaxError extends Error {
  constructor(message: string, readonly position: number) {
    super(message)
  }
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
    while (this.consume('|')) {
      node = { type: 'or', left: node, right: this.parseRequiredExpression('“|”') }
    }
    return node
  }

  private parseAnd(): QueryNode {
    let node = this.parseUnary()
    while (this.consume('&')) {
      node = { type: 'and', left: node, right: this.parseRequiredExpression('“&”') }
    }
    return node
  }

  private parseRequiredExpression(operator: string): QueryNode {
    this.skipWhitespace()
    if (this.atEnd() || this.peek() === ')') {
      throw new QuerySyntaxError(`Esperado texto após ${operator}.`, this.position)
    }
    return this.parseUnary()
  }

  private parseUnary(): QueryNode {
    this.skipWhitespace()
    if (this.consume('!')) {
      this.skipWhitespace()
      if (this.atEnd() || this.peek() === ')') {
        throw new QuerySyntaxError('Esperado texto após “!”.', this.position)
      }
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
    if (this.atEnd() || this.peek() === ')' || this.peek() === '&' || this.peek() === '|') {
      throw new QuerySyntaxError('Esperado texto ou “(”.', this.position)
    }
    return this.parseTerm()
  }

  private parseTerm(): QueryNode {
    const parts: string[] = ['']
    let escaped = false
    while (!this.atEnd()) {
      const character = this.peek()
      if (escaped) {
        parts[parts.length - 1] += character
        this.position += 1
        escaped = false
        continue
      }
      if (character === '\\') {
        this.position += 1
        if (this.atEnd()) throw new QuerySyntaxError('Escape incompleto.', this.position - 1)
        escaped = true
        continue
      }
      if (character === '*') {
        parts.push('')
        this.position += 1
        continue
      }
      if ('()!&|'.includes(character)) break
      parts[parts.length - 1] += character
      this.position += 1
    }

    const normalizedParts = parts.map(normalize)
    if (normalizedParts.every(part => !part)) {
      throw new QuerySyntaxError('Esperado texto para a busca.', this.position)
    }
    return { type: 'term', parts: normalizedParts }
  }

  private consume(character: string): boolean {
    this.skipWhitespace()
    if (this.peek() !== character) return false
    this.position += 1
    return true
  }

  private skipWhitespace(): void {
    while (/\s/.test(this.peek())) this.position += 1
  }

  private peek(): string {
    return this.source[this.position] ?? ''
  }

  private atEnd(): boolean {
    return this.position >= this.source.length
  }
}

function matchesNode(node: QueryNode, title: string): boolean {
  if (node.type === 'term') {
    let position = 0
    for (const part of node.parts) {
      if (!part) continue
      position = title.indexOf(part, position)
      if (position < 0) return false
      position += part.length
    }
    return true
  }
  if (node.type === 'not') return !matchesNode(node.child, title)
  if (node.type === 'and') return matchesNode(node.left, title) && matchesNode(node.right, title)
  return matchesNode(node.left, title) || matchesNode(node.right, title)
}

export function parseTaskQuery(source: string): TaskQueryResult {
  try {
    const node = new TaskQueryParser(source).parse()
    return {
      valid: true,
      matches: title => node === null || matchesNode(node, normalize(title)),
    }
  } catch (error) {
    if (error instanceof QuerySyntaxError) {
      return { valid: false, error: { message: error.message, position: error.position } }
    }
    throw error
  }
}
