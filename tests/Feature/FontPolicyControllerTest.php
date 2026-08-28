<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Presentation\AdapterRegistry;
use Tests\TestCase;

final class FontPolicyControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('presentation.service_token', 'presentation-test-token');
    }

    public function test_font_policy_check_requires_the_service_bearer_token(): void
    {
        $this->postJson('/api/v1/presentations/font-assets/check', $this->policyPayload())
            ->assertUnauthorized();
    }

    public function test_unmodified_ofl_google_font_is_eligible_and_deterministic(): void
    {
        $first = $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/font-assets/check', $this->policyPayload())
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('policy_version', 'google-fonts-self-host-v1')
            ->assertJsonPath('eligible_for_materialization', true)
            ->assertJsonPath('family', 'Inter')
            ->assertJsonPath('requirements.0', 'retain-license-text')
            ->assertJsonPath('warnings', [])
            ->json();

        $second = $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/font-assets/check', $this->policyPayload())
            ->assertOk()
            ->json();

        $this->assertSame($first, $second);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $first['digest']);
    }

    public function test_font_asset_set_is_a_closed_contract(): void
    {
        $payload = $this->policyPayload();
        $payload['font_asset_set']['license']['download_url'] = 'https://fonts.example.invalid/font.woff2';

        $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/font-assets/check', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('type', 'invalid_font_asset_set')
            ->assertJsonFragment(['field' => 'font_asset_set.license.download_url']);
    }

    public function test_license_identifier_must_match_the_google_fonts_catalog_path(): void
    {
        $payload = $this->policyPayload();
        $payload['font_asset_set']['license']['spdx'] = 'Apache-2.0';

        $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/font-assets/check', $payload)
            ->assertUnprocessable()
            ->assertJsonFragment(['field' => 'font_asset_set.upstream.family_path']);
    }

    public function test_modified_ofl_font_with_reserved_name_requires_a_safe_rename(): void
    {
        $payload = $this->policyPayload();
        $payload['font_asset_set']['transformation']['kind'] = 'subset';
        $payload['font_asset_set']['license']['reserved_font_names'] = ['Inter'];

        $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/font-assets/check', $payload)
            ->assertUnprocessable()
            ->assertJsonFragment(['field' => 'font_asset_set.transformation.renamed_family']);

        $payload['font_asset_set']['transformation']['renamed_family'] = 'Magic Sans';
        $payload['font_assets'][0]['family'] = 'Magic Sans';

        $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/font-assets/check', $payload)
            ->assertOk()
            ->assertJsonPath('family', 'Magic Sans')
            ->assertJsonPath('warnings.0', 'font-subset-is-modified')
            ->assertJsonPath('warnings.1', 'font-family-renamed-for-reserved-name');
    }

    public function test_asset_set_must_exactly_govern_the_declared_woff2_assets(): void
    {
        $payload = $this->policyPayload();
        $payload['font_asset_set']['asset_refs'] = ['different-font'];

        $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/font-assets/check', $payload)
            ->assertUnprocessable()
            ->assertJsonFragment(['field' => 'font_asset_set.asset_refs']);

        $payload = $this->policyPayload();
        $payload['font_assets'][0]['family'] = 'Different Sans';

        $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/font-assets/check', $payload)
            ->assertUnprocessable()
            ->assertJsonFragment(['field' => 'font_assets.0.family']);
    }

    public function test_subset_policy_fails_closed_for_non_ofl_fonts(): void
    {
        $payload = $this->policyPayload();
        $payload['font_asset_set']['upstream']['family_path'] = 'apache/inter';
        $payload['font_asset_set']['license']['spdx'] = 'Apache-2.0';
        $payload['font_asset_set']['transformation']['kind'] = 'subset';

        $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/font-assets/check', $payload)
            ->assertUnprocessable()
            ->assertJsonFragment(['field' => 'font_asset_set.transformation.kind']);
    }

    public function test_licensed_profile_materializes_only_after_policy_validation(): void
    {
        $policyPayload = $this->policyPayload();
        $manifest = app(AdapterRegistry::class)->manifest();
        $payload = [
            'contract_version' => '1.0',
            'component_ast' => [
                'contract_version' => '1.0',
                'surface_id' => 'licensed-font-demo',
                'locale' => 'ja-JP',
                'components' => [[
                    'key' => 'intro',
                    'composition' => 'content',
                    'variant' => 'prose',
                    'slots' => [[
                        'role' => 'Title',
                        'content' => ['kind' => 'text', 'value' => 'Licensed Google Fonts'],
                    ]],
                ]],
            ],
            'adapter_profile' => [
                ...app(AdapterRegistry::class)->defaultProfile(),
                'font_adapter' => 'licensed-self-hosted',
                'font_family' => 'sans',
                'catalog_locks' => $manifest['profiles'][0]['catalog_locks_by_font_adapter']['licensed-self-hosted'],
            ],
            'assets' => [],
            'font_assets' => $policyPayload['font_assets'],
            'font_asset_set' => $policyPayload['font_asset_set'],
        ];

        $result = $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/materialize', $payload)
            ->assertOk()
            ->assertJsonPath('font_policy.eligible_for_materialization', true)
            ->assertJsonPath('required_assets.0.asset_ref', 'font-inter-400')
            ->json();

        $this->assertStringContainsString('@font-face{font-family:"Inter"', $result['css']);

        unset($payload['font_asset_set']);
        $this->withToken('presentation-test-token')
            ->postJson('/api/v1/presentations/materialize', $payload)
            ->assertUnprocessable()
            ->assertJsonFragment(['field' => 'font_asset_set']);
    }

    /** @return array<string,mixed> */
    private function policyPayload(): array
    {
        return [
            'contract_version' => '1.0',
            'font_asset_set' => [
                'contract_version' => '1.0',
                'set_id' => 'google-fonts-inter-v20',
                'provider' => 'google-fonts',
                'family' => 'Inter',
                'upstream' => [
                    'catalog' => 'google/fonts',
                    'revision' => '0123456789abcdef0123456789abcdef01234567',
                    'family_path' => 'ofl/inter',
                    'version' => 'v20',
                    'source_digest' => str_repeat('a', 64),
                ],
                'license' => [
                    'spdx' => 'OFL-1.1',
                    'copyright_notice' => 'Copyright 2020 The Inter Project Authors',
                    'license_text_path' => 'licenses/google-fonts/inter/OFL.txt',
                    'license_text_sha256' => str_repeat('b', 64),
                    'reserved_font_names' => [],
                ],
                'transformation' => [
                    'kind' => 'none',
                    'metadata_preserved' => true,
                    'renamed_family' => null,
                ],
                'asset_refs' => ['font-inter-400'],
            ],
            'font_assets' => [[
                'asset_ref' => 'font-inter-400',
                'path' => 'assets/fonts/inter-v20-400.woff2',
                'mime' => 'font/woff2',
                'sha256' => str_repeat('c', 64),
                'family' => 'Inter',
                'weight' => 400,
                'style' => 'normal',
            ]],
        ];
    }
}
