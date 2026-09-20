<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['sometimes', 'required', 'string', 'max:100'], 'parentTagId' => ['sometimes', 'nullable', 'ulid']];
    }
}
