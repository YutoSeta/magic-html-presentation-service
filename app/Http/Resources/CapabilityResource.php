<?php

namespace App\Http\Resources;

use App\Presentation\AdapterRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CapabilityResource extends JsonResource
{
    public function __construct(private readonly AdapterRegistry $adapters)
    {
        parent::__construct([]);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => 'magic-html-presentation-service',
            'tier' => 0,
            'contract_version' => '1.0',
            'documentation' => url('/api/__verify'),
            'health' => url('/up'),
            'operations' => [
                'POST /api/v1/presentations/materialize',
                'POST /api/v1/presentations/font-assets/check',
            ],
            'execution' => [
                'side_effects' => false,
                'deterministic' => true,
                'remote_asset_fetching' => false,
                'request_supplied_css_or_templates' => false,
                'licensed_font_policy' => 'google-fonts-self-host-v1',
                'font_catalog_fetching' => false,
            ],
            'default_profile' => $this->adapters->defaultProfile(),
            'adapters' => $this->adapters->manifest(),
        ];
    }
}
