<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brand_website' => ['nullable', 'url', 'max:255'],
            'brand_description' => ['nullable', 'string', 'max:2000'],
            'brand_tone' => ['required', 'string', 'in:professional,casual,friendly,bold,inspirational,humorous,educational'],
            'brand_voice_notes' => ['nullable', 'string', 'max:2000'],
            'content_language' => ['required', 'string', 'in:en,pt-BR,es'],
        ];
    }
}
