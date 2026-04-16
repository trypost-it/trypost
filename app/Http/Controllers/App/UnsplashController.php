<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Requests\App\Asset\SearchRequest;
use App\Services\UnsplashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnsplashController extends Controller
{
    public function search(SearchRequest $request, UnsplashService $unsplash): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $validated = $request->validated();

        $results = $unsplash->search(
            query: data_get($validated, 'query'),
            page: (int) data_get($validated, 'page', 1),
        );

        return response()->json($results);
    }

    public function trending(Request $request, UnsplashService $unsplash): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $photos = $unsplash->trending(
            page: $request->integer('page', 1),
        );

        return response()->json(['results' => $photos]);
    }
}
