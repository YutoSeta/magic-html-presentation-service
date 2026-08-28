<?php

declare(strict_types=1);

namespace App\Presentation\Adapters;

use App\Presentation\Contracts\FontAdapter;
use App\Presentation\LicensedFontPolicy;

final class LicensedSelfHostedFontAdapter implements FontAdapter
{
    public function __construct(private readonly SelfHostedFontAdapter $fonts) {}

    public function key(): string
    {
        return 'licensed-self-hosted';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function digest(): string
    {
        return hash('sha256', $this->fonts->digest().'@'.LicensedFontPolicy::VERSION.'@'.$this->version());
    }

    public function compile(string $family, array $fontAssets): string
    {
        return $this->fonts->compile($family, $fontAssets);
    }
}
