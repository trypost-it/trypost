<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string'],
            'scheduled_at' => ['sometimes', 'nullable', 'string'],
            'platforms' => ['sometimes', 'array'],
            'platforms.*.id' => ['required', 'uuid', 'exists:post_platforms,id'],
            'platforms.*.content' => ['nullable', 'string', 'max:5000'],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['uuid', 'exists:workspace_labels,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_at.after' => 'The scheduled date must be in the future.',
        ];
    }
}
