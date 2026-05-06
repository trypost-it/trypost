<?php

declare(strict_types=1);

namespace App\Http\Requests\App\PostTemplate;

use Illuminate\Foundation\Http\FormRequest;

class ApplyPostTemplateRequest extends FormRequest
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
            'social_account_id' => ['nullable', 'uuid'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
