<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Workspace;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'brand_website' => ['nullable', 'url', 'max:255'],
            'brand_description' => ['nullable', 'string', 'max:2000'],
            'brand_tone' => ['nullable', 'string', 'in:professional,casual,friendly,bold,inspirational,humorous,educational'],
            'brand_voice_notes' => ['nullable', 'string', 'max:2000'],
            'content_language' => ['sometimes', 'string', 'in:en,pt-BR,es'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The workspace name is required.',
            'name.max' => 'The workspace name must be at most 255 characters.',
        ];
    }
}
