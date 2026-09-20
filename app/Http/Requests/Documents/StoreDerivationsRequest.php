<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

final class StoreDerivationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'], 'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.page' => ['required', 'integer', 'min:1'], 'items.*.rectNormalized' => ['required', 'array'],
            'items.*.rectNormalized.x' => ['required', 'numeric', 'min:0', 'max:1'], 'items.*.rectNormalized.y' => ['required', 'numeric', 'min:0', 'max:1'],
            'items.*.rectNormalized.width' => ['required', 'numeric', 'min:0.000001', 'max:1'], 'items.*.rectNormalized.height' => ['required', 'numeric', 'min:0.000001', 'max:1'],
            'items.*.tagIds' => ['nullable', 'array'], 'items.*.tagIds.*' => ['ulid'], 'items.*.autoRegenerate' => ['nullable', 'boolean'],
        ];
    }
}
