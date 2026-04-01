<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaRequest extends FormRequest
{
    protected array $allowedModels = [
        'postPlatform',
        'workspace',
        'user',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = $this->isVideoUpload() ? 1048576 : 10240; // Videos: 1GB, Images: 10MB

        return [
            'media' => [
                'required',
                'file',
                "max:{$maxSize}",
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,video/mp4',
            ],
            'model' => [
                'required',
                'string',
                Rule::in($this->allowedModels),
            ],
            'model_id' => [
                'required',
                'string',
            ],
            'collection' => [
                'sometimes',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'media.required' => 'The file is required.',
            'media.max' => $this->isVideoUpload() ? 'Videos must be at most 1GB.' : 'Images must be at most 10MB.',
            'media.mimetypes' => 'File type not supported.',
            'model.required' => 'The model is required.',
            'model.in' => 'Invalid model type.',
            'model_id.required' => 'The model ID is required.',
        ];
    }

    private function isVideoUpload(): bool
    {
        return str_starts_with($this->file('media')?->getMimeType() ?? '', 'video/');
    }
}
