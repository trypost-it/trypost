<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Support\WorkspacePresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PresenceController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return response()->json(['ok' => false], Response::HTTP_NO_CONTENT);
        }

        WorkspacePresence::markOnline($workspace->id, $request->user()->id);

        return response()->json(['ok' => true]);
    }
}
