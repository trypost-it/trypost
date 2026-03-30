<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Workspace;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
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
