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
            'status' => ['required', 'string', Rule::in([Status::Draft->value, Status::Scheduled->value, Status::Publishing->value])],
            'content' => ['nullable', 'string', 'max:63206'],
            'media' => ['sometimes', 'array'],
            'media.*.id' => ['required', 'string'],
            'media.*.path' => ['required', 'string', 'max:500'],
            'media.*.url' => ['required', 'string', 'max:2048'],
            'scheduled_at' => [
                'sometimes',
                'nullable',
                'date',
                Rule::when(
                    $this->input('status') === Status::Scheduled->value,
                    ['after:now']
                ),
            ],
            'platforms' => ['sometimes', 'array'],
            'platforms.*.id' => ['required', 'uuid', Rule::exists('post_platforms', 'id')->where('post_id', $this->route('post')->id ?? $this->route('post'))],
            'platforms.*.content_type' => ['sometimes', 'string', Rule::in(array_column(ContentType::cases(), 'value'))],
            'platforms.*.meta' => ['nullable', 'array'],
            'platforms.*.meta.privacy_level' => ['sometimes', 'string', Rule::in(['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY'])],
            'platforms.*.meta.auto_add_music' => ['sometimes', 'boolean'],
            'platforms.*.meta.allow_comments' => ['sometimes', 'boolean'],
            'platforms.*.meta.allow_duet' => ['sometimes', 'boolean'],
            'platforms.*.meta.allow_stitch' => ['sometimes', 'boolean'],
            'platforms.*.meta.is_aigc' => ['sometimes', 'boolean'],
            'platforms.*.meta.brand_content_toggle' => ['sometimes', 'boolean'],
            'platforms.*.meta.brand_organic_toggle' => ['sometimes', 'boolean'],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['uuid', Rule::exists('workspace_labels', 'id')->where('workspace_id', $this->user()->currentWorkspace->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'The post status is required.',
            'status.in' => 'Invalid post status.',
            'platforms.*.content_type.in' => 'Invalid content type.',
            'scheduled_at.after' => 'The scheduled date must be in the future.',
        ];
    }
}
