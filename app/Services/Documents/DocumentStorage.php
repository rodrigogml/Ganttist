<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Contracts\Documents\DocumentStorageContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class DocumentStorage implements DocumentStorageContract
{
    /** @return array{disk:string,key:string,size:int,sha256:string,mime:string,original_name:string} */
    public function storeUpload(string $projectId, string $documentId, string $revisionId, UploadedFile $file): array
    {
        abort_unless($file->isValid(), 422, 'O upload não foi concluído corretamente.');
        $source = $file->getRealPath();
        abort_unless(is_string($source) && is_file($source), 422, 'Arquivo de upload indisponível.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($source) ?: 'application/octet-stream';
        $size = filesize($source);
        $sha256 = hash_file('sha256', $source);
        if ($size === false || $sha256 === false) {
            throw new RuntimeException('Não foi possível validar o arquivo enviado.');
        }

        $disk = (string) config('documents.disk', 'local');
        $key = "projects/{$projectId}/documents/{$documentId}/revisions/{$revisionId}/content";
        $stream = fopen($source, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Não foi possível abrir o arquivo enviado.');
        }

        try {
            if (! Storage::disk($disk)->writeStream($key, $stream, ['visibility' => 'private'])) {
                throw new RuntimeException('Não foi possível armazenar o documento.');
            }
        } finally {
            fclose($stream);
        }

        return [
            'disk' => $disk,
            'key' => $key,
            'size' => (int) $size,
            'sha256' => $sha256,
            'mime' => $mime,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
        ];
    }

    public function delete(string $disk, string $key): void
    {
        Storage::disk($disk)->delete($key);
    }
}
