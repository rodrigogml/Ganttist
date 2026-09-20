<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

final class ListDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'], 'kind' => ['nullable', 'in:manual,derived'],
            'mimeFamily' => ['nullable', 'string', 'max:100'], 'state' => ['nullable', 'in:ready,queued,processing,failed,outdated'],
            'tagIds' => ['nullable', 'array'], 'tagIds.*' => ['ulid'], 'archived' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'], 'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
