<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Hashtag;

use Illuminate\Foundation\Http\FormRequest;

class StoreHashtagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'hashtags' => ['required', 'string'],
        ];
    }
}
