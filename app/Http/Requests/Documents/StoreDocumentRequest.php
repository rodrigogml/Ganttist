<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

final class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'], 'revisionLabel' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'max:'.config('documents.max_upload_kb')],
            'tagIds' => ['nullable', 'array'], 'tagIds.*' => ['ulid'],
        ];
    }
}
