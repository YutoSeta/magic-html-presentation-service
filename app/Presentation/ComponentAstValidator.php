<?php

namespace App\Presentation;

use App\Exceptions\InvalidPresentationException;
use Illuminate\Support\Str;

final class ComponentAstValidator
{
    /** @var array<string,array<int,string>> */
    private const VARIANTS = [
        'hero' => ['centered', 'split-media'],
        'feature-grid' => ['cards', 'icon-grid'],
        'content' => ['prose', 'split'],
        'steps' => ['numbered', 'timeline'],
        'testimonials' => ['cards', 'spotlight'],
        'faq' => ['stacked', 'two-column'],
        'cta' => ['banner', 'centered'],
        'contact' => ['split', 'centered'],
    ];

    /** @var array<int,string> */
    private const ROLES = ['Eyebrow', 'Title', 'Text', 'Items', 'Actions', 'Image'];

    /** @var array<int,string> */
    private const ICON_INTENTS = ['primary-action', 'success', 'feature', 'search', 'email', 'phone', 'play', 'disclosure'];

    /** @var array<string,array<int,string>> */
    private array $errors = [];

    /** @param array<string,mixed> $payload */
    public function validate(array $payload): void
    {
        $this->errors = [];
        $this->validateComponentAst($payload['component_ast'] ?? null);
        $this->validateAdapterProfile($payload['adapter_profile'] ?? null);
        $this->validateAssets($payload['assets'] ?? null);
        $this->validateFontAssets($payload['font_assets'] ?? null);

        if ($this->errors !== []) {
            ksort($this->errors, SORT_STRING);

            throw new InvalidPresentationException($this->errors);
        }
    }

    private function validateComponentAst(mixed $ast): void
    {
        if (! is_array($ast) || array_is_list($ast)) {
            $this->add('component_ast', 'The Component AST must be an object.');

            return;
        }
        $this->assertKeys($ast, ['contract_version', 'surface_id', 'locale', 'components'], ['contract_version', 'surface_id', 'components'], 'component_ast');
        if (($ast['contract_version'] ?? null) !== '1.0') {
            $this->add('component_ast.contract_version', 'The Component AST contract version must be 1.0.');
        }
        $this->assertIdentifier($ast['surface_id'] ?? null, 'component_ast.surface_id');
        if (isset($ast['locale']) && (! is_string($ast['locale']) || Str::length($ast['locale']) < 2 || Str::length($ast['locale']) > 20)) {
            $this->add('component_ast.locale', 'The locale must be between 2 and 20 characters.');
        }

        $components = $ast['components'] ?? null;
        if (! is_array($components) || ! array_is_list($components) || $components === [] || count($components) > 50) {
            $this->add('component_ast.components', 'The Component AST must contain between 1 and 50 components.');

            return;
        }

        $keys = [];
        foreach ($components as $index => $component) {
            $path = "component_ast.components.{$index}";
            if (! is_array($component) || array_is_list($component)) {
                $this->add($path, 'Every component must be an object.');

                continue;
            }
            $this->assertKeys($component, ['key', 'composition', 'variant', 'design_key', 'slots'], ['key', 'composition', 'variant', 'slots'], $path);
            $this->assertIdentifier($component['key'] ?? null, $path.'.key');
            if (is_string($component['key'] ?? null) && isset($keys[$component['key']])) {
                $this->add($path.'.key', 'Component keys must be unique.');
            }
            if (is_string($component['key'] ?? null)) {
                $keys[$component['key']] = true;
            }
            $composition = $component['composition'] ?? null;
            $variant = $component['variant'] ?? null;
            if (! is_string($composition) || ! isset(self::VARIANTS[$composition])) {
                $this->add($path.'.composition', 'The component composition is not supported.');
            } elseif (! is_string($variant) || ! in_array($variant, self::VARIANTS[$composition], true)) {
                $this->add($path.'.variant', 'The component variant is not supported for this composition.');
            }
            if (isset($component['design_key']) && (! is_string($component['design_key']) || preg_match('/^d_[a-f0-9]{12}$/D', $component['design_key']) !== 1)) {
                $this->add($path.'.design_key', 'The design key must be a canonical Design AST marker.');
            }
            $this->validateSlots($component['slots'] ?? null, $path.'.slots');
        }
    }

    private function validateSlots(mixed $slots, string $path): void
    {
        if (! is_array($slots) || ! array_is_list($slots) || $slots === [] || count($slots) > 12) {
            $this->add($path, 'A component must contain between 1 and 12 semantic slots.');

            return;
        }

        $roles = [];
        foreach ($slots as $index => $slot) {
            $slotPath = $path.'.'.$index;
            if (! is_array($slot) || array_is_list($slot)) {
                $this->add($slotPath, 'Every slot must be an object.');

                continue;
            }
            $this->assertKeys($slot, ['role', 'content'], ['role', 'content'], $slotPath);
            $role = $slot['role'] ?? null;
            if (! is_string($role) || ! in_array($role, self::ROLES, true)) {
                $this->add($slotPath.'.role', 'The semantic slot role is not supported.');

                continue;
            }
            if (isset($roles[$role])) {
                $this->add($slotPath.'.role', 'Semantic slot roles must be unique within a component.');
            }
            $roles[$role] = true;
            $this->validateSlotContent($role, $slot['content'] ?? null, $slotPath.'.content');
        }
    }

    private function validateSlotContent(string $role, mixed $content, string $path): void
    {
        if (! is_array($content) || array_is_list($content)) {
            $this->add($path, 'Slot content must be an object.');

            return;
        }
        $kind = $content['kind'] ?? null;
        if (in_array($role, ['Eyebrow', 'Title', 'Text'], true)) {
            if ($kind === 'text') {
                $this->assertKeys($content, ['kind', 'value'], ['kind', 'value'], $path);
                $this->assertText($content['value'] ?? null, 1, 8000, $path.'.value');
            } elseif ($kind === 'binding') {
                $this->assertKeys($content, ['kind', 'resource_key', 'path', 'fallback'], ['kind', 'resource_key', 'path'], $path);
                if (! is_string($content['resource_key'] ?? null) || preg_match('/^(?:content|collection|form):[A-Za-z0-9][A-Za-z0-9_-]{0,99}$/D', $content['resource_key']) !== 1) {
                    $this->add($path.'.resource_key', 'The Resource Contract key is invalid.');
                }
                if (! is_string($content['path'] ?? null) || preg_match('/^[A-Za-z][A-Za-z0-9_-]*(?:\.[A-Za-z][A-Za-z0-9_-]*)*$/D', $content['path']) !== 1 || Str::length($content['path']) > 500) {
                    $this->add($path.'.path', 'The Resource Contract field path is invalid.');
                }
                if (isset($content['fallback']) && (! is_string($content['fallback']) || Str::length($content['fallback']) > 8000)) {
                    $this->add($path.'.fallback', 'The binding fallback must not exceed 8000 characters.');
                }
            } else {
                $this->add($path.'.kind', 'Text slots accept only text or binding content.');
            }

            return;
        }

        if ($role === 'Items') {
            $this->assertKeys($content, ['kind', 'items'], ['kind', 'items'], $path);
            if ($kind !== 'items' || ! is_array($content['items'] ?? null) || ! array_is_list($content['items']) || $content['items'] === [] || count($content['items']) > 12) {
                $this->add($path.'.items', 'Items content must contain between 1 and 12 items.');

                return;
            }
            $keys = [];
            foreach ($content['items'] as $index => $item) {
                $itemPath = $path.'.items.'.$index;
                if (! is_array($item) || array_is_list($item)) {
                    $this->add($itemPath, 'Every item must be an object.');

                    continue;
                }
                $this->assertKeys($item, ['key', 'title', 'text', 'icon_intent'], ['key', 'title'], $itemPath);
                $this->assertIdentifier($item['key'] ?? null, $itemPath.'.key');
                if (is_string($item['key'] ?? null) && isset($keys[$item['key']])) {
                    $this->add($itemPath.'.key', 'Item keys must be unique within a slot.');
                }
                if (is_string($item['key'] ?? null)) {
                    $keys[$item['key']] = true;
                }
                $this->assertText($item['title'] ?? null, 1, 500, $itemPath.'.title');
                if (isset($item['text'])) {
                    $this->assertText($item['text'], 0, 4000, $itemPath.'.text');
                }
                $this->assertIconIntent($item['icon_intent'] ?? null, $itemPath.'.icon_intent');
            }

            return;
        }

        if ($role === 'Actions') {
            $this->assertKeys($content, ['kind', 'actions'], ['kind', 'actions'], $path);
            if ($kind !== 'actions' || ! is_array($content['actions'] ?? null) || ! array_is_list($content['actions']) || $content['actions'] === [] || count($content['actions']) > 4) {
                $this->add($path.'.actions', 'Actions content must contain between 1 and 4 actions.');

                return;
            }
            foreach ($content['actions'] as $index => $action) {
                $actionPath = $path.'.actions.'.$index;
                if (! is_array($action) || array_is_list($action)) {
                    $this->add($actionPath, 'Every action must be an object.');

                    continue;
                }
                $this->assertKeys($action, ['label', 'href', 'tone', 'icon_intent'], ['label', 'href', 'tone'], $actionPath);
                $this->assertText($action['label'] ?? null, 1, 200, $actionPath.'.label');
                if (! is_string($action['href'] ?? null)
                    || Str::length($action['href']) > 2048
                    || preg_match('~^(?:/(?:[^/\s][^\s]*)?|#[A-Za-z][A-Za-z0-9_-]*|https://[^\s]+)$~D', $action['href']) !== 1) {
                    $this->add($actionPath.'.href', 'Action destinations must be relative paths, fragments, or HTTPS URLs.');
                }
                if (! in_array($action['tone'] ?? null, ['primary', 'secondary'], true)) {
                    $this->add($actionPath.'.tone', 'The action tone must be primary or secondary.');
                }
                $this->assertIconIntent($action['icon_intent'] ?? null, $actionPath.'.icon_intent');
            }

            return;
        }

        $this->assertKeys($content, ['kind', 'asset_ref', 'alt'], ['kind', 'asset_ref', 'alt'], $path);
        if ($kind !== 'asset') {
            $this->add($path.'.kind', 'Image slots accept only asset content.');
        }
        $this->assertIdentifier($content['asset_ref'] ?? null, $path.'.asset_ref');
        $this->assertText($content['alt'] ?? null, 0, 500, $path.'.alt');
    }

    private function validateAdapterProfile(mixed $profile): void
    {
        if (! is_array($profile) || array_is_list($profile)) {
            $this->add('adapter_profile', 'The Adapter Profile must be an object.');

            return;
        }
        $keys = ['profile_id', 'component_adapter', 'utility_adapter', 'icon_adapter', 'icon_style', 'font_adapter', 'font_family', 'image_adapter', 'catalog_locks'];
        $this->assertKeys($profile, $keys, $keys, 'adapter_profile');
        foreach (['profile_id', 'component_adapter', 'utility_adapter', 'icon_adapter'] as $field) {
            if (! is_string($profile[$field] ?? null) || preg_match('/^[a-z][a-z0-9.-]{0,99}$/D', $profile[$field]) !== 1) {
                $this->add('adapter_profile.'.$field, 'Adapter identifiers must use the canonical registry-key format.');
            }
        }
        if (! in_array($profile['icon_style'] ?? null, ['outline', 'solid'], true)) {
            $this->add('adapter_profile.icon_style', 'The icon style must be outline or solid.');
        }
        if (! in_array($profile['font_adapter'] ?? null, ['system', 'self-hosted'], true)) {
            $this->add('adapter_profile.font_adapter', 'The font adapter is not supported by contract 1.0.');
        }
        if (! in_array($profile['font_family'] ?? null, ['sans', 'sans-ja', 'serif', 'mono'], true)) {
            $this->add('adapter_profile.font_family', 'The font family is not supported by contract 1.0.');
        }
        if (($profile['image_adapter'] ?? null) !== 'relative-assets') {
            $this->add('adapter_profile.image_adapter', 'The image adapter must be relative-assets.');
        }
        $locks = $profile['catalog_locks'] ?? null;
        if (! is_array($locks) || ! array_is_list($locks) || $locks === [] || count($locks) > 20) {
            $this->add('adapter_profile.catalog_locks', 'The Adapter Profile must contain between 1 and 20 catalog locks.');

            return;
        }
        $providers = [];
        foreach ($locks as $index => $lock) {
            $path = 'adapter_profile.catalog_locks.'.$index;
            if (! is_array($lock) || array_is_list($lock)) {
                $this->add($path, 'Every catalog lock must be an object.');

                continue;
            }
            $this->assertKeys($lock, ['provider', 'version', 'digest', 'license_scope'], ['provider', 'version', 'digest', 'license_scope'], $path);
            if (! is_string($lock['provider'] ?? null) || preg_match('/^[a-z][a-z0-9.-]{0,99}$/D', $lock['provider']) !== 1) {
                $this->add($path.'.provider', 'The catalog provider must be a canonical registry key.');
            } elseif (isset($providers[$lock['provider']])) {
                $this->add($path.'.provider', 'Catalog lock providers must be unique.');
            } else {
                $providers[$lock['provider']] = true;
            }
            $this->assertText($lock['version'] ?? null, 1, 100, $path.'.version');
            if (! is_string($lock['digest'] ?? null) || preg_match('/^[a-f0-9]{64}$/D', $lock['digest']) !== 1) {
                $this->add($path.'.digest', 'The catalog lock digest must be lowercase SHA-256.');
            }
            if (! in_array($lock['license_scope'] ?? null, ['open-source', 'private-byo'], true)) {
                $this->add($path.'.license_scope', 'The catalog license scope is invalid.');
            }
        }
    }

    private function validateAssets(mixed $assets): void
    {
        if (! is_array($assets) || ! array_is_list($assets) || count($assets) > 50) {
            $this->add('assets', 'Assets must be a list containing at most 50 entries.');

            return;
        }
        $references = [];
        foreach ($assets as $index => $asset) {
            $path = 'assets.'.$index;
            if (! is_array($asset) || array_is_list($asset)) {
                $this->add($path, 'Every image asset must be an object.');

                continue;
            }
            $this->assertKeys($asset, ['asset_ref', 'path', 'mime', 'sha256'], ['asset_ref', 'path', 'mime', 'sha256'], $path);
            $this->assertIdentifier($asset['asset_ref'] ?? null, $path.'.asset_ref');
            if (is_string($asset['asset_ref'] ?? null) && isset($references[$asset['asset_ref']])) {
                $this->add($path.'.asset_ref', 'Image asset references must be unique.');
            }
            if (is_string($asset['asset_ref'] ?? null)) {
                $references[$asset['asset_ref']] = true;
            }
            $this->assertRelativeAssetPath($asset['path'] ?? null, $path.'.path');
            if (! in_array($asset['mime'] ?? null, ['image/jpeg', 'image/png', 'image/webp', 'image/avif'], true)) {
                $this->add($path.'.mime', 'The image MIME type is not supported.');
            }
            $this->assertSha256($asset['sha256'] ?? null, $path.'.sha256');
        }
    }

    private function validateFontAssets(mixed $fontAssets): void
    {
        if (! is_array($fontAssets) || ! array_is_list($fontAssets) || count($fontAssets) > 20) {
            $this->add('font_assets', 'Font assets must be a list containing at most 20 entries.');

            return;
        }
        $references = [];
        foreach ($fontAssets as $index => $fontAsset) {
            $path = 'font_assets.'.$index;
            if (! is_array($fontAsset) || array_is_list($fontAsset)) {
                $this->add($path, 'Every font asset must be an object.');

                continue;
            }
            $keys = ['asset_ref', 'path', 'mime', 'sha256', 'family', 'weight', 'style'];
            $this->assertKeys($fontAsset, $keys, $keys, $path);
            $this->assertIdentifier($fontAsset['asset_ref'] ?? null, $path.'.asset_ref');
            if (is_string($fontAsset['asset_ref'] ?? null) && isset($references[$fontAsset['asset_ref']])) {
                $this->add($path.'.asset_ref', 'Font asset references must be unique.');
            }
            if (is_string($fontAsset['asset_ref'] ?? null)) {
                $references[$fontAsset['asset_ref']] = true;
            }
            $this->assertRelativeAssetPath($fontAsset['path'] ?? null, $path.'.path');
            if (($fontAsset['mime'] ?? null) !== 'font/woff2') {
                $this->add($path.'.mime', 'Self-hosted fonts must use WOFF2.');
            }
            $this->assertSha256($fontAsset['sha256'] ?? null, $path.'.sha256');
            if (! is_string($fontAsset['family'] ?? null)
                || preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]{0,99}$/D', $fontAsset['family']) !== 1) {
                $this->add($path.'.family', 'Font family names must use only ASCII letters, numbers, spaces, dots, underscores, or hyphens.');
            }
            if (! is_int($fontAsset['weight'] ?? null) || $fontAsset['weight'] < 100 || $fontAsset['weight'] > 900 || $fontAsset['weight'] % 100 !== 0) {
                $this->add($path.'.weight', 'Font weight must be a 100-step integer from 100 through 900.');
            }
            if (! in_array($fontAsset['style'] ?? null, ['normal', 'italic'], true)) {
                $this->add($path.'.style', 'Font style must be normal or italic.');
            }
        }
    }

    /** @param array<string,mixed> $value @param array<int,string> $allowed @param array<int,string> $required */
    private function assertKeys(array $value, array $allowed, array $required, string $path): void
    {
        foreach (array_diff(array_keys($value), $allowed) as $unknown) {
            $this->add($path.'.'.$unknown, 'This field is not part of contract 1.0.');
        }
        foreach (array_diff($required, array_keys($value)) as $missing) {
            $this->add($path.'.'.$missing, 'This field is required by contract 1.0.');
        }
    }

    private function assertIdentifier(mixed $value, string $path): void
    {
        if (! is_string($value) || preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,99}$/D', $value) !== 1) {
            $this->add($path, 'The identifier must use 1 to 100 URL-safe characters.');
        }
    }

    private function assertText(mixed $value, int $minimum, int $maximum, string $path): void
    {
        if (! is_string($value) || Str::length($value) < $minimum || Str::length($value) > $maximum) {
            $this->add($path, "The text must contain between {$minimum} and {$maximum} characters.");
        }
    }

    private function assertIconIntent(mixed $value, string $path): void
    {
        if ($value !== null && (! is_string($value) || ! in_array($value, self::ICON_INTENTS, true))) {
            $this->add($path, 'The icon intent is not supported.');
        }
    }

    private function assertRelativeAssetPath(mixed $value, string $path): void
    {
        if (! is_string($value)
            || Str::length($value) > 1000
            || preg_match('~^assets/(?:[A-Za-z0-9._-]+/)*[A-Za-z0-9][A-Za-z0-9._-]{0,199}$~D', $value) !== 1) {
            $this->add($path, 'Asset paths must be normalized relative paths below assets/.');
        }
    }

    private function assertSha256(mixed $value, string $path): void
    {
        if (! is_string($value) || preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
            $this->add($path, 'The asset digest must be lowercase SHA-256.');
        }
    }

    private function add(string $path, string $message): void
    {
        $this->errors[$path][] = $message;
    }
}
