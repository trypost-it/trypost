<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DuplicateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'targets' => ['required', 'array', 'max:50'],
            'targets.*.model' => ['required', 'string', Rule::in(['postPlatform', 'workspace', 'user'])],
            'targets.*.model_id' => ['required', 'uuid'],
        ];
    }
}
