<?php

declare(strict_types=1);

namespace App\Contracts\Documents;

use Illuminate\Http\UploadedFile;

interface DocumentStorageContract
{
    /** @return array{disk:string,key:string,size:int,sha256:string,mime:string,original_name:string} */
    public function storeUpload(string $projectId, string $documentId, string $revisionId, UploadedFile $file): array;

    public function delete(string $disk, string $key): void;
}
