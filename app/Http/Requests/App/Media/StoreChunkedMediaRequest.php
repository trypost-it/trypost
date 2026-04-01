<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChunkedMediaRequest extends FormRequest
{
    private const CONTENT_RANGE_PATTERN = '/bytes (\d+)-(\d+)\/(\d+)/';

    protected array $allowedModels = [
        'postPlatform',
        'workspace',
        'user',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'model' => $this->header('X-Model'),
            'model_id' => $this->header('X-Model-Id'),
            'collection' => $this->header('X-Collection', 'default'),
            'file_name' => $this->header('X-File-Name', 'upload'),
            'content_range' => $this->header('Content-Range'),
        ]);
    }

    public function rules(): array
    {
        return [
            'content_range' => ['required', 'string', 'regex:'.self::CONTENT_RANGE_PATTERN],
            'model' => ['required', 'string', Rule::in($this->allowedModels)],
            'model_id' => ['required', 'string'],
            'collection' => ['sometimes', 'string', 'max:255'],
            'file_name' => [
                'required',
                'string',
                'max:255',
                'regex:/\.(jpe?g|png|gif|webp|mp4)$/i',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file_name.regex' => 'File type not supported. Allowed: JPEG, PNG, GIF, WebP, MP4.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if ($validator->errors()->has('content_range')) {
                    return;
                }

                if ($this->totalSize() > 1073741824) { // 1GB
                    $validator->errors()->add('content_range', 'File size exceeds the maximum allowed (1GB).');
                }
            },
        ];
    }

    public function rangeStart(): int
    {
        return (int) $this->parsedRange()[1];
    }

    public function rangeEnd(): int
    {
        return (int) $this->parsedRange()[2];
    }

    public function totalSize(): int
    {
        return (int) $this->parsedRange()[3];
    }

    public function isLastChunk(): bool
    {
        return ($this->rangeEnd() + 1) >= $this->totalSize();
    }

    public function isFirstChunk(): bool
    {
        return $this->rangeStart() === 0;
    }

    public function progress(): int
    {
        return (int) round(($this->rangeEnd() + 1) / $this->totalSize() * 100);
    }

    public function chunkIdentifier(): string
    {
        return md5($this->input('file_name').$this->totalSize());
    }

    /**
     * @return array<int, string>
     */
    private function parsedRange(): array
    {
        preg_match(self::CONTENT_RANGE_PATTERN, $this->input('content_range'), $matches);

        return $matches;
    }
}
