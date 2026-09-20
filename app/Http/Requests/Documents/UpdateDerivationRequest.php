<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateDerivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'autoRegenerate' => ['sometimes', 'boolean'], 'active' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'required_with:rectNormalized', 'integer', 'min:1'], 'rectNormalized' => ['sometimes', 'required_with:page', 'array'],
            'rectNormalized.x' => ['required_with:rectNormalized', 'numeric', 'min:0', 'max:1'], 'rectNormalized.y' => ['required_with:rectNormalized', 'numeric', 'min:0', 'max:1'],
            'rectNormalized.width' => ['required_with:rectNormalized', 'numeric', 'min:0.000001', 'max:1'], 'rectNormalized.height' => ['required_with:rectNormalized', 'numeric', 'min:0.000001', 'max:1'],
        ];
    }
}
