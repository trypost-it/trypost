<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Post;

use App\Enums\Post\Status;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Rules\ContentFitsPlatformLimits;
use App\Rules\ContentTypeMatchesPostPlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $enforcesPlatformLimits = in_array(
            $this->input('status'),
            [Status::Scheduled->value, Status::Publishing->value],
            true,
        );

        return [
            'status' => ['required', 'string', Rule::in([Status::Draft->value, Status::Scheduled->value, Status::Publishing->value])],
            'content' => [
                'nullable',
                'string',
                'max:63206',
                Rule::when(
                    $enforcesPlatformLimits,
                    [new ContentFitsPlatformLimits($this->resolveSelectedPlatforms())]
                ),
            ],
            'media' => ['sometimes', 'array'],
            'platforms' => ['sometimes', 'array'],
            'platforms.*.id' => ['required', 'uuid', Rule::exists('post_platforms', 'id')->where('post_id', $this->route('post') instanceof Post ? $this->route('post')->id : $this->route('post'))],
            'platforms.*.content_type' => [
                'sometimes',
                'string',
                Rule::in(array_column(ContentType::cases(), 'value')),
                new ContentTypeMatchesPostPlatform,
            ],
            'platforms.*.meta' => ['nullable', 'array'],
            'scheduled_at' => [
                'nullable',
                'date',
                Rule::when(
                    $this->input('status') === Status::Scheduled->value,
                    ['after:now']
                ),
            ],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['uuid', Rule::exists('workspace_labels', 'id')->where('workspace_id', $this->user()->currentWorkspace->id)],
        ];
    }

    /**
     * @return Collection<int|string, Platform>
     */
    private function resolveSelectedPlatforms(): Collection
    {
        $ids = collect($this->input('platforms', []))->pluck('id')->filter()->all();
        if (empty($ids)) {
            return collect();
        }

        $post = $this->route('post');
        $postId = $post instanceof Post ? $post->id : $post;

        return PostPlatform::query()
            ->where('post_id', $postId)
            ->whereIn('id', $ids)
            ->pluck('platform', 'id');
    }
}
