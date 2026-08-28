<?php

namespace App\Presentation\Contracts;

use DOMDocument;
use DOMElement;

interface ComponentAdapter
{
    public function key(): string;

    public function version(): string;

    /** @param array<string,mixed> $component @param array<string,array<string,mixed>> $assets */
    public function render(DOMDocument $document, array $component, IconAdapter $icons, string $iconStyle, array $assets): DOMElement;
}
