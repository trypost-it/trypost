<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Post;

use App\Enums\Post\Status;
use App\Enums\PostPlatform\ContentType;
use App\Models\Post;
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
            'platforms' => ['required', 'array'],
            'platforms.*.id' => ['required', 'uuid', Rule::exists('post_platforms', 'id')->where('post_id', $this->route('post') instanceof Post ? $this->route('post')->id : $this->route('post'))],
            'platforms.*.content' => ['nullable', 'string', 'max:63206'],
            'platforms.*.content_type' => ['required', 'string', Rule::in(array_column(ContentType::cases(), 'value'))],
            'platforms.*.meta' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date'],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['uuid'],
        ];
    }
}
