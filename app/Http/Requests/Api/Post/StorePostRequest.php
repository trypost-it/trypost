<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Post;

use App\Enums\Post\Status;
use App\Enums\PostPlatform\ContentType;
use App\Rules\ContentTypeMatchesPlatform;
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
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*.social_account_id' => [
                'required',
                'uuid',
                Rule::exists('social_accounts', 'id')
                    ->where('workspace_id', $this->user()->currentWorkspace->id)
                    ->where('is_active', true),
            ],
            'platforms.*.content_type' => [
                'required',
                'string',
                Rule::in(array_column(ContentType::cases(), 'value')),
                new ContentTypeMatchesPlatform,
            ],
            'platforms.*.content' => ['nullable', 'string', 'max:63206'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'status' => ['nullable', 'string', Rule::in(array_column(Status::cases(), 'value'))],
        ];
    }
}
