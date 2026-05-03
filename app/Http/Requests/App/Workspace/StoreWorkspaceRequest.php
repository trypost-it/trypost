<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Workspace;

use App\Enums\Workspace\BrandFont;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hex = ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'brand_website' => ['nullable', 'url', 'max:255'],
            'brand_description' => ['nullable', 'string', 'max:2000'],
            'brand_tone' => ['nullable', 'string', 'in:professional,casual,friendly,bold,inspirational,humorous,educational'],
            'brand_voice_notes' => ['nullable', 'string', 'max:2000'],
            'brand_color' => $hex,
            'background_color' => $hex,
            'text_color' => $hex,
            'brand_font' => ['sometimes', 'string', Rule::in(BrandFont::values())],
            'content_language' => ['nullable', 'string', 'in:en,pt-BR,es'],
            'logo_url' => ['nullable', 'url', 'max:1024'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do workspace é obrigatório.',
            'name.max' => 'O nome do workspace deve ter no máximo 255 caracteres.',
        ];
    }
}
