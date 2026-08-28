<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AssetResolutionException;
use App\Exceptions\InvalidPresentationException;
use App\Exceptions\UnsupportedAdapterException;
use App\Http\Controllers\Controller;
use App\Http\Requests\MaterializePresentationRequest;
use App\Http\Resources\PresentationResource;
use App\Presentation\PresentationMaterializer;
use App\Support\Problem;
use Illuminate\Http\JsonResponse;
use Throwable;

final class PresentationController extends Controller
{
    public function store(
        MaterializePresentationRequest $request,
        PresentationMaterializer $materializer,
    ): JsonResponse {
        try {
            $result = $materializer->materialize($request->validated());
        } catch (InvalidPresentationException $exception) {
            return Problem::response($request, 422, 'invalid_component_ast', 'The presentation input is invalid.', $exception->errors);
        } catch (UnsupportedAdapterException $exception) {
            return Problem::response($request, 422, 'unsupported_adapter', $exception->getMessage());
        } catch (AssetResolutionException $exception) {
            return Problem::response($request, 422, 'asset_resolution_failed', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return Problem::response($request, 503, 'materialization_failed', 'The installed presentation adapter assets are unavailable.');
        }

        return (new PresentationResource($result))->response()->withHeaders([
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
