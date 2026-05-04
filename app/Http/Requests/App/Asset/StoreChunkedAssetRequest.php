<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Asset;

use App\Enums\Media\Type as MediaType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a chunked upload request. The chunk metadata (offset / total
 * size / filename) is encoded in the `Content-Range` and `X-File-Name`
 * headers, not in the body, so we lift it into the request bag via
 * `prepareForValidation` and then run standard rules against it.
 */
class StoreChunkedAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        preg_match(
            '/bytes (\d+)-(\d+)\/(\d+)/',
            (string) $this->header('Content-Range'),
            $matches,
        );

        $this->merge([
            'range_start' => isset($matches[1]) ? (int) $matches[1] : null,
            'range_end' => isset($matches[2]) ? (int) $matches[2] : null,
            'total_size' => isset($matches[3]) ? (int) $matches[3] : null,
            'file_name' => $this->header('X-File-Name', 'upload'),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'range_start' => ['required', 'integer', 'min:0'],
            'range_end' => ['required', 'integer', 'gte:range_start'],
            'total_size' => ['required', 'integer', 'min:1', 'max:'.MediaType::Video->maxSizeInBytes()],
            'file_name' => ['required', 'string', 'regex:/\.(jpe?g|png|gif|webp|mp4)$/i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'range_start.required' => 'Invalid Content-Range header',
            'range_end.required' => 'Invalid Content-Range header',
            'total_size.required' => 'Invalid Content-Range header',
            'total_size.max' => 'File size exceeds the maximum allowed ('.MediaType::Video->maxSizeInMb().' MB).',
            'file_name.regex' => 'File type not supported.',
        ];
    }
}
