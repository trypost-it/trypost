<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Post;

use App\Enums\Post\Status;
use App\Enums\PostPlatform\ContentType;
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
            'status' => ['required', 'string', Rule::in(array_column(Status::cases(), 'value'))],
            'synced' => ['required', 'boolean'],
            'scheduled_at' => [
                'sometimes',
                'nullable',
                'date',
                Rule::when(
                    in_array($this->input('status'), ['scheduled', 'publishing']),
                    ['after:now']
                ),
            ],
            'platforms' => ['required', 'array'],
            'platforms.*.id' => ['required', 'uuid', Rule::exists('post_platforms', 'id')->where('post_id', $this->route('post')->id ?? $this->route('post'))],
            'platforms.*.content' => ['nullable', 'string', 'max:63206'],
            'platforms.*.content_type' => ['required', 'string', Rule::in(array_column(ContentType::cases(), 'value'))],
            'platforms.*.meta' => ['nullable', 'array'],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['uuid', Rule::exists('workspace_labels', 'id')->where('workspace_id', $this->user()->currentWorkspace->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'The post status is required.',
            'status.in' => 'Invalid post status.',
            'synced.required' => 'The synced field is required.',
            'platforms.required' => 'At least one platform is required.',
            'platforms.*.content_type.required' => 'The content type is required for each platform.',
            'platforms.*.content_type.in' => 'Invalid content type.',
            'scheduled_at.after' => 'The scheduled date must be in the future.',
        ];
    }
}
