<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InvalidPresentationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\FontPolicyCheckRequest;
use App\Http\Resources\FontPolicyResource;
use App\Presentation\LicensedFontPolicy;
use App\Support\Problem;
use Illuminate\Http\JsonResponse;

final class FontPolicyController extends Controller
{
    public function __invoke(FontPolicyCheckRequest $request, LicensedFontPolicy $policy): JsonResponse
    {
        $input = $request->validated();

        try {
            $result = $policy->check($input['font_asset_set'], $input['font_assets']);
        } catch (InvalidPresentationException $exception) {
            return Problem::response($request, 422, 'invalid_font_asset_set', 'The licensed font asset set is invalid.', $exception->errors);
        }

        return (new FontPolicyResource($result))->response()->withHeaders([
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
