<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\Media\Type as MediaType;
use Illuminate\Foundation\Http\FormRequest;

class StoreUploadRequest extends FormRequest
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
        $allowed = implode(',', array_merge(
            MediaType::Image->allowedMimeTypes(),
            MediaType::Video->allowedMimeTypes(),
        ));

        return [
            'media' => [
                'required',
                'file',
                'max:51200',
                "mimetypes:{$allowed}",
            ],
        ];
    }
}
