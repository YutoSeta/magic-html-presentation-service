<?php

namespace App\Http\Controllers;

use App\Http\Resources\CapabilityResource;
use App\Presentation\AdapterRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CapabilityController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, AdapterRegistry $adapters): JsonResource
    {
        return new CapabilityResource($adapters);
    }

    public function verify(AdapterRegistry $adapters): JsonResponse
    {
        $checks = [
            'contract_version_supported' => data_get($adapters->manifest(), 'contract_version') === '1.0',
            'adapter_assets' => $adapters->ready(),
        ];
        $ready = ! in_array(false, $checks, true);

        return response()->json([
            'service' => 'magic-html-presentation-service',
            'tier' => 0,
            'status' => $ready ? 'ok' : 'degraded',
            'contract_version' => '1.0',
            'checks' => $checks,
        ], $ready ? 200 : 503);
    }
}
