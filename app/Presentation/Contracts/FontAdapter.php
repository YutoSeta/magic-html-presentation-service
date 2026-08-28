<?php

namespace App\Presentation\Contracts;

interface FontAdapter
{
    public function key(): string;

    public function version(): string;

    public function digest(): string;

    /** @param array<int,array<string,mixed>> $fontAssets */
    public function compile(string $family, array $fontAssets): string;
}
