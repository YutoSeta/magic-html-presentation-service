<?php

namespace App\Presentation;

use App\Support\CanonicalJson;
use DOMDocument;

final class PresentationMaterializer
{
    public function __construct(
        private readonly ComponentAstValidator $validator,
        private readonly AdapterRegistry $adapters,
        private readonly AssetResolver $assets,
    ) {}

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function materialize(array $payload): array
    {
        $this->validator->validate($payload);

        $profile = $payload['adapter_profile'];
        $ast = $payload['component_ast'];
        $this->adapters->assertSupported($profile);

        $resolved = $this->assets->resolve($ast['components'], $payload['assets'], $payload['font_assets']);
        $components = $this->adapters->components($profile['component_adapter']);
        $icons = $this->adapters->icons($profile['icon_adapter']);
        $font = $this->adapters->font($profile['font_adapter']);

        $document = new DOMDocument('1.0', 'UTF-8');
        $main = $document->createElement('main');
        $main->setAttribute('class', 'm-presentation min-h-screen bg-white text-slate-950');
        $main->setAttribute('m-surface', (string) $ast['surface_id']);
        $main->setAttribute('lang', (string) ($ast['locale'] ?? 'en'));
        $document->appendChild($main);

        $sourceMap = [];
        foreach ($ast['components'] as $component) {
            $main->appendChild($components->render(
                $document,
                $component,
                $icons,
                $profile['icon_style'],
                $resolved['images'],
            ));
            $sourceMap[] = [
                'component_key' => $component['key'],
                'composition' => $component['composition'],
                'variant' => $component['variant'],
                'html_id' => 'm-component-'.$component['key'],
                'slot_roles' => array_map(
                    static fn (array $slot): string => (string) $slot['role'],
                    $component['slots'],
                ),
            ];
        }

        $html = (string) $document->saveHTML($main);
        $css = $this->adapters->compiledCss().$font->compile($profile['font_family'], $payload['font_assets']);
        $digestInput = [
            'surface_id' => $ast['surface_id'],
            'profile_id' => $profile['profile_id'],
            'catalog_locks' => $profile['catalog_locks'],
            'html' => $html,
            'css' => $css,
            'required_assets' => $resolved['required_assets'],
            'source_map' => $sourceMap,
        ];

        return [
            'contract_version' => '1.0',
            ...$digestInput,
            'digest' => hash('sha256', CanonicalJson::encode($digestInput)),
        ];
    }
}
