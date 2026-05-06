<?php

declare(strict_types=1);

namespace App\Http\Requests\App\PostTemplate;

use Illuminate\Foundation\Http\FormRequest;

class IndexPostTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'platform' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:120'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
