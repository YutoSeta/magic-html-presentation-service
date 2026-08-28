<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class FontPolicyCheckRequest extends ContractRequest
{
    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'contract_version' => ['required', 'string', 'in:1.0'],
            'font_asset_set' => ['required', 'array'],
            'font_assets' => ['required', 'array', 'min:1', 'max:20'],
        ];
    }
}
