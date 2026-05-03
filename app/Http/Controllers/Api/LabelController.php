<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Label\CreateLabel;
use App\Actions\Label\DeleteLabel;
use App\Actions\Label\UpdateLabel;
use App\Http\Requests\Api\Label\StoreLabelRequest;
use App\Http\Requests\Api\Label\UpdateLabelRequest;
use App\Http\Resources\Api\LabelResource;
use App\Models\WorkspaceLabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class LabelController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $labels = $request->user()->currentWorkspace->labels()->latest()->get();

        return LabelResource::collection($labels);
    }

    public function store(StoreLabelRequest $request): JsonResponse
    {
        $label = CreateLabel::execute($request->user()->currentWorkspace, $request->validated());

        return (new LabelResource($label))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateLabelRequest $request, WorkspaceLabel $label): LabelResource
    {
        if ($label->workspace_id !== $request->user()->currentWorkspace->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $label = UpdateLabel::execute($label, $request->validated());

        return new LabelResource($label);
    }

    public function destroy(Request $request, WorkspaceLabel $label): JsonResponse
    {
        if ($label->workspace_id !== $request->user()->currentWorkspace->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        DeleteLabel::execute($label);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
