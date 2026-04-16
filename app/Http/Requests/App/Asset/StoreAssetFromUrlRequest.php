<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Asset;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetFromUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'regex:/^https:\/\/(images\.unsplash\.com|media[0-9]*\.giphy\.com)\//'],
            'filename' => ['required', 'string', 'max:255'],
            'download_location' => ['nullable', 'url', 'regex:/^https:\/\/api\.unsplash\.com\//'],
        ];
    }
}
