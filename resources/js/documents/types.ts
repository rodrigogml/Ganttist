export type DocumentTag = { id: string; name: string; parentTagId: string | null }
export type DocumentRevision = { id: string; label: string; sequence: number; mimeType: string; originalFilename: string | null; sizeBytes: number; sha256: string; contentUrl: string; createdAt: string }
export type ProjectDocument = {
  id: string; projectId: string; name: string; kind: 'manual' | 'derived'; archivedAt: string | null
  currentRevision: DocumentRevision | null; tags: DocumentTag[]; outdated: boolean
  processing: null | { runId: string; status: 'queued' | 'processing' | 'succeeded' | 'failed'; errorMessage?: string | null }
  derivation?: null | { id: string; sourceDocumentId: string; sourceDocumentName?: string | null; autoRegenerate: boolean; active: boolean; definitionVersion?: number; configuration: CropConfiguration }
}
export type CropConfiguration = { page: number; coordinateSpace: 'effective-crop-box-top-left-v1'; rectNormalized: CropRect }
export type CropRect = { x: number; y: number; width: number; height: number }
