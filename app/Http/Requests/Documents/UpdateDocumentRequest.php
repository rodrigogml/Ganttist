<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['sometimes', 'required', 'string', 'max:255'], 'tagIds' => ['sometimes', 'array'], 'tagIds.*' => ['ulid']];
    }
}
