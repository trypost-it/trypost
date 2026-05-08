<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Requests\App\Ai\StartPostCreationRequest;
use App\Jobs\Ai\StreamPostCreation;
use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PostAiCreateController extends Controller
{
    public function start(StartPostCreationRequest $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $gate = Gate::inspect('useAi', $workspace->account);
        if ($gate->denied()) {
            return response()->json(['message' => $gate->message()], Response::HTTP_PAYMENT_REQUIRED);
        }

        $socialAccountId = $request->input('social_account_id');

        if ($socialAccountId) {
            $owned = SocialAccount::where('id', $socialAccountId)
                ->where('workspace_id', $workspace->id)
                ->exists();

            if (! $owned) {
                abort(Response::HTTP_FORBIDDEN);
            }
        }

        $creationId = (string) Str::uuid();

        StreamPostCreation::dispatch(
            userId: $request->user()->id,
            creationId: $creationId,
            workspaceId: $workspace->id,
            format: $request->string('format')->toString(),
            socialAccountId: $socialAccountId,
            imageCount: (int) $request->input('image_count', 0),
            prompt: $request->string('prompt')->toString(),
            date: $request->input('date'),
        );

        return response()->json([
            'creation_id' => $creationId,
            'channel' => "users.{$request->user()->id}.ai-creation.{$creationId}",
        ], Response::HTTP_ACCEPTED);
    }
}
