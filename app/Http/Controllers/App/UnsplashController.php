<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Services\UnsplashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnsplashController extends Controller
{
    public function search(Request $request, UnsplashService $unsplash): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('manageAccounts', $workspace);

        $request->validate([
            'query' => ['required', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $results = $unsplash->search(
            query: $request->input('query'),
            page: $request->integer('page', 1),
        );

        return response()->json($results);
    }
}
