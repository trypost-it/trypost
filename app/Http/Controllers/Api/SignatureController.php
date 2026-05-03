<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Signature\CreateSignature;
use App\Actions\Signature\DeleteSignature;
use App\Actions\Signature\UpdateSignature;
use App\Http\Requests\Api\Signature\StoreSignatureRequest;
use App\Http\Requests\Api\Signature\UpdateSignatureRequest;
use App\Http\Resources\Api\SignatureResource;
use App\Models\WorkspaceSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SignatureController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $signatures = $request->user()->currentWorkspace->signatures()->latest()->get();

        return SignatureResource::collection($signatures);
    }

    public function store(StoreSignatureRequest $request): JsonResponse
    {
        $signature = CreateSignature::execute($request->user()->currentWorkspace, $request->validated());

        return (new SignatureResource($signature))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateSignatureRequest $request, WorkspaceSignature $signature): SignatureResource
    {
        if ($signature->workspace_id !== $request->user()->currentWorkspace->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $signature = UpdateSignature::execute($signature, $request->validated());

        return new SignatureResource($signature);
    }

    public function destroy(Request $request, WorkspaceSignature $signature): JsonResponse
    {
        if ($signature->workspace_id !== $request->user()->currentWorkspace->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        DeleteSignature::execute($signature);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
