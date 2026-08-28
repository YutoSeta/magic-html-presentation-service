<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Exceptions\InvalidPresentationException;
use App\Support\CanonicalJson;

final class LicensedFontPolicy
{
    public const VERSION = 'google-fonts-self-host-v1';

    /** @var array<string,string> */
    private const LICENSE_PATH_PREFIXES = [
        'OFL-1.1' => 'ofl/',
        'Apache-2.0' => 'apache/',
        'Ubuntu-font-1.0' => 'ufl/',
    ];

    /** @var array<string,array<int,string>> */
    private array $errors = [];

    /**
     * @param  array<string,mixed>  $fontAssetSet
     * @param  array<int,array<string,mixed>>  $fontAssets
     * @return array<string,mixed>
     */
    public function check(array $fontAssetSet, array $fontAssets): array
    {
        $this->errors = [];
        $this->validateAssetSet($fontAssetSet);
        $this->validateFontAssets($fontAssets);
        $this->validateRelationship($fontAssetSet, $fontAssets);

        if ($this->errors !== []) {
            ksort($this->errors, SORT_STRING);

            throw new InvalidPresentationException($this->errors);
        }

        $transformation = $fontAssetSet['transformation'];
        $warnings = [];
        if ($transformation['kind'] === 'subset') {
            $warnings[] = 'font-subset-is-modified';
        }
        if ($transformation['renamed_family'] !== null) {
            $warnings[] = 'font-family-renamed-for-reserved-name';
        }

        $result = [
            'contract_version' => '1.0',
            'set_id' => $fontAssetSet['set_id'],
            'provider' => 'google-fonts',
            'family' => $transformation['renamed_family'] ?? $fontAssetSet['family'],
            'policy_version' => self::VERSION,
            'eligible_for_materialization' => true,
            'requirements' => [
                'retain-license-text',
                'retain-copyright-notice',
                'serve-declared-woff2-only',
            ],
            'warnings' => $warnings,
        ];

        return [
            ...$result,
            'digest' => hash('sha256', CanonicalJson::encode($result)),
        ];
    }

    /** @param array<string,mixed> $set */
    private function validateAssetSet(array $set): void
    {
        $keys = ['contract_version', 'set_id', 'provider', 'family', 'upstream', 'license', 'transformation', 'asset_refs'];
        $this->assertKeys($set, $keys, $keys, 'font_asset_set');
        if (($set['contract_version'] ?? null) !== '1.0') {
            $this->add('font_asset_set.contract_version', 'The font asset set contract version must be 1.0.');
        }
        if (($set['provider'] ?? null) !== 'google-fonts') {
            $this->add('font_asset_set.provider', 'The licensed font provider must be google-fonts.');
        }
        $this->assertIdentifier($set['set_id'] ?? null, 'font_asset_set.set_id');
        $this->assertFamily($set['family'] ?? null, 'font_asset_set.family');
        $this->validateUpstream($set['upstream'] ?? null);
        $this->validateLicense($set['license'] ?? null);
        $this->validateTransformation($set['transformation'] ?? null);

        $assetRefs = $set['asset_refs'] ?? null;
        if (! is_array($assetRefs) || ! array_is_list($assetRefs) || $assetRefs === [] || count($assetRefs) > 20) {
            $this->add('font_asset_set.asset_refs', 'The font asset set must govern between 1 and 20 asset references.');

            return;
        }
        foreach ($assetRefs as $index => $assetRef) {
            $this->assertIdentifier($assetRef, "font_asset_set.asset_refs.{$index}");
        }
        if ($this->hasDuplicateStrings($assetRefs)) {
            $this->add('font_asset_set.asset_refs', 'Font asset references must be unique.');
        }
    }

    private function validateUpstream(mixed $upstream): void
    {
        if (! is_array($upstream) || array_is_list($upstream)) {
            $this->add('font_asset_set.upstream', 'The upstream provenance must be an object.');

            return;
        }
        $keys = ['catalog', 'revision', 'family_path', 'version', 'source_digest'];
        $this->assertKeys($upstream, $keys, $keys, 'font_asset_set.upstream');
        if (($upstream['catalog'] ?? null) !== 'google/fonts') {
            $this->add('font_asset_set.upstream.catalog', 'The upstream catalog must be google/fonts.');
        }
        $this->assertPattern($upstream['revision'] ?? null, '/^[a-f0-9]{40}$/D', 'font_asset_set.upstream.revision', 'The upstream revision must be a full lowercase Git commit hash.');
        $this->assertPattern($upstream['family_path'] ?? null, '/^(?:ofl|apache|ufl)\/[a-z0-9]+$/D', 'font_asset_set.upstream.family_path', 'The upstream family path is invalid.');
        if (! is_string($upstream['version'] ?? null) || $upstream['version'] === '' || mb_strlen($upstream['version']) > 100) {
            $this->add('font_asset_set.upstream.version', 'The upstream font version must be between 1 and 100 characters.');
        }
        $this->assertSha256($upstream['source_digest'] ?? null, 'font_asset_set.upstream.source_digest');
    }

    private function validateLicense(mixed $license): void
    {
        if (! is_array($license) || array_is_list($license)) {
            $this->add('font_asset_set.license', 'The license evidence must be an object.');

            return;
        }
        $keys = ['spdx', 'copyright_notice', 'license_text_path', 'license_text_sha256', 'reserved_font_names'];
        $this->assertKeys($license, $keys, $keys, 'font_asset_set.license');
        if (! array_key_exists((string) ($license['spdx'] ?? ''), self::LICENSE_PATH_PREFIXES)) {
            $this->add('font_asset_set.license.spdx', 'The Google Fonts license identifier is not supported.');
        }
        if (! is_string($license['copyright_notice'] ?? null) || trim($license['copyright_notice']) === '' || mb_strlen($license['copyright_notice']) > 2000) {
            $this->add('font_asset_set.license.copyright_notice', 'A copyright notice between 1 and 2000 characters is required.');
        }
        $this->assertPattern($license['license_text_path'] ?? null, '/^licenses\/(?:[A-Za-z0-9._-]+\/)*[A-Za-z0-9][A-Za-z0-9._-]{0,199}$/D', 'font_asset_set.license.license_text_path', 'The license text path must be a relative licenses/ path.');
        $this->assertSha256($license['license_text_sha256'] ?? null, 'font_asset_set.license.license_text_sha256');

        $reservedNames = $license['reserved_font_names'] ?? null;
        if (! is_array($reservedNames) || ! array_is_list($reservedNames) || count($reservedNames) > 20) {
            $this->add('font_asset_set.license.reserved_font_names', 'Reserved Font Names must be a list of at most 20 names.');

            return;
        }
        foreach ($reservedNames as $index => $name) {
            $this->assertFamily($name, "font_asset_set.license.reserved_font_names.{$index}");
        }
        if ($this->hasDuplicateStrings($reservedNames)) {
            $this->add('font_asset_set.license.reserved_font_names', 'Reserved Font Names must be unique.');
        }
    }

    private function validateTransformation(mixed $transformation): void
    {
        if (! is_array($transformation) || array_is_list($transformation)) {
            $this->add('font_asset_set.transformation', 'The transformation record must be an object.');

            return;
        }
        $keys = ['kind', 'metadata_preserved', 'renamed_family'];
        $this->assertKeys($transformation, $keys, $keys, 'font_asset_set.transformation');
        if (! in_array($transformation['kind'] ?? null, ['none', 'woff2-compression', 'subset'], true)) {
            $this->add('font_asset_set.transformation.kind', 'The font transformation kind is not supported.');
        }
        if (($transformation['metadata_preserved'] ?? null) !== true) {
            $this->add('font_asset_set.transformation.metadata_preserved', 'Self-hosted font metadata must be preserved.');
        }
        if (($transformation['renamed_family'] ?? null) !== null) {
            $this->assertFamily($transformation['renamed_family'] ?? null, 'font_asset_set.transformation.renamed_family');
        }
    }

    /** @param array<int,array<string,mixed>> $fontAssets */
    private function validateFontAssets(array $fontAssets): void
    {
        if (! array_is_list($fontAssets) || $fontAssets === [] || count($fontAssets) > 20) {
            $this->add('font_assets', 'The licensed font adapter requires between 1 and 20 WOFF2 assets.');

            return;
        }

        $refs = [];
        foreach ($fontAssets as $index => $asset) {
            $path = "font_assets.{$index}";
            if (! is_array($asset) || array_is_list($asset)) {
                $this->add($path, 'Every font asset must be an object.');

                continue;
            }
            $keys = ['asset_ref', 'path', 'mime', 'sha256', 'family', 'weight', 'style'];
            $this->assertKeys($asset, $keys, $keys, $path);
            $this->assertIdentifier($asset['asset_ref'] ?? null, $path.'.asset_ref');
            $this->assertPattern($asset['path'] ?? null, '/^assets\/(?:[A-Za-z0-9._-]+\/)*[A-Za-z0-9][A-Za-z0-9._-]{0,199}$/D', $path.'.path', 'The WOFF2 asset path must be a relative assets/ path.');
            if (($asset['mime'] ?? null) !== 'font/woff2') {
                $this->add($path.'.mime', 'Licensed font assets must use the font/woff2 media type.');
            }
            $this->assertSha256($asset['sha256'] ?? null, $path.'.sha256');
            $this->assertFamily($asset['family'] ?? null, $path.'.family');
            if (! is_int($asset['weight'] ?? null) || $asset['weight'] < 100 || $asset['weight'] > 900 || $asset['weight'] % 100 !== 0) {
                $this->add($path.'.weight', 'Font weight must be a multiple of 100 between 100 and 900.');
            }
            if (! in_array($asset['style'] ?? null, ['normal', 'italic'], true)) {
                $this->add($path.'.style', 'Font style must be normal or italic.');
            }
            if (is_string($asset['asset_ref'] ?? null)) {
                $refs[] = $asset['asset_ref'];
            }
        }
        if (count(array_unique($refs, SORT_STRING)) !== count($refs)) {
            $this->add('font_assets', 'Font asset references must be unique.');
        }
    }

    /** @param array<string,mixed> $set @param array<int,array<string,mixed>> $fontAssets */
    private function validateRelationship(array $set, array $fontAssets): void
    {
        $upstream = is_array($set['upstream'] ?? null) ? $set['upstream'] : [];
        $license = is_array($set['license'] ?? null) ? $set['license'] : [];
        $transformation = is_array($set['transformation'] ?? null) ? $set['transformation'] : [];
        $spdx = $license['spdx'] ?? null;
        if (is_string($spdx) && isset(self::LICENSE_PATH_PREFIXES[$spdx]) && is_string($upstream['family_path'] ?? null)
            && ! str_starts_with($upstream['family_path'], self::LICENSE_PATH_PREFIXES[$spdx])) {
            $this->add('font_asset_set.upstream.family_path', 'The Google Fonts family path does not match the declared SPDX license.');
        }

        $declaredRefs = is_array($set['asset_refs'] ?? null) ? $set['asset_refs'] : [];
        $actualRefs = array_map(static fn (mixed $asset): mixed => is_array($asset) ? ($asset['asset_ref'] ?? null) : null, $fontAssets);
        if ($declaredRefs !== $actualRefs) {
            $this->add('font_asset_set.asset_refs', 'The asset references must exactly match font_assets in request order.');
        }

        $kind = $transformation['kind'] ?? null;
        $renamedFamily = $transformation['renamed_family'] ?? null;
        $originalFamily = $set['family'] ?? null;
        if ($kind === 'none' && $renamedFamily !== null) {
            $this->add('font_asset_set.transformation.renamed_family', 'An unmodified font cannot declare a renamed family.');
        }
        if (is_string($renamedFamily) && $renamedFamily === $originalFamily) {
            $this->add('font_asset_set.transformation.renamed_family', 'A renamed font family must differ from its upstream family.');
        }
        if ($kind === 'subset' && $spdx !== 'OFL-1.1') {
            $this->add('font_asset_set.transformation.kind', 'This policy supports subset modification only for OFL-1.1 fonts.');
        }

        $reservedNames = is_array($license['reserved_font_names'] ?? null) ? $license['reserved_font_names'] : [];
        if ($kind === 'subset' && $reservedNames !== [] && ! is_string($renamedFamily)) {
            $this->add('font_asset_set.transformation.renamed_family', 'Modified OFL fonts with Reserved Font Names require a distinct renamed family.');
        }
        if (is_string($renamedFamily)) {
            if ($kind !== 'subset' || $spdx !== 'OFL-1.1' || $reservedNames === []) {
                $this->add('font_asset_set.transformation.renamed_family', 'A renamed family is accepted only for a modified OFL font with Reserved Font Names.');
            }
            foreach ($reservedNames as $reservedName) {
                if (is_string($reservedName) && str_contains(mb_strtolower($renamedFamily), mb_strtolower($reservedName))) {
                    $this->add('font_asset_set.transformation.renamed_family', 'The renamed family must not contain a Reserved Font Name.');
                }
            }
        }

        $effectiveFamily = is_string($renamedFamily) ? $renamedFamily : $originalFamily;
        foreach ($fontAssets as $index => $asset) {
            if (is_array($asset) && isset($asset['family']) && $asset['family'] !== $effectiveFamily) {
                $this->add("font_assets.{$index}.family", 'The WOFF2 family must match the effective licensed font family.');
            }
        }
    }

    /** @param array<string,mixed> $value @param array<int,string> $allowed @param array<int,string> $required */
    private function assertKeys(array $value, array $allowed, array $required, string $path): void
    {
        foreach (array_diff(array_keys($value), $allowed) as $key) {
            $this->add($path.'.'.$key, 'This field is not part of the font asset contract.');
        }
        foreach (array_diff($required, array_keys($value)) as $key) {
            $this->add($path.'.'.$key, 'This field is required.');
        }
    }

    private function assertIdentifier(mixed $value, string $path): void
    {
        $this->assertPattern($value, '/^[A-Za-z0-9][A-Za-z0-9_-]{0,99}$/D', $path, 'The identifier is invalid.');
    }

    private function assertFamily(mixed $value, string $path): void
    {
        $this->assertPattern($value, '/^[A-Za-z0-9][A-Za-z0-9 ._-]{0,99}$/D', $path, 'The font family name is invalid.');
    }

    private function assertSha256(mixed $value, string $path): void
    {
        $this->assertPattern($value, '/^[a-f0-9]{64}$/D', $path, 'The digest must be a lowercase SHA-256 value.');
    }

    private function assertPattern(mixed $value, string $pattern, string $path, string $message): void
    {
        if (! is_string($value) || preg_match($pattern, $value) !== 1) {
            $this->add($path, $message);
        }
    }

    /** @param array<int,mixed> $values */
    private function hasDuplicateStrings(array $values): bool
    {
        $strings = array_values(array_filter($values, is_string(...)));

        return count($strings) === count($values)
            && count(array_unique($strings, SORT_STRING)) !== count($strings);
    }

    private function add(string $path, string $message): void
    {
        $this->errors[$path][] = $message;
    }
}
