<?php

declare(strict_types=1);

namespace App\Http\Requests\App\PostComment;

use Illuminate\Foundation\Http\FormRequest;

class ReactPostCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'emoji' => ['required', 'string', 'max:10'],
        ];
    }
}
