<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class FontPolicyResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'contract_version' => $this->resource['contract_version'],
            'set_id' => $this->resource['set_id'],
            'provider' => $this->resource['provider'],
            'family' => $this->resource['family'],
            'policy_version' => $this->resource['policy_version'],
            'eligible_for_materialization' => $this->resource['eligible_for_materialization'],
            'requirements' => $this->resource['requirements'],
            'warnings' => $this->resource['warnings'],
            'digest' => $this->resource['digest'],
        ];
    }
}
