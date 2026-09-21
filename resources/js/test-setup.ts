// pdfjs-dist 6 uses the Iterator Helpers proposal during module evaluation.
// Node 20 (used by the local Vitest runtime) does not expose it yet, while
// supported browsers do. The viewer keeps its own PDF.js mocks; this only
// makes components that import it loadable in jsdom.
if (typeof globalThis.Iterator === 'undefined') {
  class IteratorPolyfill<T> implements IterableIterator<T> {
    next(): IteratorResult<T> { return { done: true, value: undefined as T } }
    [Symbol.iterator](): IterableIterator<T> { return this }
  }

  Object.defineProperty(globalThis, 'Iterator', { configurable: true, value: IteratorPolyfill })
}
