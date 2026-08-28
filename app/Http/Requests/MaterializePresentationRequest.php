<?php

namespace App\Http\Requests;

final class MaterializePresentationRequest extends ContractRequest
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'contract_version' => ['required', 'string', 'in:1.0'],
            'component_ast' => ['required', 'array'],
            'adapter_profile' => ['required', 'array'],
            'assets' => ['present', 'array', 'max:50'],
            'font_assets' => ['present', 'array', 'max:20'],
            'font_asset_set' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
