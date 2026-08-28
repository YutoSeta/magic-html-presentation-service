<?php

namespace App\Providers;

use App\Presentation\AdapterRegistry;
use App\Presentation\Adapters\HeroiconsAdapter;
use App\Presentation\Adapters\LicensedSelfHostedFontAdapter;
use App\Presentation\Adapters\SelfHostedFontAdapter;
use App\Presentation\Adapters\SystemFontAdapter;
use App\Presentation\Adapters\TailwindComponentAdapter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Yutoseta\LaravelAdmin\Admin\AdminManifestMetadataRegistry;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(HeroiconsAdapter::class, fn (): HeroiconsAdapter => new HeroiconsAdapter(
            (string) config('presentation.heroicons_path'),
        ));
        $this->app->singleton(AdapterRegistry::class, fn ($app): AdapterRegistry => new AdapterRegistry(
            $app->make(TailwindComponentAdapter::class),
            $app->make(HeroiconsAdapter::class),
            $app->make(SystemFontAdapter::class),
            $app->make(SelfHostedFontAdapter::class),
            $app->make(LicensedSelfHostedFontAdapter::class),
            (string) config('presentation.compiled_css_path'),
            (string) config('presentation.utility_version'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();
        RateLimiter::for('presentation-requests', fn (Request $request): Limit => Limit::perMinute((int) config('presentation.requests_per_minute'))
            ->by((string) $request->bearerToken()));

        $this->app->make(AdminManifestMetadataRegistry::class)->register(
            'presentation_adapters',
            fn (): array => $this->app->make(AdapterRegistry::class)->manifest(),
        );
    }
}
