import { ref } from 'vue'

export type TextScale = 'compact' | 'comfortable' | 'large'
export type Spacing = 'compact' | 'comfortable' | 'spacious'

export const textScale = ref<TextScale>('comfortable')
export const spacing = ref<Spacing>('comfortable')
let initialized = false

function isTextScale(value: string | null): value is TextScale {
  return value === 'compact' || value === 'comfortable' || value === 'large'
}

function isSpacing(value: string | null): value is Spacing {
  return value === 'compact' || value === 'comfortable' || value === 'spacious'
}

function apply(): void {
  document.documentElement.classList.remove('text-compact', 'text-comfortable', 'text-large', 'space-compact', 'space-comfortable', 'space-spacious')
  document.documentElement.classList.add(`text-${textScale.value}`, `space-${spacing.value}`)
}

/** Applies the single, application-wide visual preference source. */
export function initializeAppearancePreferences(): void {
  if (initialized) return
  initialized = true
  const savedText = localStorage.getItem('ganttist.text-scale')
  const savedSpacing = localStorage.getItem('ganttist.spacing')
  if (isTextScale(savedText)) textScale.value = savedText
  if (isSpacing(savedSpacing)) spacing.value = savedSpacing
  apply()
}

export function updateTextScale(value: TextScale): void {
  textScale.value = value
  localStorage.setItem('ganttist.text-scale', value)
  apply()
}

export function updateSpacing(value: Spacing): void {
  spacing.value = value
  localStorage.setItem('ganttist.spacing', value)
  apply()
}
