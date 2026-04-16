<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Requests\App\Asset\SearchRequest;
use App\Services\GiphyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiphyController extends Controller
{
    public function search(SearchRequest $request, GiphyService $giphy): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $validated = $request->validated();

        $results = $giphy->search(
            query: data_get($validated, 'query'),
            page: (int) data_get($validated, 'page', 1),
        );

        return response()->json($results);
    }

    public function trending(Request $request, GiphyService $giphy): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $photos = $giphy->trending(
            page: $request->integer('page', 1),
        );

        return response()->json(['results' => $photos]);
    }
}
