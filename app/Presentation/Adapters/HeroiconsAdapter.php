<?php

namespace App\Presentation\Adapters;

use App\Exceptions\UnsupportedAdapterException;
use App\Presentation\Contracts\IconAdapter;
use DOMDocument;
use DOMElement;
use RuntimeException;

final class HeroiconsAdapter implements IconAdapter
{
    /** @var array<string,mixed>|null */
    private ?array $catalog = null;

    public function __construct(private readonly string $catalogPath) {}

    public function key(): string
    {
        return 'heroicons-v2';
    }

    public function version(): string
    {
        return (string) ($this->catalog()['version'] ?? '');
    }

    public function digest(): string
    {
        return (string) ($this->catalog()['source_digest'] ?? '');
    }

    public function render(DOMDocument $document, string $intent, string $style): DOMElement
    {
        $icon = $this->catalog()['icons'][$intent][$style] ?? null;
        if (! is_array($icon)) {
            throw new UnsupportedAdapterException("Heroicons does not support {$intent} in {$style} style.");
        }

        $svgDocument = new DOMDocument('1.0', 'UTF-8');
        $fill = htmlspecialchars((string) $icon['fill'], ENT_QUOTES | ENT_XML1, 'UTF-8');
        $viewBox = htmlspecialchars((string) $icon['view_box'], ENT_QUOTES | ENT_XML1, 'UTF-8');
        $strokeWidth = is_string($icon['stroke_width'] ?? null)
            ? ' stroke-width="'.htmlspecialchars($icon['stroke_width'], ENT_QUOTES | ENT_XML1, 'UTF-8').'" stroke="currentColor"'
            : '';
        $source = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="'.$viewBox.'" fill="'.$fill.'"'.$strokeWidth.'>'.(string) $icon['body'].'</svg>';

        if (! $svgDocument->loadXML($source, LIBXML_NONET | LIBXML_NOBLANKS) || ! $svgDocument->documentElement instanceof DOMElement) {
            throw new RuntimeException('The generated Heroicons catalog contains invalid SVG.');
        }

        $svg = $document->importNode($svgDocument->documentElement, true);
        if (! $svg instanceof DOMElement) {
            throw new RuntimeException('The Heroicon SVG could not be imported.');
        }
        $svg->setAttribute('aria-hidden', 'true');
        $svg->setAttribute('focusable', 'false');
        $svg->setAttribute('data-m-icon', $intent);
        $svg->setAttribute('data-m-icon-provider', $this->key());

        return $svg;
    }

    /** @return array<string,mixed> */
    private function catalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }
        if (! is_file($this->catalogPath)) {
            throw new RuntimeException('The generated Heroicons catalog is unavailable.');
        }

        $catalog = json_decode((string) file_get_contents($this->catalogPath), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($catalog)
            || ($catalog['provider'] ?? null) !== $this->key()
            || ($catalog['version'] ?? null) !== '2.2.0'
            || ! preg_match('/^[a-f0-9]{64}$/D', (string) ($catalog['source_digest'] ?? ''))) {
            throw new RuntimeException('The generated Heroicons catalog is invalid.');
        }

        return $this->catalog = $catalog;
    }
}
