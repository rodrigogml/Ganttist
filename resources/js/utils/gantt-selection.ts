export interface SelectionModifiers {
  additive?: boolean
  range?: boolean
}

export interface SelectionState {
  selected: string[]
  anchor: string | null
  cursor: string | null
}

export function selectGanttRow(
  state: SelectionState,
  visibleIds: string[],
  id: string,
  modifiers: SelectionModifiers = {},
): SelectionState {
  const targetIndex = visibleIds.indexOf(id)
  const anchorIndex = state.anchor ? visibleIds.indexOf(state.anchor) : -1
  let selected: string[]

  if (modifiers.range && targetIndex >= 0 && anchorIndex >= 0) {
    const [start, end] = anchorIndex < targetIndex ? [anchorIndex, targetIndex] : [targetIndex, anchorIndex]
    const interval = visibleIds.slice(start, end + 1)
    selected = modifiers.additive ? [...new Set([...state.selected, ...interval])] : interval
  } else if (modifiers.additive) {
    selected = state.selected.includes(id)
      ? state.selected.filter(selectedId => selectedId !== id)
      : [...state.selected, id]
  } else {
    selected = [id]
  }

  const anchor = modifiers.range && anchorIndex >= 0 ? state.anchor : id

  return { selected, anchor, cursor: id }
}
