<?php

namespace App\Presentation\Adapters;

use App\Exceptions\AssetResolutionException;
use App\Presentation\Contracts\FontAdapter;

final class SelfHostedFontAdapter implements FontAdapter
{
    public function key(): string
    {
        return 'self-hosted';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function digest(): string
    {
        return hash('sha256', self::class.'@'.$this->version());
    }

    public function compile(string $family, array $fontAssets): string
    {
        if ($fontAssets === []) {
            throw new AssetResolutionException('The self-hosted font adapter requires at least one WOFF2 asset.');
        }

        $fontName = (string) $fontAssets[0]['family'];
        $rules = [];
        foreach ($fontAssets as $fontAsset) {
            if ($fontAsset['family'] !== $fontName) {
                throw new AssetResolutionException('Every self-hosted font asset must use the same family name.');
            }
            $escapedFamily = addcslashes($fontName, '\\"');
            $rules[] = '@font-face{font-family:"'.$escapedFamily.'";src:url("'.$fontAsset['path'].'") format("woff2");font-style:'.$fontAsset['style'].';font-weight:'.$fontAsset['weight'].';font-display:swap}';
        }

        $fallback = match ($family) {
            'serif' => 'ui-serif,Georgia,serif',
            'mono' => 'ui-monospace,SFMono-Regular,monospace',
            default => 'ui-sans-serif,system-ui,sans-serif',
        };
        $escapedFamily = addcslashes($fontName, '\\"');
        $rules[] = ':root{--m-font-family:"'.$escapedFamily.'",'.$fallback.'}.m-presentation{font-family:var(--m-font-family)}';

        return implode('', $rules);
    }
}
