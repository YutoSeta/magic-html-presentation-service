<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PresentationResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'contract_version' => $this->resource['contract_version'],
            'surface_id' => $this->resource['surface_id'],
            'profile_id' => $this->resource['profile_id'],
            'catalog_locks' => $this->resource['catalog_locks'],
            'html' => $this->resource['html'],
            'css' => $this->resource['css'],
            'required_assets' => $this->resource['required_assets'],
            'source_map' => $this->resource['source_map'],
            'digest' => $this->resource['digest'],
        ];
    }
}
