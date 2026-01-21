<?php

namespace App\Http\Requests;

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
        return [
            'media' => [
                'required',
                'file',
                'max:512000', // 500MB
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,video/webm,application/pdf',
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
            'media.max' => 'The file must be at most 500MB.',
            'media.mimetypes' => 'File type not supported.',
            'model.required' => 'The model is required.',
            'model.in' => 'Invalid model type.',
            'model_id.required' => 'The model ID is required.',
        ];
    }
}
