<?php

namespace App\Presentation\Adapters;

use App\Exceptions\UnsupportedAdapterException;
use App\Presentation\Contracts\FontAdapter;
use App\Support\CanonicalJson;

final class SystemFontAdapter implements FontAdapter
{
    /** @var array<string,string> */
    private const FAMILIES = [
        'sans' => 'ui-sans-serif,system-ui,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji"',
        'sans-ja' => '"Hiragino Sans","Yu Gothic UI","Noto Sans JP",ui-sans-serif,system-ui,sans-serif',
        'serif' => 'ui-serif,Georgia,Cambria,"Times New Roman",Times,serif',
        'mono' => 'ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace',
    ];

    public function key(): string
    {
        return 'system';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function digest(): string
    {
        return hash('sha256', CanonicalJson::encode(self::FAMILIES));
    }

    public function compile(string $family, array $fontAssets): string
    {
        if ($fontAssets !== []) {
            throw new UnsupportedAdapterException('The system font adapter does not accept font assets.');
        }
        if (! isset(self::FAMILIES[$family])) {
            throw new UnsupportedAdapterException("System font family {$family} is not supported.");
        }

        return ':root{--m-font-family:'.self::FAMILIES[$family].'}.m-presentation{font-family:var(--m-font-family)}';
    }
}
