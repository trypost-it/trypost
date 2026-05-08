<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Ai;

use App\Enums\PostPlatform\ContentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartPostCreationRequest extends FormRequest
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
            'format' => [
                'required',
                'string',
                Rule::in(array_map(fn (ContentType $t) => $t->value, ContentType::aiSupported())),
            ],
            'social_account_id' => ['nullable', 'uuid'],
            'image_count' => ['nullable', 'integer', 'min:0', 'max:10'],
            'prompt' => ['required', 'string', 'max:2000'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
