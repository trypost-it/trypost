<?php

namespace App\Http\Requests;

use App\Enums\PostStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(PostStatus::class)],
            'scheduled_at' => ['required_if:status,' . PostStatus::Scheduled->value, 'nullable', 'date', 'after:now'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*.social_account_id' => ['required', 'uuid', 'exists:social_accounts,id'],
            'platforms.*.platform' => ['required', 'string'],
            'platforms.*.content' => ['nullable', 'string', 'max:5000'],
            'platforms.*.media_ids' => ['nullable', 'array'],
            'platforms.*.media_ids.*' => ['uuid', 'exists:post_media,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'platforms.required' => 'Selecione pelo menos uma rede social.',
            'platforms.min' => 'Selecione pelo menos uma rede social.',
            'scheduled_at.required_if' => 'A data de agendamento é obrigatória para posts agendados.',
            'scheduled_at.after' => 'A data de agendamento deve ser no futuro.',
        ];
    }
}
