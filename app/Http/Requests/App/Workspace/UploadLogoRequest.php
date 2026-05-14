<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Workspace;

use Illuminate\Foundation\Http\FormRequest;

class UploadLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'max:2048'],
        ];
    }
}
