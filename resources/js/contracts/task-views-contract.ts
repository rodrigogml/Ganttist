type RecordValue = Record<string, unknown>

export type SavedTaskView = { id: string; name: string; query: string; visualState: Record<string, unknown>; formatVersion: number }

function record(value: unknown, label: string): RecordValue {
  if (!value || typeof value !== 'object' || Array.isArray(value)) throw new Error(`Contrato de views inválido: ${label}.`)
  return value as RecordValue
}
function string(value: unknown, label: string): string {
  if (typeof value !== 'string') throw new Error(`Contrato de views inválido: ${label}.`)
  return value
}
export function parseViewsResponse(payload: unknown): SavedTaskView[] {
  const data = record(payload, 'resposta').data
  if (!Array.isArray(data)) throw new Error('Contrato de views inválido: data.')
  return data.map((item, index) => parseSavedTaskView(item, `data.${index}`))
}
export function parseSavedTaskView(value: unknown, label = 'data'): SavedTaskView {
  const item = record(value, label)
  if (!Number.isInteger(item.formatVersion) || item.formatVersion !== 1) throw new Error(`Contrato de views inválido: ${label}.formatVersion.`)
  return { id: string(item.id, `${label}.id`), name: string(item.name, `${label}.name`), query: string(item.query, `${label}.query`), visualState: record(item.visualState, `${label}.visualState`), formatVersion: item.formatVersion as number }
}
