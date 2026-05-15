<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Post;

use App\Enums\Post\Status;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Rules\ContentFitsPlatformLimits;
use App\Rules\ContentTypeCompatibleWithMedia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $enforcesMediaCompatibility = in_array(
            $this->input('status'),
            [Status::Scheduled->value, Status::Publishing->value],
            true,
        );

        return [
            'status' => ['required', 'string', Rule::in([Status::Draft->value, Status::Scheduled->value, Status::Publishing->value])],
            'content' => [
                'nullable',
                'string',
                'max:10000',
                Rule::when(
                    $enforcesMediaCompatibility,
                    [new ContentFitsPlatformLimits($this->resolveSelectedPlatforms())]
                ),
            ],
            'media' => ['sometimes', 'array'],
            'media.*.id' => ['required', 'string'],
            'media.*.path' => ['required', 'string', 'max:500'],
            'media.*.url' => ['required', 'string', 'max:2048'],
            'media.*.type' => ['sometimes', 'nullable', 'string', 'max:32'],
            'media.*.mime_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'media.*.original_filename' => ['sometimes', 'nullable', 'string', 'max:500'],
            'media.*.size' => ['sometimes', 'nullable', 'integer'],
            'media.*.meta' => ['sometimes', 'nullable', 'array'],
            'scheduled_at' => [
                'sometimes',
                'nullable',
                'date',
                Rule::when(
                    $this->input('status') === Status::Scheduled->value,
                    ['after:now']
                ),
            ],
            'platforms' => ['sometimes', 'array'],
            'platforms.*.id' => ['required', 'uuid', Rule::exists('post_platforms', 'id')->where('post_id', $this->route('post')->id)],
            'platforms.*.content_type' => [
                $enforcesMediaCompatibility ? 'required' : 'sometimes',
                'string',
                Rule::in(array_column(ContentType::cases(), 'value')),
                Rule::when($enforcesMediaCompatibility, [new ContentTypeCompatibleWithMedia]),
            ],
            'platforms.*.meta' => ['nullable', 'array'],
            'platforms.*.meta.aspect_ratio' => ['sometimes', 'nullable', 'string', Rule::in(['1:1', '4:5', '16:9', 'original'])],
            'platforms.*.meta.privacy_level' => ['sometimes', 'nullable', 'string', Rule::in(['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY'])],
            'platforms.*.meta.auto_add_music' => ['sometimes', 'boolean'],
            'platforms.*.meta.allow_comments' => ['sometimes', 'boolean'],
            'platforms.*.meta.allow_duet' => ['sometimes', 'boolean'],
            'platforms.*.meta.allow_stitch' => ['sometimes', 'boolean'],
            'platforms.*.meta.is_aigc' => ['sometimes', 'boolean'],
            'platforms.*.meta.disclose' => ['sometimes', 'boolean'],
            'platforms.*.meta.brand_content_toggle' => ['sometimes', 'boolean'],
            'platforms.*.meta.brand_organic_toggle' => ['sometimes', 'boolean'],
            'platforms.*.meta.board_id' => ['sometimes', 'nullable', 'string'],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['uuid', Rule::exists('workspace_labels', 'id')->where('workspace_id', $this->user()->currentWorkspace->id)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->isPublishingOrScheduling()) {
                return;
            }

            $platforms = $this->input('platforms', []);
            $ids = collect($platforms)->pluck('id')->filter()->all();

            $platformsById = $this->route('post')
                ->postPlatforms()
                ->whereIn('id', $ids)
                ->pluck('platform', 'id');

            foreach ($platforms as $i => $platform) {
                $platformEnum = $platformsById[data_get($platform, 'id')] ?? null;
                if ($platformEnum === Platform::TikTok
                    && blank(data_get($platform, 'meta.privacy_level'))) {
                    $validator->errors()->add(
                        "platforms.{$i}.meta.privacy_level",
                        trans('posts.form.tiktok.privacy_required'),
                    );
                }

                if ($platformEnum === Platform::Pinterest
                    && blank(data_get($platform, 'meta.board_id'))) {
                    $validator->errors()->add(
                        "platforms.{$i}.meta.board_id",
                        trans('posts.form.pinterest.board_required'),
                    );
                }
            }
        });
    }

    private function isPublishingOrScheduling(): bool
    {
        return in_array(
            $this->input('status'),
            [Status::Scheduled->value, Status::Publishing->value],
            true,
        );
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

        return $this->route('post')
            ->postPlatforms()
            ->whereIn('id', $ids)
            ->pluck('platform', 'id');
    }
}
