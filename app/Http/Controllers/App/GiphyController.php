<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Services\GiphyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiphyController extends Controller
{
    public function search(Request $request, GiphyService $giphy): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $request->validate([
            'query' => ['required', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $results = $giphy->search(
            query: $request->input('query'),
            page: $request->integer('page', 1),
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
