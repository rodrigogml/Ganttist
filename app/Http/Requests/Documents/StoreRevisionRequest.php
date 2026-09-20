<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

final class StoreRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['revisionLabel' => ['required', 'string', 'max:100'], 'file' => ['required', 'file', 'max:'.config('documents.max_upload_kb')]];
    }
}
