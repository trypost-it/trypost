<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\SocialAccount\ToggleSocialAccount;
use App\Http\Resources\Api\SocialAccountResource;
use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SocialAccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $accounts = $request->workspace->socialAccounts()->orderBy('platform')->get();

        return SocialAccountResource::collection($accounts);
    }

    public function toggle(Request $request, SocialAccount $account): SocialAccountResource|JsonResponse
    {
        if ($account->workspace_id !== $request->workspace->id) {
            return response()->json(
                ['message' => 'Account not found.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        ToggleSocialAccount::execute($account);

        return new SocialAccountResource($account);
    }
}
