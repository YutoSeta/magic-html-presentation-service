<?php

namespace App\Presentation\Adapters;

use App\Exceptions\AssetResolutionException;
use App\Presentation\Contracts\ComponentAdapter;
use App\Presentation\Contracts\IconAdapter;
use DOMDocument;
use DOMElement;

final class TailwindComponentAdapter implements ComponentAdapter
{
    /** @var array<string,array<string,string>> */
    private const SECTION_CLASSES = [
        'hero' => [
            'centered' => 'bg-white px-6 py-24 text-slate-950 sm:py-32 lg:px-8',
            'split-media' => 'bg-white px-6 py-20 text-slate-950 sm:py-28 lg:px-8',
        ],
        'feature-grid' => [
            'cards' => 'bg-slate-50 px-6 py-20 text-slate-950 sm:py-28 lg:px-8',
            'icon-grid' => 'bg-white px-6 py-20 text-slate-950 sm:py-28 lg:px-8',
        ],
        'content' => [
            'prose' => 'bg-white px-6 py-20 text-slate-950 sm:py-28 lg:px-8',
            'split' => 'bg-slate-50 px-6 py-20 text-slate-950 sm:py-28 lg:px-8',
        ],
        'steps' => [
            'numbered' => 'bg-slate-950 px-6 py-20 text-white sm:py-28 lg:px-8',
            'timeline' => 'bg-white px-6 py-20 text-slate-950 sm:py-28 lg:px-8',
        ],
        'testimonials' => [
            'cards' => 'bg-slate-50 px-6 py-20 text-slate-950 sm:py-28 lg:px-8',
            'spotlight' => 'bg-indigo-950 px-6 py-20 text-white sm:py-28 lg:px-8',
        ],
        'faq' => [
            'stacked' => 'bg-white px-6 py-20 text-slate-950 sm:py-28 lg:px-8',
            'two-column' => 'bg-slate-50 px-6 py-20 text-slate-950 sm:py-28 lg:px-8',
        ],
        'cta' => [
            'banner' => 'bg-indigo-600 px-6 py-16 text-white sm:py-20 lg:px-8',
            'centered' => 'bg-slate-950 px-6 py-20 text-white sm:py-28 lg:px-8',
        ],
        'contact' => [
            'split' => 'bg-white px-6 py-20 text-slate-950 sm:py-28 lg:px-8',
            'centered' => 'bg-slate-50 px-6 py-20 text-slate-950 sm:py-28 lg:px-8',
        ],
    ];

    public function key(): string
    {
        return 'tailwindcss-core';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function render(DOMDocument $document, array $component, IconAdapter $icons, string $iconStyle, array $assets): DOMElement
    {
        $composition = (string) $component['composition'];
        $variant = (string) $component['variant'];
        $section = $document->createElement('section');
        $section->setAttribute('id', 'm-component-'.$component['key']);
        $section->setAttribute('m-component', (string) $component['key']);
        $section->setAttribute('m-composition', $composition);
        $section->setAttribute('m-variant', $variant);
        $section->setAttribute('class', self::SECTION_CLASSES[$composition][$variant]);
        if (is_string($component['design_key'] ?? null)) {
            $section->setAttribute('m-design-key', $component['design_key']);
        }

        $container = $document->createElement('div');
        $container->setAttribute('class', $this->containerClasses($composition, $variant));
        $section->appendChild($container);

        $slots = $this->indexSlots($component['slots']);
        if ($composition === 'hero' && $variant === 'split-media') {
            $copy = $document->createElement('div');
            $copy->setAttribute('class', 'flex flex-col justify-center gap-6');
            foreach (['Eyebrow', 'Title', 'Text', 'Actions'] as $role) {
                $this->appendSlot($document, $copy, $slots[$role] ?? null, $composition, $variant, $icons, $iconStyle, $assets);
            }
            $container->appendChild($copy);
            $this->appendSlot($document, $container, $slots['Image'] ?? null, $composition, $variant, $icons, $iconStyle, $assets);

            return $section;
        }

        foreach ($component['slots'] as $slot) {
            $this->appendSlot($document, $container, $slot, $composition, $variant, $icons, $iconStyle, $assets);
        }

        return $section;
    }

    private function containerClasses(string $composition, string $variant): string
    {
        return match (true) {
            $composition === 'hero' && $variant === 'centered' => 'mx-auto flex max-w-4xl flex-col items-center gap-6 text-center',
            $composition === 'hero' && $variant === 'split-media' => 'mx-auto grid max-w-7xl gap-12 lg:grid-cols-2 lg:items-center',
            in_array($composition, ['content', 'contact'], true) && $variant === 'split' => 'mx-auto grid max-w-7xl gap-10 lg:grid-cols-2',
            $composition === 'faq' && $variant === 'two-column' => 'mx-auto grid max-w-7xl gap-10 lg:grid-cols-[0.7fr_1.3fr]',
            default => 'mx-auto flex max-w-7xl flex-col gap-8',
        };
    }

    /** @param array<int,array<string,mixed>> $slots @return array<string,array<string,mixed>> */
    private function indexSlots(array $slots): array
    {
        $indexed = [];
        foreach ($slots as $slot) {
            $indexed[(string) $slot['role']] = $slot;
        }

        return $indexed;
    }

    /** @param array<string,mixed>|null $slot @param array<string,array<string,mixed>> $assets */
    private function appendSlot(DOMDocument $document, DOMElement $parent, ?array $slot, string $composition, string $variant, IconAdapter $icons, string $iconStyle, array $assets): void
    {
        if ($slot === null) {
            return;
        }

        $role = (string) $slot['role'];
        $content = $slot['content'];
        $element = match ($role) {
            'Eyebrow', 'Title', 'Text' => $this->renderText($document, $role, $content, $composition, $variant),
            'Items' => $this->renderItems($document, $content, $composition, $icons, $iconStyle),
            'Actions' => $this->renderActions($document, $content, $icons, $iconStyle),
            'Image' => $this->renderImage($document, $content, $assets),
        };
        $element->setAttribute('m-slot', $role);
        $parent->appendChild($element);
    }

    /** @param array<string,mixed> $content */
    private function renderText(DOMDocument $document, string $role, array $content, string $composition, string $variant): DOMElement
    {
        $tag = $role === 'Title' ? ($composition === 'hero' ? 'h1' : 'h2') : 'p';
        $element = $document->createElement($tag);
        $dark = in_array($composition.':'.$variant, [
            'steps:numbered',
            'testimonials:spotlight',
            'cta:banner',
            'cta:centered',
        ], true);
        $element->setAttribute('class', match ($role) {
            'Eyebrow' => $dark
                ? 'text-sm font-semibold uppercase tracking-widest text-indigo-300'
                : 'text-sm font-semibold uppercase tracking-widest text-indigo-600',
            'Title' => $composition === 'hero'
                ? 'text-balance text-4xl font-semibold tracking-tight sm:text-6xl'
                : 'text-balance text-3xl font-semibold tracking-tight sm:text-5xl',
            'Text' => $dark
                ? 'max-w-3xl text-pretty text-lg leading-8 text-slate-200'
                : 'max-w-3xl text-pretty text-lg leading-8 text-slate-600',
        });
        if (($content['kind'] ?? null) === 'binding') {
            $element->setAttribute('m-field', (string) $content['path']);
            $element->setAttribute('m-resource', (string) $content['resource_key']);
            $element->appendChild($document->createTextNode((string) ($content['fallback'] ?? '')));
        } else {
            $element->appendChild($document->createTextNode((string) $content['value']));
        }

        return $element;
    }

    /** @param array<string,mixed> $content */
    private function renderItems(DOMDocument $document, array $content, string $composition, IconAdapter $icons, string $iconStyle): DOMElement
    {
        $list = $document->createElement('div');
        $list->setAttribute('class', match ($composition) {
            'feature-grid', 'testimonials' => 'grid gap-6 sm:grid-cols-2 lg:grid-cols-3',
            'faq' => 'grid gap-4',
            'steps' => 'grid gap-6 md:grid-cols-3',
            default => 'grid gap-5',
        });

        foreach ($content['items'] as $index => $item) {
            $article = $document->createElement('article');
            $article->setAttribute('m-item', '');
            $article->setAttribute('m-key', (string) $item['key']);
            $article->setAttribute('class', 'rounded-2xl border border-slate-200 bg-white p-6 shadow-sm');
            if (is_string($item['icon_intent'] ?? null)) {
                $icon = $icons->render($document, $item['icon_intent'], $iconStyle);
                $icon->setAttribute('class', 'size-7 text-indigo-600');
                $article->appendChild($icon);
            } elseif ($composition === 'steps') {
                $number = $document->createElement('span', (string) ($index + 1));
                $number->setAttribute('class', 'grid size-9 place-items-center rounded-full bg-indigo-600 text-sm font-semibold text-white');
                $article->appendChild($number);
            }
            $title = $document->createElement('h3');
            $title->setAttribute('class', 'mt-4 text-lg font-semibold text-slate-950');
            $title->appendChild($document->createTextNode((string) $item['title']));
            $article->appendChild($title);
            if (is_string($item['text'] ?? null) && $item['text'] !== '') {
                $text = $document->createElement('p');
                $text->setAttribute('class', 'mt-2 text-sm leading-6 text-slate-600');
                $text->appendChild($document->createTextNode($item['text']));
                $article->appendChild($text);
            }
            $list->appendChild($article);
        }

        return $list;
    }

    /** @param array<string,mixed> $content */
    private function renderActions(DOMDocument $document, array $content, IconAdapter $icons, string $iconStyle): DOMElement
    {
        $actions = $document->createElement('div');
        $actions->setAttribute('class', 'flex flex-wrap items-center gap-3');
        foreach ($content['actions'] as $action) {
            $link = $document->createElement('a');
            $link->setAttribute('href', (string) $action['href']);
            $link->setAttribute('class', $action['tone'] === 'primary'
                ? 'inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600'
                : 'inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500');
            $link->appendChild($document->createTextNode((string) $action['label']));
            if (is_string($action['icon_intent'] ?? null)) {
                $icon = $icons->render($document, $action['icon_intent'], $iconStyle);
                $icon->setAttribute('class', 'size-4');
                $link->appendChild($icon);
            }
            $actions->appendChild($link);
        }

        return $actions;
    }

    /** @param array<string,mixed> $content @param array<string,array<string,mixed>> $assets */
    private function renderImage(DOMDocument $document, array $content, array $assets): DOMElement
    {
        $assetRef = (string) $content['asset_ref'];
        $asset = $assets[$assetRef] ?? null;
        if (! is_array($asset)) {
            throw new AssetResolutionException("Image asset {$assetRef} is not resolved.");
        }

        $figure = $document->createElement('figure');
        $figure->setAttribute('class', 'overflow-hidden rounded-3xl bg-slate-100 shadow-xl ring-1 ring-slate-950/10');
        $image = $document->createElement('img');
        $image->setAttribute('src', (string) $asset['path']);
        $image->setAttribute('alt', (string) $content['alt']);
        $image->setAttribute('m-asset', $assetRef);
        $image->setAttribute('class', 'aspect-[4/3] h-full w-full object-cover');
        $image->setAttribute('loading', 'lazy');
        $figure->appendChild($image);

        return $figure;
    }
}
