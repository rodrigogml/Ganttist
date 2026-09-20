<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Documents;

use App\Http\Controllers\Controller;
use App\Services\ProjectAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentContentController extends Controller
{
    public function __construct(private readonly ProjectAccess $access) {}

    public function __invoke(Request $request, string $projectId, string $revisionId): StreamedResponse
    {
        $this->access->view($request->user(), $projectId);
        $revision = DB::table('project_document_revisions')
            ->join('project_documents', 'project_documents.id', '=', 'project_document_revisions.document_id')
            ->where('project_document_revisions.id', $revisionId)->where('project_documents.project_id', $projectId)
            ->first(['project_document_revisions.*']) ?? abort(404);
        $size = (int) $revision->size_bytes;
        [$start, $end, $partial] = $this->range($request->header('Range'), $size);
        $length = $end - $start + 1;
        $inline = $revision->mime_type === 'application/pdf' || in_array($revision->mime_type, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true);
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $revision->original_filename ?: "revision-{$revision->id}");
        $headers = [
            'Content-Type' => $revision->mime_type,
            'Content-Length' => (string) $length,
            'Accept-Ranges' => 'bytes',
            'ETag' => '"'.$revision->sha256.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$filename.'"',
        ];
        if ($partial) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return response()->stream(function () use ($revision, $start, $length): void {
            $stream = Storage::disk($revision->storage_disk)->readStream($revision->storage_key);
            abort_unless(is_resource($stream), 404);
            try {
                if ($start > 0) {
                    if (fseek($stream, $start) !== 0) {
                        $remaining = $start;
                        while ($remaining > 0 && ! feof($stream)) {
                            $remaining -= strlen((string) fread($stream, min(8192, $remaining)));
                        }
                    }
                }
                $remaining = $length;
                while ($remaining > 0 && ! feof($stream)) {
                    $chunk = fread($stream, min(1024 * 1024, $remaining));
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    echo $chunk;
                    $remaining -= strlen($chunk);
                }
            } finally {
                fclose($stream);
            }
        }, $partial ? 206 : 200, $headers);
    }

    /** @return array{int,int,bool} */
    private function range(?string $header, int $size): array
    {
        abort_if($size < 1, 404);
        if ($header === null || $header === '') {
            return [0, $size - 1, false];
        }
        $rangeHeaders = ['Content-Range' => "bytes */{$size}"];
        abort_unless(preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $matches) === 1, 416, 'Intervalo de bytes inválido.', $rangeHeaders);
        abort_if($matches[1] === '' && $matches[2] === '', 416, 'Intervalo de bytes inválido.', $rangeHeaders);
        if ($matches[1] === '') {
            $suffix = min((int) $matches[2], $size);
            abort_if($suffix < 1, 416, 'Intervalo de bytes inválido.', $rangeHeaders);

            return [$size - $suffix, $size - 1, true];
        }
        $start = (int) $matches[1];
        $end = $matches[2] === '' ? $size - 1 : min((int) $matches[2], $size - 1);
        abort_if($start >= $size || $end < $start, 416, 'Intervalo de bytes inválido.', $rangeHeaders);

        return [$start, $end, true];
    }
}
