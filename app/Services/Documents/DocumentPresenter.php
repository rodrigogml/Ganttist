<?php

declare(strict_types=1);

namespace App\Services\Documents;

use Illuminate\Support\Facades\DB;

final class DocumentPresenter
{
    /** @return array<string, mixed> */
    public function one(object $document): array
    {
        $revision = $document->current_revision_id
            ? DB::table('project_document_revisions')->where('id', $document->current_revision_id)->first()
            : null;
        $tags = DB::table('project_tags')
            ->join('project_document_tag', 'project_document_tag.tag_id', '=', 'project_tags.id')
            ->where('project_document_tag.document_id', $document->id)
            ->whereNull('project_tags.archived_at')
            ->orderBy('project_tags.name')
            ->get(['project_tags.id', 'project_tags.name', 'project_tags.parent_tag_id'])
            ->map(fn (object $tag): array => ['id' => $tag->id, 'name' => $tag->name, 'parentTagId' => $tag->parent_tag_id])
            ->all();
        $derivation = DB::table('project_document_derivations')->where('target_document_id', $document->id)->first();
        $sourceDocument = $derivation ? DB::table('project_documents')->where('id', $derivation->source_document_id)->first(['id', 'name']) : null;
        $latestRun = $derivation ? DB::table('project_document_derivation_runs')->where('derivation_id', $derivation->id)->orderByDesc('created_at')->first() : null;
        $sourceCurrentRevision = $derivation ? DB::table('project_documents')->where('id', $derivation->source_document_id)->value('current_revision_id') : null;

        return [
            'id' => $document->id,
            'projectId' => $document->project_id,
            'name' => $document->name,
            'kind' => $document->kind,
            'archivedAt' => $document->archived_at,
            'currentRevision' => $revision ? $this->revision($document->project_id, $revision) : null,
            'tags' => $tags,
            'derivation' => $derivation ? [
                'id' => $derivation->id,
                'sourceDocumentId' => $derivation->source_document_id,
                'sourceDocumentName' => $sourceDocument?->name,
                'processor' => $derivation->processor,
                'processorVersion' => (int) $derivation->processor_version,
                'definitionVersion' => (int) $derivation->definition_version,
                'configuration' => json_decode($derivation->configuration, true, flags: JSON_THROW_ON_ERROR),
                'autoRegenerate' => (bool) $derivation->auto_regenerate,
                'active' => (bool) $derivation->active,
            ] : null,
            'processing' => $latestRun ? [
                'runId' => $latestRun->id,
                'status' => $latestRun->status,
                'errorCode' => $latestRun->error_code,
                'errorMessage' => $latestRun->error_message,
            ] : null,
            'outdated' => (bool) ($derivation && $revision && $sourceCurrentRevision && $revision->source_revision_id !== $sourceCurrentRevision),
            'createdAt' => $document->created_at,
            'updatedAt' => $document->updated_at,
        ];
    }

    /** @return array<string, mixed> */
    public function revision(string $projectId, object $revision): array
    {
        return [
            'id' => $revision->id,
            'label' => $revision->revision_label,
            'sequence' => (int) $revision->revision_sequence,
            'mimeType' => $revision->mime_type,
            'originalFilename' => $revision->original_filename,
            'sizeBytes' => (int) $revision->size_bytes,
            'sha256' => $revision->sha256,
            'sourceRevisionId' => $revision->source_revision_id,
            'derivationId' => $revision->derivation_id,
            'contentUrl' => "/api/v1/projects/{$projectId}/revisions/{$revision->id}/content",
            'createdAt' => $revision->created_at,
        ];
    }
}
