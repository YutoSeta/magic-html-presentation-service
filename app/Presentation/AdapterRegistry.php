<?php

namespace App\Presentation;

use App\Exceptions\UnsupportedAdapterException;
use App\Presentation\Adapters\HeroiconsAdapter;
use App\Presentation\Adapters\LicensedSelfHostedFontAdapter;
use App\Presentation\Adapters\SelfHostedFontAdapter;
use App\Presentation\Adapters\SystemFontAdapter;
use App\Presentation\Adapters\TailwindComponentAdapter;
use App\Presentation\Contracts\ComponentAdapter;
use App\Presentation\Contracts\FontAdapter;
use App\Presentation\Contracts\IconAdapter;
use ReflectionClass;
use RuntimeException;

final class AdapterRegistry
{
    public function __construct(
        private readonly TailwindComponentAdapter $components,
        private readonly HeroiconsAdapter $icons,
        private readonly SystemFontAdapter $systemFonts,
        private readonly SelfHostedFontAdapter $selfHostedFonts,
        private readonly LicensedSelfHostedFontAdapter $licensedSelfHostedFonts,
        private readonly string $compiledCssPath,
        private readonly string $utilityVersion,
    ) {}

    /** @return array<string,mixed> */
    public function defaultProfile(): array
    {
        return [
            'profile_id' => 'tailwindcss-heroicons-system-v1',
            'component_adapter' => $this->components->key(),
            'utility_adapter' => 'tailwindcss-v4',
            'icon_adapter' => $this->icons->key(),
            'icon_style' => 'outline',
            'font_adapter' => $this->systemFonts->key(),
            'font_family' => 'sans-ja',
            'image_adapter' => 'relative-assets',
            'catalog_locks' => $this->catalogLocks($this->systemFonts),
        ];
    }

    /** @return array<string,mixed> */
    public function manifest(): array
    {
        return [
            'contract_version' => '1.0',
            'profiles' => [[
                'profile_id' => 'tailwindcss-heroicons-system-v1',
                'component_adapter' => $this->components->key(),
                'utility_adapter' => 'tailwindcss-v4',
                'icon_adapter' => $this->icons->key(),
                'icon_styles' => ['outline', 'solid'],
                'font_adapters' => [$this->systemFonts->key(), $this->selfHostedFonts->key(), $this->licensedSelfHostedFonts->key()],
                'font_families' => ['sans', 'sans-ja', 'serif', 'mono'],
                'image_adapter' => 'relative-assets',
                'default_profile' => $this->defaultProfile(),
                'catalog_locks_by_font_adapter' => [
                    $this->systemFonts->key() => $this->catalogLocks($this->systemFonts),
                    $this->selfHostedFonts->key() => $this->catalogLocks($this->selfHostedFonts),
                    $this->licensedSelfHostedFonts->key() => $this->catalogLocks($this->licensedSelfHostedFonts),
                ],
            ]],
            'extension_points' => [[
                'adapter' => 'tailwind-plus-byo',
                'status' => 'not_registered',
                'license_scope' => 'private-byo',
                'accepts_provider_source_in_requests' => false,
            ]],
        ];
    }

    /** @param array<string,mixed> $profile */
    public function assertSupported(array $profile): void
    {
        $configuration = config('presentation.profiles.'.$profile['profile_id']);
        if (! is_array($configuration)) {
            throw new UnsupportedAdapterException('The requested Adapter Profile is not registered by this service.');
        }
        foreach (['component_adapter', 'utility_adapter', 'icon_adapter', 'image_adapter'] as $field) {
            if (($profile[$field] ?? null) !== ($configuration[$field] ?? null)) {
                throw new UnsupportedAdapterException("The Adapter Profile field {$field} does not match its server registry entry.");
            }
        }
        if (! in_array($profile['font_adapter'] ?? null, $configuration['font_adapters'] ?? [], true)) {
            throw new UnsupportedAdapterException('The requested font adapter is not registered for this profile.');
        }

        $fontAdapter = $this->font((string) $profile['font_adapter']);
        $expectedLocks = $this->indexLocks($this->catalogLocks($fontAdapter));
        $actualLocks = $this->indexLocks($profile['catalog_locks']);
        if ($actualLocks !== $expectedLocks) {
            throw new UnsupportedAdapterException('The Adapter Profile catalog locks do not match the installed immutable adapter assets.');
        }
    }

    public function components(string $key): ComponentAdapter
    {
        if ($key !== $this->components->key()) {
            throw new UnsupportedAdapterException("Component adapter {$key} is not registered.");
        }

        return $this->components;
    }

    public function icons(string $key): IconAdapter
    {
        if ($key !== $this->icons->key()) {
            throw new UnsupportedAdapterException("Icon adapter {$key} is not registered.");
        }

        return $this->icons;
    }

    public function font(string $key): FontAdapter
    {
        return match ($key) {
            'system' => $this->systemFonts,
            'self-hosted' => $this->selfHostedFonts,
            'licensed-self-hosted' => $this->licensedSelfHostedFonts,
            default => throw new UnsupportedAdapterException("Font adapter {$key} is not registered."),
        };
    }

    public function compiledCss(): string
    {
        if (! is_file($this->compiledCssPath)) {
            throw new RuntimeException('The compiled Tailwind presentation stylesheet is unavailable.');
        }

        return (string) file_get_contents($this->compiledCssPath);
    }

    public function ready(): bool
    {
        try {
            return $this->compiledCss() !== ''
                && $this->icons->version() === '2.2.0'
                && preg_match('/^[a-f0-9]{64}$/D', $this->icons->digest()) === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<int,array<string,string>> */
    private function catalogLocks(FontAdapter $fontAdapter): array
    {
        $reflection = new ReflectionClass($this->components);
        $componentPath = $reflection->getFileName();
        if (! is_string($componentPath) || ! is_file($componentPath) || ! is_file($this->compiledCssPath)) {
            throw new RuntimeException('Installed presentation adapter assets are unavailable.');
        }

        $locks = [
            [
                'provider' => $this->components->key(),
                'version' => $this->components->version(),
                'digest' => hash_file('sha256', $componentPath),
                'license_scope' => 'open-source',
            ],
            [
                'provider' => 'tailwindcss-v4',
                'version' => $this->utilityVersion,
                'digest' => hash_file('sha256', $this->compiledCssPath),
                'license_scope' => 'open-source',
            ],
            [
                'provider' => $this->icons->key(),
                'version' => $this->icons->version(),
                'digest' => $this->icons->digest(),
                'license_scope' => 'open-source',
            ],
            [
                'provider' => $fontAdapter->key().'-fonts',
                'version' => $fontAdapter->version(),
                'digest' => $fontAdapter->digest(),
                'license_scope' => 'open-source',
            ],
        ];
        usort($locks, static fn (array $left, array $right): int => strcmp($left['provider'], $right['provider']));

        return $locks;
    }

    /** @param array<int,array<string,mixed>> $locks @return array<string,array<string,mixed>> */
    private function indexLocks(array $locks): array
    {
        $indexed = [];
        foreach ($locks as $lock) {
            $indexed[(string) $lock['provider']] = $lock;
        }
        ksort($indexed, SORT_STRING);

        return $indexed;
    }
}
