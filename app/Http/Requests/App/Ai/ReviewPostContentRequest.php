<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Ai;

use Illuminate\Foundation\Http\FormRequest;

class ReviewPostContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:10000'],
        ];
    }
}
