<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Post;

use Illuminate\Foundation\Http\FormRequest;

class AttachMediaRequest extends FormRequest
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
            'urls' => ['required', 'array', 'min:1', 'max:10'],
            'urls.*' => ['url:http,https', 'active_url'],
        ];
    }
}
