<?php

namespace App\Http\Requests;

use App\Enums\PostStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(PostStatus::class)],
            'scheduled_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'platforms' => ['sometimes', 'array'],
            'platforms.*.id' => ['required', 'uuid', 'exists:post_platforms,id'],
            'platforms.*.content' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_at.after' => 'A data de agendamento deve ser no futuro.',
        ];
    }
}
