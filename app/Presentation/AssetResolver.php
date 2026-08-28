<?php

namespace App\Presentation;

use App\Exceptions\AssetResolutionException;

final class AssetResolver
{
    /**
     * @param  array<int,array<string,mixed>>  $components
     * @param  array<int,array<string,mixed>>  $assets
     * @param  array<int,array<string,mixed>>  $fontAssets
     * @return array{images:array<string,array<string,mixed>>,required_assets:array<int,array<string,mixed>>}
     */
    public function resolve(array $components, array $assets, array $fontAssets): array
    {
        $available = [];
        foreach ($assets as $asset) {
            $available[(string) $asset['asset_ref']] = $asset;
        }

        $images = [];
        foreach ($components as $component) {
            foreach ($component['slots'] as $slot) {
                if (($slot['role'] ?? null) !== 'Image') {
                    continue;
                }
                $assetRef = (string) $slot['content']['asset_ref'];
                if (! isset($available[$assetRef])) {
                    throw new AssetResolutionException("Image asset {$assetRef} is not resolved.");
                }
                $images[$assetRef] = $available[$assetRef];
            }
        }

        $unused = array_diff(array_keys($available), array_keys($images));
        if ($unused !== []) {
            sort($unused, SORT_STRING);

            throw new AssetResolutionException('Unused image assets are not allowed: '.implode(', ', $unused).'.');
        }

        $requiredAssets = [];
        foreach ([...array_values($images), ...$fontAssets] as $asset) {
            $requiredAssets[] = [
                'asset_ref' => $asset['asset_ref'],
                'path' => $asset['path'],
                'mime' => $asset['mime'],
                'sha256' => $asset['sha256'],
            ];
        }

        return ['images' => $images, 'required_assets' => $requiredAssets];
    }
}
