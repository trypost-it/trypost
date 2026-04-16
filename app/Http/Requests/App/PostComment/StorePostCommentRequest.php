<?php

declare(strict_types=1);

namespace App\Http\Requests\App\PostComment;

use Illuminate\Foundation\Http\FormRequest;

class StorePostCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'uuid', 'exists:post_comments,id'],
        ];
    }
}
