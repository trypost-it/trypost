<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Asset;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'media' => ['required', 'file', 'max:1048576', 'mimetypes:image/jpeg,image/png,image/gif,image/webp,video/mp4'],
        ];
    }
}
