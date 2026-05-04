<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Asset;

use App\Enums\Media\Type as MediaType;
use Illuminate\Foundation\Http\FormRequest;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedMimes = [...MediaType::Image->allowedMimeTypes(), ...MediaType::Video->allowedMimeTypes()];

        return [
            // Use the largest per-type cap as the upper bound; per-type
            // enforcement happens after the upload via the Media model.
            'media' => [
                'required',
                'file',
                'max:'.MediaType::Video->maxSizeInKb(),
                'mimetypes:'.implode(',', $allowedMimes),
            ],
            'meta' => ['sometimes', 'array'],
            'meta.width' => ['sometimes', 'integer', 'min:1'],
            'meta.height' => ['sometimes', 'integer', 'min:1'],
            'meta.duration' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
