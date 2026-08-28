<?php

namespace Tests\Feature;

use App\Presentation\AdapterRegistry;
use Tests\TestCase;

final class PresentationControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('presentation.service_token', 'presentation-test-token');
    }

    public function test_capability_inventory_exposes_the_default_pinned_profile(): void
    {
        $profile = app(AdapterRegistry::class)->defaultProfile();

        $this->getJson('/api')
            ->assertOk()
            ->assertJsonPath('name', 'magic-html-presentation-service')
            ->assertJsonPath('execution.deterministic', true)
            ->assertJsonPath('execution.remote_asset_fetching', false)
            ->assertJsonPath('default_profile.profile_id', $profile['profile_id'])
            ->assertJsonPath('adapters.extension_points.0.license_scope', 'private-byo');

        $this->getJson('/api/__verify')
            ->assertOk()
            ->assertJsonPath('checks.contract_version_supported', true)
            ->assertJsonPath('checks.adapter_assets', true);
    }

    public function test_materialization_requires_the_service_bearer_token(): void
    {
        $this->postJson('/api/v1/presentations/materialize', $this->payload())
            ->assertUnauthorized()
            ->assertExactJson([
                'contract_version' => '1.0',
                'type' => 'unauthenticated',
                'message' => 'A valid bearer token is required.',
                'errors' => [],
            ]);
    }

    public function test_it_materializes_canonical_html_css_assets_and_source_map_deterministically(): void
    {
        $first = $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/materialize', $this->payload())
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('contract_version', '1.0')
            ->assertJsonPath('surface_id', 'demo')
            ->assertJsonPath('profile_id', 'tailwindcss-heroicons-system-v1')
            ->assertJsonPath('required_assets.0.asset_ref', 'hero-image')
            ->assertJsonPath('source_map.0.component_key', 'hero')
            ->assertJsonPath('source_map.0.slot_roles.1', 'Title')
            ->json();

        $second = $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/materialize', $this->payload())
            ->assertOk()
            ->json();

        $this->assertSame($first, $second);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $first['digest']);
        $this->assertStringContainsString('m-component="hero"', $first['html']);
        $this->assertStringContainsString('m-field="hero.title"', $first['html']);
        $this->assertStringContainsString('data-m-icon-provider="heroicons-v2"', $first['html']);
        $this->assertStringContainsString('m-asset="hero-image"', $first['html']);
        $this->assertStringContainsString('sm\\:text-6xl', $first['css']);
        $this->assertStringContainsString('--m-font-family:', $first['css']);
    }

    public function test_user_content_is_escaped_and_cannot_inject_script_or_classes(): void
    {
        $payload = $this->payload();
        $payload['component_ast']['components'][0]['slots'][1]['content']['fallback'] = '<script>alert(1)</script><div class="fixed">';

        $html = $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/materialize', $payload)
            ->assertOk()
            ->json('html');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<div class="fixed">', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_closed_ast_and_safe_href_boundaries_are_enforced(): void
    {
        $payload = $this->payload();
        $payload['component_ast']['components'][0]['class'] = 'fixed inset-0';
        $payload['component_ast']['components'][0]['slots'][3]['content']['actions'][0]['href'] = 'javascript:alert(1)';

        $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/materialize', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('type', 'invalid_component_ast')
            ->assertJsonFragment(['field' => 'component_ast.components.0.class'])
            ->assertJsonFragment(['field' => 'component_ast.components.0.slots.3.content.actions.0.href']);
    }

    public function test_unregistered_or_unpinned_adapters_fail_closed(): void
    {
        $payload = $this->payload();
        $payload['adapter_profile']['catalog_locks'][0]['digest'] = str_repeat('f', 64);

        $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/materialize', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('type', 'unsupported_adapter');
    }

    public function test_asset_manifest_must_exactly_cover_image_slots(): void
    {
        $payload = $this->payload();
        $payload['assets'] = [];

        $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/materialize', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('type', 'asset_resolution_failed');

        $payload = $this->payload();
        $payload['assets'][] = [
            'asset_ref' => 'unused',
            'path' => 'assets/images/unused.webp',
            'mime' => 'image/webp',
            'sha256' => str_repeat('d', 64),
        ];

        $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/materialize', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('type', 'asset_resolution_failed');
    }

    public function test_system_fonts_reject_request_supplied_font_assets(): void
    {
        $payload = $this->payload();
        $payload['font_assets'][] = [
            'asset_ref' => 'font-main',
            'path' => 'assets/fonts/main.woff2',
            'mime' => 'font/woff2',
            'sha256' => str_repeat('e', 64),
            'family' => 'Example Sans',
            'weight' => 400,
            'style' => 'normal',
        ];

        $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/materialize', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('type', 'unsupported_adapter');
    }

    public function test_self_hosted_font_profile_emits_only_declared_woff2_assets(): void
    {
        $payload = $this->payload();
        $manifest = app(AdapterRegistry::class)->manifest();
        $payload['adapter_profile']['font_adapter'] = 'self-hosted';
        $payload['adapter_profile']['font_family'] = 'sans-ja';
        $payload['adapter_profile']['catalog_locks'] = $manifest['profiles'][0]['catalog_locks_by_font_adapter']['self-hosted'];
        $payload['font_assets'] = [[
            'asset_ref' => 'font-main',
            'path' => 'assets/fonts/main.woff2',
            'mime' => 'font/woff2',
            'sha256' => str_repeat('e', 64),
            'family' => 'Example Sans',
            'weight' => 400,
            'style' => 'normal',
        ]];

        $response = $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/materialize', $payload)
            ->assertOk()
            ->assertJsonPath('required_assets.1.asset_ref', 'font-main')
            ->json();

        $this->assertStringContainsString('@font-face{font-family:"Example Sans"', $response['css']);
        $this->assertStringContainsString('url("assets/fonts/main.woff2")', $response['css']);
    }

    public function test_self_hosted_font_family_cannot_break_out_of_generated_css(): void
    {
        $payload = $this->payload();
        $manifest = app(AdapterRegistry::class)->manifest();
        $payload['adapter_profile']['font_adapter'] = 'self-hosted';
        $payload['adapter_profile']['catalog_locks'] = $manifest['profiles'][0]['catalog_locks_by_font_adapter']['self-hosted'];
        $payload['font_assets'] = [[
            'asset_ref' => 'font-main',
            'path' => 'assets/fonts/main.woff2',
            'mime' => 'font/woff2',
            'sha256' => str_repeat('e', 64),
            'family' => '</style><script>alert(1)</script>',
            'weight' => 400,
            'style' => 'normal',
        ]];

        $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/materialize', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('type', 'invalid_component_ast')
            ->assertJsonFragment(['field' => 'font_assets.0.family']);
    }

    public function test_every_contract_composition_and_variant_has_a_deterministic_adapter(): void
    {
        $variants = [
            'hero' => ['centered', 'split-media'],
            'feature-grid' => ['cards', 'icon-grid'],
            'content' => ['prose', 'split'],
            'steps' => ['numbered', 'timeline'],
            'testimonials' => ['cards', 'spotlight'],
            'faq' => ['stacked', 'two-column'],
            'cta' => ['banner', 'centered'],
            'contact' => ['split', 'centered'],
        ];

        foreach ($variants as $composition => $compositionVariants) {
            foreach ($compositionVariants as $variant) {
                $payload = $this->payload();
                $payload['component_ast']['components'] = [[
                    'key' => str_replace('-', '_', $composition).'_'.str_replace('-', '_', $variant),
                    'composition' => $composition,
                    'variant' => $variant,
                    'slots' => [[
                        'role' => 'Title',
                        'content' => ['kind' => 'text', 'value' => $composition.' '.$variant],
                    ]],
                ]];
                $payload['assets'] = [];

                $this->withToken('presentation-test-token')
                    ->postJson('/api/v1/presentations/materialize', $payload)
                    ->assertOk()
                    ->assertJsonPath('source_map.0.composition', $composition)
                    ->assertJsonPath('source_map.0.variant', $variant);
            }
        }
    }

    public function test_unknown_top_level_fields_are_rejected(): void
    {
        $payload = $this->payload();
        $payload['template_url'] = 'https://attacker.invalid/template';

        $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/materialize', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('type', 'validation_failed')
            ->assertJsonPath('errors.0.field', 'template_url');
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        return [
            'contract_version' => '1.0',
            'component_ast' => [
                'contract_version' => '1.0',
                'surface_id' => 'demo',
                'locale' => 'ja-JP',
                'components' => [[
                    'key' => 'hero',
                    'composition' => 'hero',
                    'variant' => 'split-media',
                    'design_key' => 'd_0123456789ab',
                    'slots' => [
                        ['role' => 'Eyebrow', 'content' => ['kind' => 'text', 'value' => 'Magic HTML']],
                        ['role' => 'Title', 'content' => ['kind' => 'binding', 'resource_key' => 'content:home', 'path' => 'hero.title', 'fallback' => '決定論的なUI生成']],
                        ['role' => 'Text', 'content' => ['kind' => 'text', 'value' => '意味、見た目、データ接続を分離します。']],
                        ['role' => 'Actions', 'content' => ['kind' => 'actions', 'actions' => [[
                            'label' => '詳しく見る',
                            'href' => '#features',
                            'tone' => 'primary',
                            'icon_intent' => 'primary-action',
                        ]]]],
                        ['role' => 'Image', 'content' => ['kind' => 'asset', 'asset_ref' => 'hero-image', 'alt' => 'Adapter preview']],
                    ],
                ]],
            ],
            'adapter_profile' => app(AdapterRegistry::class)->defaultProfile(),
            'assets' => [[
                'asset_ref' => 'hero-image',
                'path' => 'assets/images/hero.webp',
                'mime' => 'image/webp',
                'sha256' => str_repeat('c', 64),
            ]],
            'font_assets' => [],
        ];
    }
}
