<?php

namespace App\Presentation\Contracts;

use DOMDocument;
use DOMElement;

interface IconAdapter
{
    public function key(): string;

    public function version(): string;

    public function digest(): string;

    public function render(DOMDocument $document, string $intent, string $style): DOMElement;
}
